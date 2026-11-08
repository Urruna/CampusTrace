<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once CAMPUS_APP . '/models/PostRepository.php';
require_once CAMPUS_APP . '/controllers/AuthController.php';
require_once CAMPUS_APP . '/controllers/PostController.php';
require_once CAMPUS_APP . '/controllers/HealthController.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rtrim($path, '/') : '/';
$path = $path === '' ? '/' : $path;

$auth = new AuthController();
$healthController = new HealthController();

function module_pending(string $owner, string $message): void
{
    http_response_code(501);
    render('errors/501', [
        'title' => '模块待实现',
        'message' => $owner . '：' . $message,
    ]);
}

try {
    if ($method === 'GET' && $path === '/') {
        $postController = new PostController(new PostRepository(db()));
        $postController->index();
        return;
    }

    if ($method === 'GET' && $path === '/health') {
        $healthController->index();
        return;
    }

    if ($method === 'GET' && $path === '/login') {
        $auth->showLogin();
        return;
    }

    if ($method === 'POST' && $path === '/login') {
        $auth->login();
        return;
    }

    if ($method === 'POST' && $path === '/logout') {
        $auth->logout();
        return;
    }

    if (preg_match('#^/posts/(\d+)$#', $path, $matches) && $method === 'GET') {
        $postController = new PostController(new PostRepository(db()));
        $postController->show((int) $matches[1]);
        return;
    }

    $pendingRoutes = [
        'GET /register' => ['成员 1', '注册页面'],
        'POST /register' => ['成员 1', '注册处理'],
        'GET /activate' => ['成员 1', '邀请码激活页面'],
        'POST /activate' => ['成员 1', '邀请码激活处理'],
        'GET /posts/new' => ['成员 2', '发布页面'],
        'POST /posts/new' => ['成员 2', '发布处理'],
        'POST /posts/{id}/resolve' => ['成员 2', '结帖处理'],
        'GET /admin/reports' => ['成员 2', '举报列表'],
        'POST /admin/posts/{id}/remove' => ['成员 2', '管理员下架'],
        'POST /posts/{id}/image' => ['成员 3', '图片上传'],
        'GET /images/{id}' => ['成员 3', '受控图片读取'],
        'POST /posts/{id}/claims' => ['成员 4', '认领申请'],
        'GET /claims/{id}' => ['成员 4', '认领详情'],
        'POST /claims/{id}/approve' => ['成员 4', '批准认领'],
        'POST /claims/{id}/reject' => ['成员 4', '拒绝认领'],
        'POST /claims/{id}/revoke' => ['成员 4', '撤回授权'],
        'GET /claims/{id}/contact' => ['成员 4', '联系方式读取'],
        'POST /posts/{id}/reports' => ['成员 2', '举报提交'],
    ];
    $routeKey = $method . ' ' . preg_replace('#/\d+#', '/{id}', $path);
    if (isset($pendingRoutes[$routeKey])) {
        module_pending($pendingRoutes[$routeKey][0], $pendingRoutes[$routeKey][1]);
        return;
    }

    if (preg_match('#^/posts/(\d+)/(edit|claims|image|resolve|reports)$#', $path, $matches)) {
        $owners = [
            'edit' => '成员 2',
            'resolve' => '成员 2',
            'reports' => '成员 2',
            'image' => '成员 3',
            'claims' => '成员 4',
        ];
        module_pending($owners[$matches[2]], '该帖子子功能尚未接入');
        return;
    }

    if (preg_match('#^/claims/(\d+)(/(approve|reject|revoke|contact))?$#', $path, $matches)) {
        $action = $matches[3] ?? 'detail';
        $pathOwner = in_array($action, ['contact'], true) ? '成员 4' : '成员 4';
        module_pending($pathOwner, '认领模块尚未接入');
        return;
    }

    if (preg_match('#^/admin/posts/(\d+)/remove$#', $path)) {
        module_pending('成员 2', '管理员下架尚未接入');
        return;
    }

    http_response_code(404);
    render('errors/404', [
        'title' => '页面不存在',
        'message' => '请求的路径不存在。',
    ]);
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    render('errors/500', [
        'title' => '处理失败',
        'message' => '服务器处理请求时发生错误，请检查数据库和运行环境。',
    ]);
}

