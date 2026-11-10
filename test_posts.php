<?php

declare(strict_types=1);

/**
 * CampusTrace member 2 HTTP regression test.
 *
 * Usage:
 *   php tests/security/test_posts.php http://192.168.111.148:8080
 *
 * The baseline seed data is required. The script creates clearly named test
 * records and intentionally removes one at the end to verify the R05/R08/R10
 * teaching gaps.
 */

final class TestHttpClient
{
    private $baseUrl;
    private $cookies = [];
    private $lastResponse = [];

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $fields): array
    {
        return $this->request('POST', $path, $fields);
    }

    private function request(string $method, string $path, ?array $fields = null): array
    {
        $headers = [
            'Accept: text/html',
            'User-Agent: CampusTrace-Member2-Test',
            'Connection: close',
        ];
        if ($this->cookies) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            $headers[] = 'Cookie: ' . implode('; ', $pairs);
        }

        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 15,
            ],
        ];
        if ($fields !== null) {
            $options['http']['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
            $options['http']['content'] = http_build_query($fields);
        }

        $http_response_header = [];
        $body = @file_get_contents(
            $this->baseUrl . $path,
            false,
            stream_context_create($options)
        );
        if ($body === false) {
            throw new RuntimeException('HTTP request failed: ' . $method . ' ' . $path);
        }

        $status = 0;
        $responseHeaders = [];
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches)) {
                $status = (int) $matches[1];
                $responseHeaders = [];
                continue;
            }

            $separator = strpos($line, ':');
            if ($separator !== false) {
                $name = strtolower(trim(substr($line, 0, $separator)));
                $value = trim(substr($line, $separator + 1));
                $responseHeaders[$name][] = $value;
                if ($name === 'set-cookie') {
                    $cookie = explode(';', $value, 2)[0];
                    $cookieParts = explode('=', $cookie, 2);
                    if (count($cookieParts) === 2) {
                        $this->cookies[$cookieParts[0]] = $cookieParts[1];
                    }
                }
            }
        }

        $this->lastResponse = [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $body,
        ];

        return $this->lastResponse;
    }
}

$baseUrl = isset($argv[1]) ? $argv[1] : getenv('CAMPUS_BASE_URL');
if (!is_string($baseUrl) || $baseUrl === '') {
    $baseUrl = 'http://192.168.111.148:8080';
}
$password = 'CampusTrace!Demo2026';
$stamp = date('YmdHis') . '-' . substr(sha1(uniqid('', true)), 0, 8);
$passed = 0;
$failed = 0;
$postId = null;

function check(bool $condition, string $name, string $detail = ''): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo '[PASS] ' . $name . PHP_EOL;
        return;
    }

    $failed++;
    echo '[FAIL] ' . $name;
    if ($detail !== '') {
        echo ' - ' . $detail;
    }
    echo PHP_EOL;
}

function response_header(array $response, string $name): string
{
    return $response['headers'][strtolower($name)][0] ?? '';
}

function login(TestHttpClient $client, string $username, string $password): array
{
    $loginPage = $client->get('/login');
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $loginPage['body'], $matches)) {
        throw new RuntimeException('Cannot find CSRF token on login page.');
    }

    return $client->post('/login', [
        '_csrf' => $matches[1],
        'username' => $username,
        'password' => $password,
    ]);
}

