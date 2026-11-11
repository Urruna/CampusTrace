<?php

declare(strict_types=1);

final class HttpTestClient
{
    private $cookieFile;

    public function __construct()
    {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'campustrace_http_');
    }

    public function __destruct()
    {
        if (is_string($this->cookieFile) && is_file($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    public function request(string $path, string $method = 'GET', array $data = []): array
    {
        $headers = [];
        $ch = curl_init('http://127.0.0.1:8080' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$headers): int {
                $headers[] = trim($line);
                return strlen($line);
            },
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $body = curl_exec($ch);
        if ($body === false) {
            throw new RuntimeException('HTTP request failed: ' . curl_error($ch));
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $location = null;
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, 9));
            }
        }

        return [
            'status' => $status,
            'body' => $body,
            'location' => $location,
        ];
    }
}

$passed = 0;
$failed = 0;

function assert_test(bool $condition, string $name): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[PASS] {$name}\n";
        return;
    }

    $failed++;
    echo "[FAIL] {$name}\n";
}

function csrf_from(string $html): string
{
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $html, $matches)) {
        throw new RuntimeException('CSRF token was not found.');
    }

    return $matches[1];
}

$config = require dirname(__DIR__, 2) . '/app/config.local.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_password'],
    $config['db_name'],
    $config['db_port']
);
$db->set_charset('utf8mb4');

$suffix = bin2hex(random_bytes(5));
$username = 'm1test_' . $suffix;
$expiredUsername = 'm1exp_' . $suffix;
$password = 'CampusTraceTest!2026';
$inviteCode = 'CAMPUS-M1-' . strtoupper($suffix);
$codeHash = hash('sha256', $inviteCode);
$expiresAt = date('Y-m-d H:i:s', time() + 3600);
$expiredCode = 'CAMPUS-M1-EXP-' . strtoupper($suffix);
$expiredHash = hash('sha256', $expiredCode);
$expiredAt = date('Y-m-d H:i:s', time() - 3600);

$stmt = $db->prepare(
    'INSERT INTO invite_codes (code_hash, expires_at, used_by, used_at)
     VALUES (?, ?, NULL, NULL)'
);
$stmt->bind_param('ss', $codeHash, $expiresAt);
$stmt->execute();
$stmt->close();

$stmt = $db->prepare(
    'INSERT INTO invite_codes (code_hash, expires_at, used_by, used_at)
     VALUES (?, ?, NULL, NULL)'
);
$stmt->bind_param('ss', $expiredHash, $expiredAt);
$stmt->execute();
$stmt->close();

