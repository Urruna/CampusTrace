<?php

declare(strict_types=1);

// Run on the same VM as the Baseline web server and its fixture database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
session_write_close();

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$host = parse_url($base, PHP_URL_HOST);
$privateV4 = is_string($host) && preg_match('/\A(?:10\.|127\.|192\.168\.|172\.(?:1[6-9]|2\d|3[01])\.)/', $host)
    && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
if (app_config('db_name') !== 'campustrace_baseline'
    || parse_url($base, PHP_URL_SCHEME) !== 'http' || ($host !== 'localhost' && !$privateV4)) {
    fwrite(STDERR, "Use a local Baseline instance and its simulated database.\n");
    exit(1);
}

function claim_test_expect(bool $ok, string $label): void
{
    if (!$ok) {
        throw new RuntimeException($label);
    }
    echo "[PASS] $label\n";
}

function claim_test_session()
{
    $client = curl_init();
    curl_setopt_array($client, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_PROXY => '',
    ]);
    return $client;
}

function claim_test_request($client, string $base, string $path, ?array $body = null): array
{
    curl_setopt($client, CURLOPT_URL, $base . $path);
    if ($body === null) {
        curl_setopt($client, CURLOPT_HTTPGET, true);
    } else {
        curl_setopt($client, CURLOPT_POST, true);
        curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($body));
    }
    $html = curl_exec($client);
    if ($html === false) {
        throw new RuntimeException('Local HTTP request failed: ' . curl_error($client));
    }
    return [
        'code' => curl_getinfo($client, CURLINFO_HTTP_CODE),
        'path' => parse_url((string) curl_getinfo($client, CURLINFO_REDIRECT_URL), PHP_URL_PATH),
        'body' => $html,
    ];
}

function claim_test_token(array $response): string
{
    if ($response['code'] !== 200 || !preg_match('/name="_csrf" value="([^"]+)"/', $response['body'], $parts)) {
        throw new RuntimeException('Expected a working form with a CSRF token.');
    }
    return $parts[1];
}

function claim_test_login($client, string $base, string $username): string
{
    $token = claim_test_token(claim_test_request($client, $base, '/login'));
    $login = claim_test_request($client, $base, '/login', [
        '_csrf' => $token, 'username' => $username, 'password' => 'CampusTrace!Demo2026',
    ]);
    if ($login['code'] !== 302 || $login['path'] !== '/') {
        throw new RuntimeException('Could not log in with the seeded demo user ' . $username);
    }
    return claim_test_token(claim_test_request($client, $base, '/'));
}

function claim_test_sql(string $sql, string $types, ...$values): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    return $stmt;
}

function claim_test_rows(int $postId): array
{
    $stmt = claim_test_sql('SELECT id, status FROM claims WHERE post_id = ? ORDER BY id', 'i', $postId);
    $rows = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'status', 'id');
    $stmt->close();
    return $rows;
}