try {
    $alice = new TestHttpClient($baseUrl);
    $bob = new TestHttpClient($baseUrl);

    check(response_header(login($alice, 'alice', $password), 'Location') === '/', '登录 alice');
    check(response_header(login($bob, 'bob', $password), 'Location') === '/', '登录 bob');

    $publicIndex = $alice->get('/');
    check($publicIndex['status'] === 200, 'GET / 可访问', 'status=' . $publicIndex['status']);

    $createTitle = 'M2-SEARCH-' . $stamp;
    $r08Description = 'R08 标记 <mark data-r08="' . $stamp . '">原样输出</mark>';
    $create = $alice->post('/posts/new', [
        'post_type' => 'lost',
        'title' => $createTitle,
        'description' => $r08Description,
        'category' => '成员2自动测试',
        'location' => '测试地点-' . $stamp,
    ]);
    check($create['status'] === 302, 'POST-01 发布帖子且不提交 CSRF（R08）', 'status=' . $create['status']);
    if (!preg_match('#^/posts/(\d+)$#', response_header($create, 'Location'), $matches)) {
        throw new RuntimeException('Create response did not redirect to a post id.');
    }
    $postId = (int) $matches[1];

    $detail = $alice->get('/posts/' . $postId);
    check(
        $detail['status'] === 200
        && strpos($detail['body'], $createTitle) !== false
        && strpos($detail['body'], '<mark data-r08="' . $stamp . '">') !== false,
        'POST-01/SEC-01 详情可见且描述按 HTML 原样输出（R08）',
        'status=' . $detail['status']
    );

    $search = $alice->get('/?q=' . rawurlencode($stamp) . '&type=lost&status=open');
    check(
        $search['status'] === 200
        && strpos($search['body'], $createTitle) !== false,
        'POST-01 关键词、类型和状态筛选',
        'status=' . $search['status']
    );

    $ownerEditTitle = $createTitle . '-OWNER';
    $ownerEdit = $alice->post('/posts/' . $postId . '/edit', [
        'post_type' => 'lost',
        'title' => $ownerEditTitle,
        'description' => $r08Description,
        'category' => '成员2自动测试',
        'location' => '测试地点-' . $stamp,
    ]);
    check($ownerEdit['status'] === 302, 'POST-02 发布者编辑本人帖子', 'status=' . $ownerEdit['status']);

    $crossEditTitle = $createTitle . '-BOB';
    $crossEdit = $bob->post('/posts/' . $postId . '/edit', [
        'post_type' => 'lost',
        'title' => $crossEditTitle,
        'description' => $r08Description,
        'category' => '成员2自动测试',
        'location' => '测试地点-' . $stamp,
    ]);
    $crossEditDetail = $bob->get('/posts/' . $postId);
    check(
        $crossEdit['status'] === 302
        && strpos($crossEditDetail['body'], $crossEditTitle) !== false,
        'POST-03 用户乙编辑用户甲帖子被 baseline 放行（R05）',
        'status=' . $crossEdit['status']
    );

    $otherResolve = $bob->post('/posts/' . $postId . '/resolve', []);
    check($otherResolve['status'] === 403, '结帖拒绝非发布者', 'status=' . $otherResolve['status']);

    $ownerResolve = $alice->post('/posts/' . $postId . '/resolve', []);
    $resolvedDetail = $alice->get('/posts/' . $postId);
    check(
        $ownerResolve['status'] === 302
        && strpos($resolvedDetail['body'], '已解决') !== false,
        '发布者标记已解决并保持公开可见',
        'status=' . $ownerResolve['status']
    );

    $reportReason = 'REPORT-' . $stamp;
    $firstReport = $bob->post('/posts/' . $postId . '/reports', ['reason' => $reportReason]);
    $secondReport = $bob->post('/posts/' . $postId . '/reports', ['reason' => $reportReason]);
    check(
        $firstReport['status'] === 302 && $secondReport['status'] === 302,
        'R10 重复举报被 baseline 接受',
        'statuses=' . $firstReport['status'] . ',' . $secondReport['status']
    );

    $ordinaryAdmin = $bob->get('/admin/reports');
    check(
        $ordinaryAdmin['status'] === 200
        && strpos($ordinaryAdmin['body'], 'Baseline R10') !== false,
        'ADMIN-01 普通用户可访问举报审核入口（R10）',
        'status=' . $ordinaryAdmin['status']
    );

    $ordinaryRemove = $bob->post('/admin/posts/' . $postId . '/remove', []);
    $removedDetail = $bob->get('/posts/' . $postId);
    $removedSearch = $bob->get('/?q=' . rawurlencode($stamp));
    check(
        $ordinaryRemove['status'] === 302 && $removedDetail['status'] === 404,
        'ADMIN-01 普通用户下架成功且详情返回 404（R10）',
        'remove=' . $ordinaryRemove['status'] . ',detail=' . $removedDetail['status']
    );
    check(
        $removedSearch['status'] === 200
        && strpos($removedSearch['body'], $crossEditTitle) === false,
        '下架帖子不再出现在公开列表',
        'status=' . $removedSearch['status']
    );

    $invalid = $alice->post('/posts/new', [
        'post_type' => 'invalid',
        'title' => '',
        'description' => '',
        'category' => '',
        'location' => '',
    ]);
    check($invalid['status'] === 422, '非法发帖输入返回 422', 'status=' . $invalid['status']);

    echo PHP_EOL;
    echo 'Result: ' . $passed . ' passed, ' . $failed . ' failed.' . PHP_EOL;
    echo 'Base URL: ' . $baseUrl . PHP_EOL;
    echo 'Test post id: ' . (int) $postId . PHP_EOL;
    exit($failed === 0 ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Test aborted: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}
