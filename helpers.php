<?php

declare(strict_types=1);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function request_string(array $source, string $key): ?string
{
    return isset($source[$key]) && is_string($source[$key]) ? trim($source[$key]) : null;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pull_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return is_array($messages) ? $messages : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(24));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = request_string($_POST, '_csrf');
    $expected = $_SESSION['_csrf_token'] ?? null;

    if (!is_string($submitted) || !is_string($expected) || !hash_equals($expected, $submitted)) {
        http_response_code(400);
        render('errors/400', [
            'title' => '请求校验失败',
            'message' => '表单已过期或请求来源无效，请返回后重新提交。',
        ]);
        exit;
    }
}

function render(string $view, array $data = []): void
{
    $viewFile = CAMPUS_APP . '/views/' . $view . '.php';
    if (!is_file($viewFile)) {
        throw new RuntimeException('View not found: ' . $view);
    }

    extract($data, EXTR_SKIP);
    $title = $data['title'] ?? app_config('app_name');
    $currentUser = current_user();
    $flashes = pull_flashes();

    ob_start();
    require $viewFile;
    $content = ob_get_clean();

    require CAMPUS_APP . '/views/layout.php';
}

function post_type_label(string $type): string
{
    return $type === 'lost' ? '寻物' : '招领';
}

function post_status_label(string $status): string
{
    $labels = [
        'open' => '开放',
        'resolved' => '已解决',
        'removed' => '已下架',
    ];

    return $labels[$status] ?? $status;
}

function report_status_label(string $status): string
{
    $labels = [
        'pending' => '待处理',
        'resolved' => '已处理',
        'dismissed' => '已驳回',
    ];

    return $labels[$status] ?? $status;
}

