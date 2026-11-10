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
    <div class="description"><?= nl2br(h($post['description'])) ?></div>
    <div class="detail-actions">
        <a class="button button-secondary" href="/">返回列表</a>
        <?php $isOwner = $currentUser !== null && (int) $currentUser['id'] === (int) $post['owner_id']; ?>
        <?php if ($isOwner || $post['status'] === 'open'): ?>
            <a class="button button-primary" href="/posts/<?= (int) $post['id'] ?>/claims"><?= $isOwner ? '管理认领' : '申请认领' ?></a>
        <?php endif; ?>
    </div>
</article>

