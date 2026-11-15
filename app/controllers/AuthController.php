<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/AccountService.php';

final class AuthController
{
    private function accounts(): AccountService
    {
        return new AccountService(db());
    }

    public function showLogin(): void
    {
        if (current_user() !== null) {
            redirect('/');
        }

        render('auth/login', [
            'title' => '登录',
            'backUrl' => '/',
            'backLabel' => '返回失物大厅',
        ]);
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
                'backUrl' => '/',
                'backLabel' => '返回失物大厅',
            ]);
            return;
        }

        if (!login_user($username, $password)) {
            http_response_code(401);
            render('auth/login', [
                'title' => '登录',
                'error' => '用户名、密码错误，或账号尚未激活。',
                'username' => $username,
                'backUrl' => '/',
                'backLabel' => '返回失物大厅',
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

    public function showRegister(): void
    {
        if (current_user() !== null) {
            redirect('/');
        }

        render('auth/register', [
            'title' => '注册账号',
            'backUrl' => '/login',
            'backLabel' => '返回登录',
        ]);
    }

    public function register(): void
    {
        if (current_user() !== null) {
            redirect('/');
        }

        verify_csrf();
        $username = request_string($_POST, 'username') ?? '';
        $password = request_string($_POST, 'password') ?? '';
        $passwordConfirmation = request_string($_POST, 'password_confirmation') ?? '';
        $contactText = request_string($_POST, 'contact_text') ?? '';

        try {
            $this->accounts()->register($username, $password, $passwordConfirmation, $contactText);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            render('auth/register', [
                'title' => '注册账号',
                'error' => $e->getMessage(),
                'username' => $username,
                'contact_text' => $contactText,
                'backUrl' => '/login',
                'backLabel' => '返回登录',
            ]);
            return;
        } catch (Throwable $e) {
            error_log((string) $e);
            http_response_code(500);
            render('errors/500', [
                'title' => '注册失败',
                'message' => '服务器无法完成注册，请稍后再试。',
                'backUrl' => '/register',
                'backLabel' => '返回注册',
            ]);
            return;
        }

        $_SESSION['_pending_activation_username'] = trim($username);
        set_flash('success', '账号注册成功，请使用邀请码激活。');
        redirect('/activate');
    }

    public function showActivate(): void
    {
        if (current_user() !== null) {
            redirect('/');
        }

        render('auth/activate', [
            'title' => '激活账号',
            'username' => $_SESSION['_pending_activation_username'] ?? '',
            'backUrl' => '/login',
            'backLabel' => '返回登录',
        ]);
    }

    public function activate(): void
    {
        if (current_user() !== null) {
            redirect('/');
        }

        verify_csrf();
        $username = request_string($_POST, 'username') ?? '';
        $inviteCode = request_string($_POST, 'invite_code') ?? '';

        try {
            $this->accounts()->activate($username, $inviteCode);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            render('auth/activate', [
                'title' => '激活账号',
                'error' => $e->getMessage(),
                'username' => $username,
                'backUrl' => '/login',
                'backLabel' => '返回登录',
            ]);
            return;
        } catch (Throwable $e) {
            error_log((string) $e);
            http_response_code(500);
            render('errors/500', [
                'title' => '激活失败',
                'message' => '服务器无法完成账号激活，请稍后再试。',
                'backUrl' => '/activate',
                'backLabel' => '返回激活',
            ]);
            return;
        }

        unset($_SESSION['_pending_activation_username']);
        set_flash('success', '账号已经激活，请登录。');
        redirect('/login');
    }

    public function showContact(): void
    {
        $user = require_active_user();
        render('account/contact', [
            'title' => '联系方式',
            'user' => $user,
            'backUrl' => '/',
            'backLabel' => '返回失物大厅',
        ]);
    }

    public function updateContact(): void
    {
        $user = require_active_user();
        verify_csrf();
        $contactText = request_string($_POST, 'contact_text') ?? '';

        try {
            $this->accounts()->updateContact((int) $user['id'], $contactText);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            render('account/contact', [
                'title' => '联系方式',
                'user' => array_merge($user, ['contact_text' => $contactText]),
                'error' => $e->getMessage(),
                'backUrl' => '/',
                'backLabel' => '返回失物大厅',
            ]);
            return;
        } catch (Throwable $e) {
            error_log((string) $e);
            http_response_code(500);
            render('errors/500', [
                'title' => '联系方式更新失败',
                'message' => '服务器无法更新联系方式，请稍后再试。',
                'backUrl' => '/account/contact',
                'backLabel' => '返回联系方式',
            ]);
            return;
        }

        set_flash('success', '联系方式已更新。');
        redirect('/account/contact');
    }
}

