<?php

declare(strict_types=1);
?>
<section class="hero">
    <div>
        <p class="eyebrow">My posts</p>
        <h1>我的帖子</h1>
        <p>集中查看自己发布的记录，并继续编辑、结帖或管理认领申请。</p>
    </div>
    <a class="button button-primary" href="/posts/new">发布信息</a>
</section>

<?php if (!$posts): ?>
    <div class="panel empty-state">
        <h2>还没有发布过帖子</h2>
        <p>发布寻物或招领信息后，可以在这里统一管理。</p>
        <a class="button button-primary" href="/posts/new">发布第一条信息</a>
    </div>
<?php else: ?>
    <div class="post-grid">
        <?php foreach ($posts as $post): ?>
            <article class="post-card panel owned-post-card">
                <div class="post-card-head">
                    <span class="badge badge-<?= h($post['post_type']) ?>">
                        <?= h(post_type_label($post['post_type'])) ?>
                    </span>
                    <span class="badge status-<?= h($post['status']) ?>">
                        <?= h(post_status_label($post['status'])) ?>
                    </span>
                </div>
                <h2>
                    <?php if ($post['status'] === 'removed'): ?>
                        <?= h($post['title']) ?>
                    <?php else: ?>
                        <a href="/posts/<?= (int) $post['id'] ?>"><?= h($post['title']) ?></a>
                    <?php endif; ?>
                </h2>
                <p class="post-summary">
                    <?= h(mb_strimwidth($post['description'], 0, 120, '...', 'UTF-8')) ?>
                </p>
                <dl class="meta-list">
                    <div><dt>分类</dt><dd><?= h($post['category']) ?></dd></div>
                    <div><dt>地点</dt><dd><?= h($post['location']) ?></dd></div>
                    <div><dt>更新时间</dt><dd><?= h($post['updated_at']) ?></dd></div>
                </dl>

                <div class="detail-actions owned-post-actions">
                    <?php if ($post['status'] !== 'removed'): ?>
                        <a class="button button-secondary button-small"
                           href="/posts/<?= (int) $post['id'] ?>">查看详情</a>
                        <a class="button button-secondary button-small"
                           href="/posts/<?= (int) $post['id'] ?>/edit">编辑</a>
                        <a class="button button-secondary button-small"
                           href="/posts/<?= (int) $post['id'] ?>/claims">认领管理</a>
                    <?php endif; ?>

                    <?php if ($post['status'] === 'open'): ?>
                        <form method="post" action="/posts/<?= (int) $post['id'] ?>/resolve">
                            <button class="button button-primary button-small" type="submit">
                                标记已解决
                            </button>
                        </form>
                    <?php elseif ($post['status'] === 'resolved'): ?>
                        <span class="badge status-resolved">已停止新认领</span>
                    <?php else: ?>
                        <span class="hint">该帖子已下架，仅保留记录状态。</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
