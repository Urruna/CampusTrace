<?php

declare(strict_types=1);
?>
<section class="auth-layout">
    <div class="panel auth-copy">
        <p class="eyebrow">CampusTrace</p>
        <h1>找回物品，也保护信息。</h1>
        <p>登录后可以管理本人发布的记录。当前骨架只开放登录、检索和详情，其他模块由成员分别接入。</p>
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
        <p class="hint">测试账号：<code>alice</code> / <code>bob</code> / <code>admin</code></p>
    </div>
</section>

