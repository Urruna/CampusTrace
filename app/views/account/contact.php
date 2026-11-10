<?php

declare(strict_types=1);
?>
<section class="auth-layout">
    <div class="panel auth-copy">
        <p class="eyebrow">Account privacy</p>
        <h1>控制你的联系方式。</h1>
        <p>这里保存的是课程模拟联系方式。帖子公开页面不会直接展示该字段，只有通过认领授权流程后才能读取。</p>
    </div>

    <div class="panel auth-form">
        <h2>本人联系方式</h2>
        <?php if (!empty($error)): ?>
            <div class="notice notice-error"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/account/contact">
            <?= csrf_field() ?>
            <label class="form-field">
                <span>模拟联系方式</span>
                <input type="text" name="contact_text" maxlength="120"
                       value="<?= h($user['contact_text'] ?? '') ?>"
                       placeholder="例如：alice@example.test">
                <small class="hint">留空可以清除联系方式；请勿填写真实敏感信息。</small>
            </label>
            <button type="submit" class="button button-primary">保存联系方式</button>
        </form>
        <p class="hint">当前账号：<code><?= h($user['username']) ?></code></p>
    </div>
</section>
