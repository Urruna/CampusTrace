<?php

declare(strict_types=1);

final class AuthController
{
    public function showLogin(): void
    {
        if (current_user() !== null) {
            redirect('/');
        }

        render('auth/login', ['title' => '登录']);
    }

    public function login(): void
    {
        if (current_user() !== null) {
            redirect('/');
        }

        verify_csrf();
        $username = request_string($_POST, 'username') ?? '';
        $password = request_string($_POST, 'password') ?? '';

        if ($username === '' || $password === '') {
            http_response_code(422);
            render('auth/login', [
                'title' => '登录',
                'error' => '用户名和密码不能为空。',
                'username' => $username,
            ]);
            return;
        }

        if (!login_user($username, $password)) {
            http_response_code(401);
            render('auth/login', [
                'title' => '登录',
                'error' => '用户名、密码错误，或账号尚未激活。',
                'username' => $username,
            ]);
            return;
        }

        set_flash('success', '登录成功。');
        redirect('/');
    }

    public function logout(): void
    {
        verify_csrf();
        logout_user();
        redirect('/login');
    }
}

