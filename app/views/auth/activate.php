<?php

declare(strict_types=1);
?>
<section class="auth-layout">
    <div class="panel auth-copy">
        <p class="eyebrow">One-time activation</p>
        <h1>激活后才能参与认领。</h1>
        <p>每个邀请码只能使用一次。邀请码过期、已使用或账号已经激活时，服务端会拒绝请求。</p>
    </div>

    <div class="panel auth-form">
        <h2>使用邀请码激活</h2>
        <?php if (!empty($error)): ?>
            <div class="notice notice-error"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/activate">
            <?= csrf_field() ?>
            <label class="form-field">
                <span>用户名</span>
                <input type="text" name="username" maxlength="30"
                       autocomplete="username" value="<?= h($username ?? '') ?>" required>
            </label>
            <label class="form-field">
                <span>邀请码</span>
                <input type="text" name="invite_code" maxlength="80"
                       autocomplete="one-time-code" required>
            </label>
            <button type="submit" class="button button-primary">激活账号</button>
        </form>
        <p class="hint">尚未注册？<a href="/register">先注册账号</a></p>
    </div>
</section>
