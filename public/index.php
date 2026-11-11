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

    if ($method === 'GET' && $path === '/register') {
        $auth->showRegister();
        return;
    }

    if ($method === 'POST' && $path === '/register') {
        $auth->register();
        return;
    }

    if ($method === 'GET' && $path === '/activate') {
        $auth->showActivate();
        return;
    }

    if ($method === 'POST' && $path === '/activate') {
        $auth->activate();
        return;
    }

    if ($method === 'GET' && $path === '/account/contact') {
        $auth->showContact();
        return;
    }

    if ($method === 'POST' && $path === '/account/contact') {
        $auth->updateContact();
        return;
    }

    if (preg_match('#^/posts/(\d+)$#', $path, $matches) && $method === 'GET') {
        $postController = new PostController(new PostRepository(db()));
        $postController->show((int) $matches[1]);
        return;
    }

    $postClaimsRoute = preg_match('#^/posts/(\d+)/claims$#', $path, $postClaim);
    $claimRoute = preg_match('#^/claims/(\d+)(?:/(approve|reject|revoke|contact))?$#', $path, $claimMatch);
    if ($postClaimsRoute || $claimRoute) {
        require_once CAMPUS_APP . '/models/Claim.php';
        require_once CAMPUS_APP . '/controllers/ClaimController.php';
        $controller = new ClaimController(new Claim(db()));
        $action = $claimMatch[2] ?? 'detail';
        $allowed = $postClaimsRoute ? ['GET', 'POST'] : (in_array($action, ['detail', 'contact'], true) ? ['GET'] : ['POST']);
        if (!in_array($method, $allowed, true)) {
            header('Allow: ' . implode(', ', $allowed));
            throw new ClaimProblem('请使用页面提供的操作提交申请或审批。', 405);
        }
        if ($postClaimsRoute) {
            $controller->postClaims((int) $postClaim[1], $method);
        } elseif ($action === 'detail') {
            $controller->detail((int) $claimMatch[1]);
        } elseif ($action === 'contact') {
            $controller->contact((int) $claimMatch[1]);
        } else {
            $controller->decide((int) $claimMatch[1], $action);
        }
        return;
    }

    $pendingRoutes = [
        'GET /posts/new' => ['成员 2', '发布页面'],
        'POST /posts/new' => ['成员 2', '发布处理'],
        'POST /posts/{id}/resolve' => ['成员 2', '结帖处理'],
        'GET /admin/reports' => ['成员 2', '举报列表'],
        'POST /admin/posts/{id}/remove' => ['成员 2', '管理员下架'],
        'POST /posts/{id}/image' => ['成员 3', '图片上传'],
        'GET /images/{id}' => ['成员 3', '受控图片读取'],
        'POST /posts/{id}/reports' => ['成员 2', '举报提交'],
    ];
    $routeKey = $method . ' ' . preg_replace('#/\d+#', '/{id}', $path);
    if (isset($pendingRoutes[$routeKey])) {
        module_pending($pendingRoutes[$routeKey][0], $pendingRoutes[$routeKey][1]);
        return;
    }

    if (preg_match('#^/posts/(\d+)/(edit|image|resolve|reports)$#', $path, $matches)) {
        $owners = [
            'edit' => '成员 2',
            'resolve' => '成员 2',
            'reports' => '成员 2',
            'image' => '成员 3',
        ];
        module_pending($owners[$matches[2]], '该帖子子功能尚未接入');
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
} catch (ClaimProblem $e) {
    $status = $e->getCode();
    http_response_code($status);
    $view = in_array($status, [403, 404], true) ? 'errors/' . $status : 'errors/400';
    render($view, ['title' => '无法完成认领操作', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    render('errors/500', [
        'title' => '处理失败',
        'message' => '服务器处理请求时发生错误，请检查数据库和运行环境。',
    ]);
}

