<?php

declare(strict_types=1);

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, username, role, contact_text, activated_at
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $id = (int) $userId;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        $user = $row;
    }

    return $user;
}

function login_user(string $username, string $password): bool
{
    $stmt = db()->prepare(
        'SELECT id, password_hash, activated_at
         FROM users
         WHERE username = ?
         LIMIT 1'
    );
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($password, (string) $row['password_hash'])) {
        return false;
    }

    if ($row['activated_at'] === null) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    unset($_SESSION['_csrf_token']);

    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }
    session_destroy();
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        set_flash('error', '请先登录。');
        redirect('/login');
    }

    return $user;
}

function require_active_user(): array
{
    $user = require_login();
    if ($user['activated_at'] === null) {
        http_response_code(403);
        render('errors/403', [
            'title' => '账号未激活',
            'message' => '当前账号不能执行该操作。',
        ]);
        exit;
    }

    return $user;
}

function require_admin(): array
{
    $user = require_active_user();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        render('errors/403', [
            'title' => '权限不足',
            'message' => '该操作仅限管理员。',
        ]);
        exit;
    }

    return $user;
}

