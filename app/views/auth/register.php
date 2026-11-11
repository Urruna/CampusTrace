<?php

declare(strict_types=1);
?>
<section class="auth-layout">
    <div class="panel auth-copy">
        <p class="eyebrow">Create account</p>
        <h1>先建立可信身份。</h1>
        <p>注册后账号仍为未激活状态，需要使用课程模拟邀请码激活，才能发布、认领或修改记录。</p>
    </div>

    <div class="panel auth-form">
        <h2>注册账号</h2>
        <?php if (!empty($error)): ?>
            <div class="notice notice-error"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/register">
            <?= csrf_field() ?>
            <label class="form-field">
                <span>用户名</span>
                <input type="text" name="username" minlength="3" maxlength="30"
                       pattern="[A-Za-z0-9_]{3,30}" autocomplete="username"
                       value="<?= h($username ?? '') ?>" required>
                <small class="hint">3—30 位字母、数字或下划线。</small>
            </label>
            <label class="form-field">
                <span>密码</span>
                <input type="password" name="password" minlength="10"
                       autocomplete="new-password" required>
            </label>
            <label class="form-field">
                <span>确认密码</span>
                <input type="password" name="password_confirmation" minlength="10"
                       autocomplete="new-password" required>
            </label>
            <label class="form-field">
                <span>模拟联系方式（可选）</span>
                <input type="text" name="contact_text" maxlength="120"
                       value="<?= h($contact_text ?? '') ?>"
                       placeholder="例如：alice@example.test">
            </label>
            <button type="submit" class="button button-primary">注册并继续激活</button>
        </form>
        <p class="hint">已有账号？<a href="/login">返回登录</a></p>
    </div>
</section>