$postId = null;
$testUserId = null;
$sessions = [];
$exitCode = 0;
try {
    $ownerRow = db()->query("SELECT id, contact_text FROM users WHERE username = 'alice'")->fetch_assoc();
    if (!$ownerRow || strpos($ownerRow['contact_text'] ?? '', '@example.test') === false) {
        throw new RuntimeException('Import the simulated Baseline seed accounts first.');
    }
    $tag = bin2hex(random_bytes(4));
    $username = 'claim_fixture_' . $tag;
    $hash = password_hash('CampusTrace!Demo2026', PASSWORD_DEFAULT);
    $userInsert = claim_test_sql(
        "INSERT INTO users (username, password_hash, role, activated_at) VALUES (?, ?, 'user', NOW())",
        'ss', $username, $hash
    );
    $testUserId = (int) $userInsert->insert_id;
    $userInsert->close();
    $postInsert = claim_test_sql(
        "INSERT INTO posts (owner_id, post_type, title, description, category, location)
         VALUES (?, 'found', ?, 'Claims test fixture', '测试', '测试地点')",
        'is', (int) $ownerRow['id'], '认领专项测试 ' . $tag
    );
    $postId = (int) $postInsert->insert_id;
    $postInsert->close();
    $entry = '/posts/' . $postId . '/claims';

    $sessions = [$alice = claim_test_session(), $bob = claim_test_session(), $other = claim_test_session(), $admin = claim_test_session(), $guest = claim_test_session()];
    $aliceToken = claim_test_login($alice, $base, 'alice');
    claim_test_login($bob, $base, 'bob');
    claim_test_login($other, $base, $username);
    $adminToken = claim_test_login($admin, $base, 'admin');
    $bobToken = claim_test_token(claim_test_request($bob, $base, $entry));
    $otherToken = claim_test_token(claim_test_request($other, $base, $entry));

    $body = ['_csrf' => $bobToken, 'reason' => '模拟理由 <b>文字</b>', 'clue' => '模拟线索 BLUE-42'];
    $submitted = claim_test_request($bob, $base, $entry, $body);
    claim_test_expect($submitted['code'] === 302 && preg_match('#^/claims/\d+$#', $submitted['path'] ?? '') === 1, 'CLAIM-01 submit a new claim');
    $claimPath = $submitted['path'];
    $claimId = (int) basename($claimPath);
    $contactPath = $claimPath . '/contact';
    $detail = claim_test_request($bob, $base, $claimPath);
    claim_test_expect($detail['code'] === 200 && strpos($detail['body'], '&lt;b&gt;文字&lt;/b&gt;') !== false, 'Applicant sees own escaped claim');
    $inbox = claim_test_request($alice, $base, $entry);
    claim_test_expect($inbox['code'] === 200 && strpos($inbox['body'], $claimPath . '"') !== false, 'Owner finds the claim in the inbox');
    claim_test_expect(claim_test_request($bob, $base, $entry, $body)['code'] === 409, 'Duplicate application rejected');
    claim_test_expect(claim_test_request($alice, $base, $entry, array_replace($body, ['_csrf' => $aliceToken]))['code'] === 403, 'Self-claim rejected');
    claim_test_expect(claim_test_request($other, $base, $claimPath)['code'] === 403, 'Unrelated user cannot read private claim details');
    claim_test_expect(claim_test_request($admin, $base, $claimPath . '/approve', ['_csrf' => $adminToken])['code'] === 403, 'Administrator cannot approve another owner\'s claim');
    claim_test_expect(claim_test_request($alice, $base, $claimPath . '/approve', [])['code'] === 400, 'Approval requires CSRF token');
    claim_test_expect(claim_test_request($alice, $base, $claimPath . '/approve')['code'] === 405, 'GET cannot approve a claim');
    claim_test_expect(claim_test_request($alice, $base, $claimPath . '/approve', ['_csrf' => $aliceToken])['code'] === 302
        && claim_test_rows($postId) === [$claimId => 'approved'], 'Owner approves pending claim');

    $contact = (string) $ownerRow['contact_text'];
    $bobContact = claim_test_request($bob, $base, $contactPath);
    claim_test_expect($bobContact['code'] === 200 && strpos($bobContact['body'], $contact) !== false, 'CLAIM-01 approved applicant reads simulated contact');
    claim_test_expect(claim_test_request($guest, $base, $contactPath)['path'] === '/login', 'Anonymous contact request requires login');
    $otherContact = claim_test_request($other, $base, $contactPath);
    claim_test_expect($otherContact['code'] === 200 && strpos($otherContact['body'], $contact) !== false, 'CLAIM-02 R06 reproduced: wrong account receives simulated contact');

    foreach (['resolved', 'removed'] as $state) {
        // Isolate the contact endpoint from member 2's pending status routes.
        claim_test_sql('UPDATE posts SET status = ? WHERE id = ?', 'si', $state, $postId)->close();
        $old = claim_test_request($bob, $base, $contactPath);
        claim_test_expect($old['code'] === 200 && strpos($old['body'], $contact) !== false, 'STATE-01 R07 reproduced after ' . $state);
        claim_test_expect(claim_test_request($other, $base, $entry, array_replace($body, ['_csrf' => $otherToken]))['code'] === 409, 'New application rejected after ' . $state);
    }
    claim_test_sql("UPDATE posts SET status = 'open' WHERE id = ?", 'i', $postId)->close();

    $second = claim_test_request($other, $base, $entry, array_replace($body, ['_csrf' => $otherToken]));
    claim_test_expect($second['code'] === 302 && preg_match('#^/claims/\d+$#', $second['path'] ?? '') === 1, 'Second applicant submits claim');
    $secondPath = $second['path'];
    $secondId = (int) basename($secondPath);
    claim_test_expect(claim_test_request($alice, $base, $secondPath . '/approve', ['_csrf' => $aliceToken])['code'] === 302
        && claim_test_rows($postId) === [$claimId => 'revoked', $secondId => 'approved'], 'Replacement leaves exactly one approved claim');
    claim_test_expect(claim_test_request($alice, $base, $secondPath . '/revoke', ['_csrf' => $aliceToken])['code'] === 302
        && claim_test_rows($postId) === [$claimId => 'revoked', $secondId => 'revoked'], 'Owner revokes current approval');
    $third = claim_test_request($admin, $base, $entry, array_replace($body, ['_csrf' => $adminToken]));
    claim_test_expect($third['code'] === 302 && preg_match('#^/claims/\d+$#', $third['path'] ?? '') === 1, 'Third applicant submits a pending claim');
    claim_test_expect(claim_test_request($alice, $base, $third['path'] . '/reject', ['_csrf' => $aliceToken])['code'] === 302
        && claim_test_rows($postId)[(int) basename($third['path'])] === 'rejected', 'Owner rejects a pending claim');
    $audit = claim_test_sql("SELECT action FROM audit_events WHERE target_type = 'claim' AND target_id = ?", 'i', $secondId);
    $actions = array_column($audit->get_result()->fetch_all(MYSQLI_ASSOC), 'action');
    $audit->close();
    claim_test_expect(!array_diff(['claim.create', 'claim.approve', 'claim.replace_approval', 'claim.revoke'], $actions), 'Claim actions emit the expected audit events');
} catch (Throwable $error) {
    fwrite(STDERR, '[FAIL] ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach ($sessions as $session) {
        curl_close($session);
    }
    if ($postId !== null) {
        claim_test_sql(
            "DELETE FROM audit_events WHERE target_type = 'claim' AND target_id IN (SELECT id FROM claims WHERE post_id = ?)",
            'i', $postId
        )->close();
        claim_test_sql('DELETE FROM posts WHERE id = ?', 'i', $postId)->close();
    }
    if ($testUserId !== null) {
        claim_test_sql('DELETE FROM users WHERE id = ?', 'i', $testUserId)->close();
    }
}
exit($exitCode);
