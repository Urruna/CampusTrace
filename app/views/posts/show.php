<?php

declare(strict_types=1);
?>
<article class="panel detail-panel">
    <div class="post-card-head">
        <span class="badge badge-<?= h($post['post_type']) ?>"><?= h(post_type_label($post['post_type'])) ?></span>
        <span class="badge status-<?= h($post['status']) ?>"><?= h(post_status_label($post['status'])) ?></span>
    </div>
    <h1><?= h($post['title']) ?></h1>
    <dl class="meta-list detail-meta">
        <div><dt>分类</dt><dd><?= h($post['category']) ?></dd></div>
        <div><dt>地点</dt><dd><?= h($post['location']) ?></dd></div>
        <div><dt>发布者</dt><dd><?= h($post['username']) ?></dd></div>
        <div><dt>发布时间</dt><dd><?= h($post['created_at']) ?></dd></div>
    </dl>
    <?php
    // SECURITY-GAP R08: baseline intentionally renders the stored description as HTML.
    ?>
    <div class="description"><?= nl2br($post['description']) ?></div>

    <?php
    $imageId = $post['image_id'] ?? null;
    require CAMPUS_APP . '/views/posts/image-preview.php';
    ?>

    <div class="detail-actions action-wrap">
        <a class="button button-secondary" href="/">返回列表</a>
        <?php if ($canUseFeatures): ?>
            <a class="button button-secondary" href="/posts/<?= (int) $post['id'] ?>/edit">编辑帖子</a>
            <?php if ($isOwner && $post['status'] === 'open'): ?>
                <form method="post" action="/posts/<?= (int) $post['id'] ?>/resolve">
                    <button class="button button-secondary" type="submit">标记已解决</button>
                </form>
            <?php endif; ?>
            <?php if ($isOwner || $post['status'] === 'open'): ?>
                <a class="button button-primary" href="/posts/<?= (int) $post['id'] ?>/claims">
                    <?= $isOwner ? '管理认领' : '申请认领' ?>
                </a>
            <?php else: ?>
                <span class="badge status-resolved">已停止新认领</span>
            <?php endif; ?>
            <?php if ($currentUser['role'] === 'admin'): ?>
                <a class="button button-secondary" href="/admin/reports">举报审核</a>
            <?php endif; ?>
        <?php else: ?>
            <a class="button button-primary" href="/login">登录后申请认领</a>
        <?php endif; ?>
    </div>
</article>

<?php if ($canUseFeatures): ?>
    <section class="panel report-panel">
        <h2>举报此帖子</h2>
        <p class="hint">举报不会直接改变帖子状态。当前 baseline 允许重复提交（R10）。</p>
        <?php if (!empty($reportErrors['reason'])): ?>
            <div class="notice notice-error"><?= h($reportErrors['reason']) ?></div>
        <?php endif; ?>
        <form method="post" action="/posts/<?= (int) $post['id'] ?>/reports" class="report-form">
            <label class="form-field">
                <span>举报原因</span>
                <textarea name="reason" rows="3" maxlength="500" required><?= h($reportReason) ?></textarea>
            </label>
            <button class="button button-danger" type="submit">提交举报</button>
        </form>
    </section>
<?php endif; ?>
