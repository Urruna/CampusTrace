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

function image_multi_expect(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
    echo "[PASS] " . $label . "\n";
}

function image_multi_client()
{
    $client = curl_init();
    curl_setopt_array($client, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_PROXY => '',
    ]);
    return $client;
}

function image_multi_request(
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

function image_multi_token(array $response): string
{
    if ($response['code'] !== 200
        || preg_match('/name="_csrf" value="([^"]+)"/', $response['body'], $parts) !== 1) {
        throw new RuntimeException('Expected a working form with a CSRF token.');
    }
    return $parts[1];
}

function image_multi_login($client, string $base, string $username): void
{
    $token = image_multi_token(image_multi_request($client, $base, '/login'));
    $login = image_multi_request($client, $base, '/login', 'POST', [
        '_csrf' => $token,
        'username' => $username,
        'password' => 'CampusTrace!Demo2026',
    ]);
    if ($login['code'] !== 302 || $login['path'] !== '/') {
        throw new RuntimeException('Could not log in with the seeded demo user ' . $username);
    }
}

function image_multi_upload(
    $client,
    string $base,
    int $postId,
    array $files
): array {
    $body = [];
    foreach (array_values($files) as $index => $file) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
        $body['images[' . $index . ']'] = new CURLFile(
            $file['path'],
            $mime,
            $file['name']
        );
    }

    return image_multi_request(
        $client,
        $base,
        '/posts/' . $postId . '/image',
        'POST',
        $body
    );
}

function image_multi_sql(string $sql, string $types, ...$values): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    return $stmt;
}

