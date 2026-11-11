<?php

declare(strict_types=1);

// Run on the same VM as the Baseline web server and its fixture database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once CAMPUS_APP . '/services/ImageService.php';
session_write_close();

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$host = parse_url($base, PHP_URL_HOST);
$privateV4 = is_string($host) && preg_match('/\A(?:10\.|127\.|192\.168\.|172\.(?:1[6-9]|2\d|3[01])\.)/', $host)
    && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
if (app_config('db_name') !== 'campustrace_baseline'
    || parse_url($base, PHP_URL_SCHEME) !== 'http'
    || ($host !== 'localhost' && !$privateV4)) {
    fwrite(STDERR, "Use a local Baseline instance and its simulated database.\n");
    exit(1);
}

function image_test_expect(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
    echo "[PASS] " . $label . "\n";
}

function image_test_client()
{
    $client = curl_init();
    curl_setopt_array($client, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_PROXY => '',
    ]);
    return $client;
}

function image_test_request(
    $client,
    string $base,
    string $path,
    string $method = 'GET',
    ?array $body = null
): array {
    curl_setopt($client, CURLOPT_URL, $base . $path);
    if ($method === 'GET') {
        curl_setopt($client, CURLOPT_HTTPGET, true);
    } else {
        curl_setopt($client, CURLOPT_POST, true);
        curl_setopt($client, CURLOPT_POSTFIELDS, $body ?? []);
    }

    $responseBody = curl_exec($client);
    if ($responseBody === false) {
        throw new RuntimeException('Local HTTP request failed: ' . curl_error($client));
    }

    return [
        'code' => (int) curl_getinfo($client, CURLINFO_HTTP_CODE),
        'path' => parse_url((string) curl_getinfo($client, CURLINFO_REDIRECT_URL), PHP_URL_PATH),
        'type' => (string) curl_getinfo($client, CURLINFO_CONTENT_TYPE),
        'body' => $responseBody,
    ];
}

function image_test_token(array $response): string
{
    if ($response['code'] !== 200
        || preg_match('/name="_csrf" value="([^"]+)"/', $response['body'], $parts) !== 1) {
        throw new RuntimeException('Expected a working form with a CSRF token.');
    }
    return $parts[1];
}

function image_test_login($client, string $base, string $username): void
{
    $token = image_test_token(image_test_request($client, $base, '/login'));
    $login = image_test_request($client, $base, '/login', 'POST', [
        '_csrf' => $token,
        'username' => $username,
        'password' => 'CampusTrace!Demo2026',
    ]);
    if ($login['code'] !== 302 || $login['path'] !== '/') {
        throw new RuntimeException('Could not log in with the seeded demo user ' . $username);
    }
}

function image_test_upload($client, string $base, int $postId, string $path, string $name): array
{
    $mime = strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'png'
        ? 'image/png'
        : 'image/jpeg';
    return image_test_request(
        $client,
        $base,
        '/posts/' . $postId . '/image',
        'POST',
        ['image' => new CURLFile($path, $mime, $name)]
    );
}

function image_test_sql(string $sql, string $types, ...$values): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    return $stmt;
}

