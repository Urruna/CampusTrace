<?php

declare(strict_types=1);
?>
<section class="auth-layout">
    <div class="panel auth-copy">
        <p class="eyebrow">CampusTrace</p>
        <h1>找回物品，也保护信息。</h1>
        <p>登录后可以管理本人发布的记录和模拟联系方式。认领线索与联系方式只在获得授权后开放。</p>
    </div>

    <div class="panel auth-form">
        <h2>账号登录</h2>
        <?php if (!empty($error)): ?>
            <div class="notice notice-error"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/login">
            <?= csrf_field() ?>
            <label class="form-field">
                <span>用户名</span>
                <input type="text" name="username" maxlength="50" autocomplete="username"
                       value="<?= h($username ?? '') ?>" required>
            </label>
            <label class="form-field">
                <span>密码</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="button button-primary">登录</button>
        </form>
        <p class="hint">
            忘记激活？<a href="/activate">使用邀请码激活</a> ·
            <a href="/register">注册新账号</a>
        </p>
    </div>
</section>