function image_multi_gallery(int $postId): array
{
    $stmt = image_multi_sql(
        'SELECT id, post_id, storage_name, mime_type, width, height
         FROM post_images
         WHERE post_id = ?
         ORDER BY created_at ASC, id ASC',
        'i',
        $postId
    );
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function image_multi_post(int $postId): ?array
{
    $stmt = image_multi_sql(
        'SELECT id, status, current_image_id FROM posts WHERE id = ? LIMIT 1',
        'i',
        $postId
    );
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function image_multi_write_image(string $path, string $format): void
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

function image_multi_create_post(int $ownerId, string $tag): int
{
    $stmt = image_multi_sql(
        "INSERT INTO posts (owner_id, post_type, title, description, category, location)
         VALUES (?, 'found', ?, 'Image test fixture', '测试', '测试地点')",
        'is',
        $ownerId,
        '多图片专项测试 ' . $tag
    );
    $postId = (int) $stmt->insert_id;
    $stmt->close();
    return $postId;
}

function image_multi_cleanup_post(int $postId): void
{
    $gallery = image_multi_gallery($postId);
    foreach ($gallery as $image) {
        $path = rtrim((string) app_config('storage_dir'), '/\\')
            . DIRECTORY_SEPARATOR . basename($image['storage_name']);
        if (is_file($path)) {
            unlink($path);
        }
    }
    image_multi_sql('DELETE FROM posts WHERE id = ?', 'i', $postId)->close();
}

$postId = null;
$failurePostId = null;
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
    image_multi_write_image($validPng, 'png');
    image_multi_write_image($validJpeg, 'jpg');
    if (file_put_contents($fakePng, 'Baseline extension-only test payload') === false
        || file_put_contents($invalid, 'Not an image') === false) {
        throw new RuntimeException('Could not create the invalid image fixtures.');
    }
    $fixtureNames = [$validPng, $validJpeg, $fakePng, $invalid];

    $postId = image_multi_create_post((int) $ownerRow['id'], $tag);
    $failurePostId = image_multi_create_post((int) $ownerRow['id'], $tag . '-failure');

    $sessions = [
        'owner' => image_multi_client(),
        'other' => image_multi_client(),
        'guest' => image_multi_client(),
    ];
    image_multi_login($sessions['owner'], $base, 'alice');
    image_multi_login($sessions['other'], $base, 'bob');

    $guestUpload = image_multi_upload($sessions['guest'], $base, $postId, [
        ['path' => $validPng, 'name' => 'guest.png'],
    ]);
    image_multi_expect(
        $guestUpload['code'] === 302 && $guestUpload['path'] === '/login',
        'Anonymous multi-image upload is redirected to login'
    );

    $allInvalid = image_multi_upload($sessions['owner'], $base, $postId, [
        ['path' => $invalid, 'name' => 'first.txt'],
        ['path' => $invalid, 'name' => 'second.txt'],
    ]);
    image_multi_expect(
        $allInvalid['code'] === 400
        && count(image_multi_gallery($postId)) === 0
        && image_multi_post($postId)['current_image_id'] === null,
        'An all-invalid batch changes neither gallery nor cover'
    );

    // No CSRF field is sent: Baseline accepts the upload and records R08.
    $firstBatch = image_multi_upload($sessions['owner'], $base, $postId, [
        ['path' => $validPng, 'name' => 'first.png'],
        ['path' => $validJpeg, 'name' => 'second.jpg'],
        ['path' => $invalid, 'name' => 'third.txt'],
    ]);
    $gallery = image_multi_gallery($postId);
    $post = image_multi_post($postId);
    image_multi_expect(
        $firstBatch['code'] === 302 && $firstBatch['path'] === '/posts/' . $postId,
        'IMG-01 mixed batch uses partial success without CSRF'
    );
    image_multi_expect(
        count($gallery) === 2
        && (int) $post['current_image_id'] === (int) $gallery[0]['id'],
        'IMG-01 first successful image becomes the default cover'
    );
    image_multi_expect(
        is_file(app_config('storage_dir') . '/' . $gallery[0]['storage_name'])
        && is_file(app_config('storage_dir') . '/' . $gallery[1]['storage_name']),
        'IMG-01 every successful image has its own stored file'
    );

    $firstId = (int) $gallery[0]['id'];
    $secondId = (int) $gallery[1]['id'];
    $detail = image_multi_request($sessions['guest'], $base, '/posts/' . $postId);
    image_multi_expect(
        $detail['code'] === 200
        && strpos($detail['body'], '/images/' . $firstId) !== false
        && strpos($detail['body'], '/images/' . $secondId) !== false,
        'IMG-01 guest detail renders all gallery images'
    );
    $ownerDetail = image_multi_request($sessions['owner'], $base, '/posts/' . $postId);
    image_multi_expect(
        $ownerDetail['code'] === 200
        && strpos($ownerDetail['body'], 'name="images[]"') !== false
        && strpos($ownerDetail['body'], 'multiple') !== false,
        'IMG-01 owner detail renders the multi-file upload input'
    );
    image_multi_expect(
        strpos($detail['body'], 'Baseline') === false
        && strpos($detail['body'], 'R04') === false,
        'Image UI does not expose Baseline or risk identifiers'
    );
    $home = image_multi_request($sessions['guest'], $base, '/');
    image_multi_expect(
        $home['code'] === 200 && strpos($home['body'], '/images/' . $firstId) !== false,
        'Post list renders the current cover image'
    );

    $original = image_multi_request($sessions['guest'], $base, '/images/' . $firstId);
    image_multi_expect(
        $original['code'] === 200
        && $original['type'] === 'image/png'
        && hash_equals(
            hash('sha256', (string) file_get_contents($validPng)),
            hash('sha256', $original['body'])
        ),
        'R02 reproduced: image endpoint returns the original bytes'
    );

    $fakeBatch = image_multi_upload($sessions['owner'], $base, $postId, [
        ['path' => $fakePng, 'name' => 'renamed-text.png'],
        ['path' => $invalid, 'name' => 'ignored.txt'],
    ]);
    $gallery = image_multi_gallery($postId);
    $post = image_multi_post($postId);
    $fake = $gallery[2];
    image_multi_expect(
        $fakeBatch['code'] === 302
        && count($gallery) === 3
        && (int) $post['current_image_id'] === $firstId,
        'IMG-02 appending does not change the existing cover'
    );
    image_multi_expect(
        (int) $fake['width'] === 0 && (int) $fake['height'] === 0,
        'R01 reproduced: renamed text is accepted as an image'
    );

    $fakeRead = image_multi_request($sessions['guest'], $base, '/images/' . (int) $fake['id']);
    image_multi_expect(
        $fakeRead['code'] === 200
        && $fakeRead['type'] === 'image/png'
        && $fakeRead['body'] === 'Baseline extension-only test payload',
        'R01 reproduced: fake image bytes are publicly served'
    );

    $crossOwnerUpload = image_multi_upload($sessions['other'], $base, $postId, [
        ['path' => $validPng, 'name' => 'bob.png'],
    ]);
    image_multi_expect(
        $crossOwnerUpload['code'] === 302
        && count(image_multi_gallery($postId)) === 4,
        'R05 reproduced: another active user can append an image'
    );

    $gallery = image_multi_gallery($postId);
    $nonCover = $gallery[1];
    $nonCoverPath = app_config('storage_dir') . '/' . $nonCover['storage_name'];
    $otherDelete = image_multi_request(
        $sessions['other'],
        $base,
        '/posts/' . $postId . '/images/' . (int) $nonCover['id'] . '/delete',
        'POST'
    );
    image_multi_expect($otherDelete['code'] === 403, 'Only the post owner can delete an image');

    // No CSRF field is sent: Baseline accepts the delete and records R08.
    $ownerDelete = image_multi_request(
        $sessions['owner'],
        $base,
        '/posts/' . $postId . '/images/' . (int) $nonCover['id'] . '/delete',
        'POST'
    );
    image_multi_expect($ownerDelete['code'] === 302, 'Owner delete request redirects');
    clearstatcache(true, $nonCoverPath);
    $nonCoverCleaned = !is_file($nonCoverPath);
    $cleanupNoticeSeen = true;
    if (!$nonCoverCleaned) {
        $afterDeleteDetail = image_multi_request($sessions['owner'], $base, '/posts/' . $postId);
        $cleanupNoticeSeen = strpos($afterDeleteDetail['body'], '物理文件清理未完成') !== false;
    }
    image_multi_expect(
        $nonCoverCleaned || $cleanupNoticeSeen,
        'Deleting a non-cover image handles file cleanup failure'
    );
    $deletedRead = image_multi_request(
        $sessions['guest'],
        $base,
        '/images/' . (int) $nonCover['id']
    );
    image_multi_expect(
        $deletedRead['code'] === 404,
        'Deleting a non-cover image removes its metadata and old URL'
    );
    image_multi_expect(
        (int) image_multi_post($postId)['current_image_id'] === $firstId,
        'Deleting a non-cover image keeps the current cover'
    );

    $coverPath = app_config('storage_dir') . '/' . $gallery[0]['storage_name'];
    $coverDelete = image_multi_request(
        $sessions['owner'],
        $base,
        '/posts/' . $postId . '/images/' . $firstId . '/delete',
        'POST'
    );
    $afterCoverDelete = image_multi_gallery($postId);
    clearstatcache(true, $coverPath);
    $coverCleaned = !is_file($coverPath);
    $coverCleanupNoticeSeen = true;
    if (!$coverCleaned) {
        $afterCoverDetail = image_multi_request($sessions['owner'], $base, '/posts/' . $postId);
        $coverCleanupNoticeSeen = strpos(
            $afterCoverDetail['body'],
            '物理文件清理未完成'
        ) !== false;
    }
    image_multi_expect(
        $coverDelete['code'] === 302
        && ($coverCleaned || $coverCleanupNoticeSeen)
        && (int) image_multi_post($postId)['current_image_id'] === (int) $afterCoverDelete[0]['id'],
        'Deleting the cover promotes the next image and handles cleanup failure'
    );

    image_multi_sql("UPDATE posts SET status = 'removed' WHERE id = ?", 'i', $postId)->close();
    $removedImageId = (int) $afterCoverDelete[0]['id'];
    image_multi_expect(
        image_multi_request($sessions['guest'], $base, '/posts/' . $postId)['code'] === 404,
        'Removed post detail is no longer public'
    );
    image_multi_expect(
        image_multi_request($sessions['guest'], $base, '/images/' . $removedImageId)['code'] === 200,
        'R04 reproduced: removed post images remain directly readable'
    );

    image_multi_sql("UPDATE posts SET status = 'open' WHERE id = ?", 'i', $postId)->close();
    foreach (image_multi_gallery($postId) as $remainingImage) {
        image_multi_request(
            $sessions['owner'],
            $base,
            '/posts/' . $postId . '/images/' . (int) $remainingImage['id'] . '/delete',
            'POST'
        );
    }
    image_multi_expect(
        count(image_multi_gallery($postId)) === 0
        && image_multi_post($postId)['current_image_id'] === null,
        'Deleting the final image clears the gallery and cover'
    );

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
    $failureResult = $failingImages->uploadBatchForPost($failurePostId, [[
        'name' => 'failure.png',
        'error' => UPLOAD_ERR_OK,
        'tmp_name' => $validPng,
        'size' => filesize($validPng),
    ]]);
    $failureGallery = image_multi_gallery($failurePostId);
    $failurePost = image_multi_post($failurePostId);
    $failedImagePath = app_config('storage_dir') . '/' . $failureGallery[0]['storage_name'];
    image_multi_expect(
        count($failureResult['successes']) === 0
        && count($failureResult['failures']) === 1
        && count($failureGallery) === 1
        && (int) $failurePost['current_image_id'] === (int) $failureGallery[0]['id']
        && !is_file($failedImagePath),
        'FILE-01 R09 reproduced: failed file move leaves metadata and cover behind'
    );
} catch (Throwable $error) {
    fwrite(STDERR, '[FAIL] ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach ($sessions as $session) {
        curl_close($session);
    }
    foreach ([$postId, $failurePostId] as $cleanupPostId) {
        if ($cleanupPostId !== null) {
            try {
                image_multi_cleanup_post($cleanupPostId);
            } catch (Throwable $cleanupError) {
                fwrite(STDERR, '[WARN] Image test cleanup failed: ' . $cleanupError->getMessage() . "\n");
            }
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