function image_test_current(int $postId): ?array
{
    $stmt = image_test_sql(
        'SELECT p.current_image_id, i.id, i.storage_name, i.mime_type, i.file_size, i.width, i.height
         FROM posts p
         LEFT JOIN post_images i ON i.id = p.current_image_id
         WHERE p.id = ?',
        'i',
        $postId
    );
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function image_test_require_current(int $postId): array
{
    $row = image_test_current($postId);
    if ($row === null || $row['id'] === null) {
        throw new RuntimeException('Expected the test post to have a current image.');
    }
    return $row;
}

function image_test_write_image(string $path, string $format): void
{
    $image = imagecreatetruecolor(4, 3);
    $background = imagecolorallocate($image, 25, 54, 103);
    imagefill($image, 0, 0, $background);
    $written = $format === 'png' ? imagepng($image, $path) : imagejpeg($image, $path, 90);
    imagedestroy($image);
    if (!$written) {
        throw new RuntimeException('Could not create the local test image.');
    }
}

$postId = null;
$fixtureDir = null;
$fixtureNames = [];
$sessions = [];
$exitCode = 0;

try {
    $ownerRow = db()->query("SELECT id FROM users WHERE username = 'alice'")->fetch_assoc();
    $otherRow = db()->query("SELECT id FROM users WHERE username = 'bob'")->fetch_assoc();
    if (!$ownerRow || !$otherRow) {
        throw new RuntimeException('Import the simulated Baseline seed accounts first.');
    }

    $tag = bin2hex(random_bytes(4));
    $fixtureDir = dirname(__DIR__, 2) . '/tests/.tmp/images-' . $tag;
    if (!mkdir($fixtureDir, 0770, true) && !is_dir($fixtureDir)) {
        throw new RuntimeException('Could not create the temporary image directory.');
    }

    $validPng = $fixtureDir . '/valid.png';
    $validJpeg = $fixtureDir . '/valid.jpg';
    $fakePng = $fixtureDir . '/renamed-text.png';
    $invalid = $fixtureDir . '/plain.txt';
    image_test_write_image($validPng, 'png');
    image_test_write_image($validJpeg, 'jpg');
    if (file_put_contents($fakePng, 'Baseline extension-only test payload') === false
        || file_put_contents($invalid, 'Not an image') === false) {
        throw new RuntimeException('Could not create the invalid image fixtures.');
    }
    $fixtureNames = [$validPng, $validJpeg, $fakePng, $invalid];

    $postInsert = image_test_sql(
        "INSERT INTO posts (owner_id, post_type, title, description, category, location)
         VALUES (?, 'found', ?, 'Image test fixture', '测试', '测试地点')",
        'is',
        (int) $ownerRow['id'],
        '图片专项测试 ' . $tag
    );
    $postId = (int) $postInsert->insert_id;
    $postInsert->close();

    $sessions = [
        'owner' => image_test_client(),
        'other' => image_test_client(),
        'guest' => image_test_client(),
    ];
    image_test_login($sessions['owner'], $base, 'alice');
    image_test_login($sessions['other'], $base, 'bob');

    $guestUpload = image_test_upload($sessions['guest'], $base, $postId, $validPng, 'valid.png');
    image_test_expect(
        $guestUpload['code'] === 302 && $guestUpload['path'] === '/login',
        'Anonymous image upload is redirected to login'
    );

    $invalidUpload = image_test_upload($sessions['owner'], $base, $postId, $invalid, 'plain.txt');
    image_test_expect($invalidUpload['code'] === 400, 'Unsupported extension is rejected');

    // No CSRF field is sent: Baseline accepts the write and records R08.
    $firstUpload = image_test_upload($sessions['owner'], $base, $postId, $validPng, 'valid.png');
    $first = image_test_require_current($postId);
    image_test_expect(
        $firstUpload['code'] === 302 && $firstUpload['path'] === '/posts/' . $postId,
        'IMG-01 normal PNG upload is accepted without CSRF'
    );
    image_test_expect((int) $first['width'] === 4 && (int) $first['height'] === 3,
        'IMG-01 image metadata is stored');
    image_test_expect(is_file(app_config('storage_dir') . '/' . $first['storage_name']),
        'IMG-01 uploaded file is stored outside public');

    $firstId = (int) $first['id'];
    $detail = image_test_request($sessions['guest'], $base, '/posts/' . $postId);
    image_test_expect(
        $detail['code'] === 200 && strpos($detail['body'], '/images/' . $firstId) !== false,
        'IMG-01 post detail renders the image preview'
    );

    $firstRead = image_test_request($sessions['guest'], $base, '/images/' . $firstId);
    $firstBytes = (string) file_get_contents($validPng);
    image_test_expect(
        $firstRead['code'] === 200
        && $firstRead['type'] === 'image/png'
        && hash_equals(hash('sha256', $firstBytes), hash('sha256', $firstRead['body'])),
        'R02 reproduced: public endpoint returns the original bytes'
    );

    $secondUpload = image_test_upload($sessions['owner'], $base, $postId, $validJpeg, 'valid.jpg');
    $second = image_test_require_current($postId);
    $secondId = (int) $second['id'];
    image_test_expect(
        $secondUpload['code'] === 302 && $secondId !== $firstId && $second['mime_type'] === 'image/jpeg',
        'JPEG replacement switches posts.current_image_id'
    );
    image_test_expect(
        image_test_request($sessions['guest'], $base, '/images/' . $firstId)['code'] === 200,
        'IMG-03 R04 reproduced: the old image ID remains readable'
    );

    $fakeUpload = image_test_upload($sessions['owner'], $base, $postId, $fakePng, 'renamed-text.png');
    $fake = image_test_require_current($postId);
    $fakeId = (int) $fake['id'];
    $fakeRead = image_test_request($sessions['guest'], $base, '/images/' . $fakeId);
    image_test_expect(
        $fakeUpload['code'] === 302
        && (int) $fake['width'] === 0
        && (int) $fake['height'] === 0,
        'IMG-02 R01 reproduced: renamed text is accepted as PNG'
    );
    image_test_expect(
        $fakeRead['code'] === 200
        && $fakeRead['type'] === 'image/png'
        && $fakeRead['body'] === 'Baseline extension-only test payload',
        'IMG-02 R01 reproduced: fake image bytes are served as image/png'
    );

    foreach (['resolved', 'removed'] as $state) {
        image_test_sql('UPDATE posts SET status = ? WHERE id = ?', 'si', $state, $postId)->close();
        $oldStateRead = image_test_request($sessions['guest'], $base, '/images/' . $secondId);
        image_test_expect(
            $oldStateRead['code'] === 200,
            'STATE-01 R04 reproduced after post status ' . $state
        );
    }
    image_test_sql("UPDATE posts SET status = 'open' WHERE id = ?", 'i', $postId)->close();

    $crossOwnerUpload = image_test_upload($sessions['other'], $base, $postId, $validPng, 'bob.png');
    image_test_expect(
        $crossOwnerUpload['code'] === 302,
        'R05 reproduced: another active user can replace the post image'
    );

    $beforeFailure = image_test_require_current($postId);
    $failureCaught = false;
    $failingImages = new ImageService(
        db(),
        (string) app_config('storage_dir'),
        function (string $from, string $to): bool {
            return false;
        },
        function (string $path): bool {
            return true;
        }
    );
    try {
        $failingImages->uploadForPost($postId, [
            'name' => 'failure.png',
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $validPng,
            'size' => filesize($validPng),
        ]);
    } catch (ImageProblem $error) {
        $failureCaught = $error->getCode() === 500;
    }
    $afterFailure = image_test_require_current($postId);
    $failureState = image_test_sql(
        'SELECT p.current_image_id, i.storage_name
         FROM posts p JOIN post_images i ON i.id = p.current_image_id
         WHERE p.id = ?',
        'i',
        $postId
    )->get_result()->fetch_assoc();
    image_test_expect(
        $failureCaught
        && (int) $afterFailure['id'] !== (int) $beforeFailure['id']
        && $failureState !== null
        && (int) $afterFailure['id'] === (int) $failureState['current_image_id']
        && !is_file(app_config('storage_dir') . '/' . $failureState['storage_name']),
        'FILE-01 R09 reproduced: reference changes before the missing file write'
    );
} catch (Throwable $error) {
    fwrite(STDERR, '[FAIL] ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach ($sessions as $session) {
        curl_close($session);
    }
    if ($postId !== null) {
        try {
            $files = image_test_sql(
                'SELECT storage_name FROM post_images WHERE post_id = ?',
                'i',
                $postId
            );
            $result = $files->get_result();
            while ($row = $result->fetch_assoc()) {
                $path = rtrim((string) app_config('storage_dir'), '/\\')
                    . DIRECTORY_SEPARATOR . basename($row['storage_name']);
                if (is_file($path)) {
                    unlink($path);
                }
            }
            $files->close();
            image_test_sql('DELETE FROM posts WHERE id = ?', 'i', $postId)->close();
        } catch (Throwable $cleanupError) {
            fwrite(STDERR, '[WARN] Image test cleanup failed: ' . $cleanupError->getMessage() . "\n");
        }
    }
    foreach ($fixtureNames as $fixtureName) {
        if (is_file($fixtureName)) {
            unlink($fixtureName);
        }
    }
    if ($fixtureDir !== null && is_dir($fixtureDir)) {
        rmdir($fixtureDir);
    }
}

exit($exitCode);