try {
    $client = new HttpTestClient();

    $page = $client->request('/register');
    assert_test($page['status'] === 200, 'GET /register returns 200');
    $csrf = csrf_from($page['body']);

    $page = $client->request('/register', 'POST', [
        '_csrf' => $csrf,
        'username' => $username,
        'password' => $password,
        'password_confirmation' => $password,
        'contact_text' => 'initial-contact@example.test',
    ]);
    assert_test($page['status'] === 302, 'valid registration redirects');
    assert_test($page['location'] === '/activate', 'registration redirects to activation');

    $stmt = $db->prepare('SELECT activated_at FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $registeredUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    assert_test($registeredUser !== null && $registeredUser['activated_at'] === null, 'new user is inactive');

    $page = $client->request('/activate');
    assert_test($page['status'] === 200, 'GET /activate returns 200');
    $csrf = csrf_from($page['body']);

    $page = $client->request('/activate', 'POST', [
        '_csrf' => $csrf,
        'username' => $username,
        'invite_code' => $inviteCode,
    ]);
    assert_test($page['status'] === 302 && $page['location'] === '/login', 'valid invite activates account');

    $stmt = $db->prepare(
        'SELECT u.activated_at, i.used_by, i.used_at
         FROM users u
         JOIN invite_codes i ON i.used_by = u.id
         WHERE u.username = ? AND i.code_hash = ?'
    );
    $stmt->bind_param('ss', $username, $codeHash);
    $stmt->execute();
    $activation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    assert_test(
        $activation !== null &&
        $activation['activated_at'] !== null &&
        $activation['used_by'] !== null &&
        $activation['used_at'] !== null,
        'activation and invite use are recorded'
    );

    $page = $client->request('/activate');
    $csrf = csrf_from($page['body']);
    $page = $client->request('/activate', 'POST', [
        '_csrf' => $csrf,
        'username' => $username,
        'invite_code' => $inviteCode,
    ]);
    assert_test($page['status'] === 422, 'invite cannot be used twice');

    $page = $client->request('/login');
    $csrf = csrf_from($page['body']);
    $page = $client->request('/login', 'POST', [
        '_csrf' => $csrf,
        'username' => $username,
        'password' => $password,
    ]);
    assert_test($page['status'] === 302 && $page['location'] === '/', 'activated user can log in');

    $page = $client->request('/account/contact');
    assert_test($page['status'] === 200, 'authenticated user can open contact page');
    assert_test(strpos($page['body'], 'initial-contact@example.test') !== false, 'contact page shows own contact');
    $csrf = csrf_from($page['body']);

    $page = $client->request('/account/contact', 'POST', [
        '_csrf' => $csrf,
        'id' => '1',
        'contact_text' => 'updated-contact@example.test',
    ]);
    assert_test($page['status'] === 302 && $page['location'] === '/account/contact', 'contact update redirects');

    $page = $client->request('/account/contact');
    assert_test(strpos($page['body'], 'updated-contact@example.test') !== false, 'updated contact is persisted');

    $page = $client->request('/account/contact', 'POST', [
        'contact_text' => 'csrf-bypass@example.test',
    ]);
    assert_test($page['status'] === 400, 'contact update without CSRF is rejected');

    $stmt = $db->query("SELECT contact_text FROM users WHERE username = 'alice'");
    $aliceContact = $stmt->fetch_assoc()['contact_text'];
    $stmt->close();
    assert_test($aliceContact === '模拟联系方式：alice@example.test', 'contact update cannot change another user');

    $anonymous = new HttpTestClient();
    $page = $anonymous->request('/account/contact');
    assert_test($page['status'] === 302 && $page['location'] === '/login', 'guest cannot open contact page');

    $duplicateClient = new HttpTestClient();
    $page = $duplicateClient->request('/register');
    $csrf = csrf_from($page['body']);
    $page = $duplicateClient->request('/register', 'POST', [
        '_csrf' => $csrf,
        'username' => 'alice',
        'password' => $password,
        'password_confirmation' => $password,
        'contact_text' => '',
    ]);
    assert_test($page['status'] === 422, 'duplicate username is rejected');

    $csrfClient = new HttpTestClient();
    $page = $csrfClient->request('/register', 'POST', [
        'username' => 'csrf_test_' . $suffix,
        'password' => $password,
        'password_confirmation' => $password,
    ]);
    assert_test($page['status'] === 400, 'missing CSRF token is rejected');

    $page = $csrfClient->request('/register');
    $csrf = csrf_from($page['body']);
    $page = $csrfClient->request('/register', 'POST', [
        '_csrf' => $csrf,
        'username' => 'shortpw_' . $suffix,
        'password' => 'short',
        'password_confirmation' => 'short',
        'contact_text' => '',
    ]);
    assert_test($page['status'] === 422, 'short password is rejected');

    $expiredClient = new HttpTestClient();
    $page = $expiredClient->request('/register');
    $csrf = csrf_from($page['body']);
    $page = $expiredClient->request('/register', 'POST', [
        '_csrf' => $csrf,
        'username' => $expiredUsername,
        'password' => $password,
        'password_confirmation' => $password,
        'contact_text' => '',
    ]);
    assert_test($page['status'] === 302, 'expired-code fixture user registration succeeds');

    $page = $expiredClient->request('/activate');
    $csrf = csrf_from($page['body']);
    $page = $expiredClient->request('/activate', 'POST', [
        '_csrf' => $csrf,
        'username' => $expiredUsername,
        'invite_code' => $expiredCode,
    ]);
    assert_test($page['status'] === 422, 'expired invite is rejected');

    $activateCsrfClient = new HttpTestClient();
    $page = $activateCsrfClient->request('/activate', 'POST', [
        'username' => $expiredUsername,
        'invite_code' => $expiredCode,
    ]);
    assert_test($page['status'] === 400, 'activation without CSRF is rejected');
} finally {
    $stmt = $db->prepare('DELETE FROM invite_codes WHERE code_hash = ?');
    $stmt->bind_param('s', $codeHash);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare('DELETE FROM invite_codes WHERE code_hash = ?');
    $stmt->bind_param('s', $expiredHash);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare('DELETE FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare('DELETE FROM users WHERE username = ?');
    $stmt->bind_param('s', $expiredUsername);
    $stmt->execute();
    $stmt->close();
}

echo "\nPassed: {$passed}, Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
