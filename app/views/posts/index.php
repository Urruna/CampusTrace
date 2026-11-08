<?php

declare(strict_types=1);
?>
<section class="hero">
    <div>
        <p class="eyebrow">Campus lost and found</p>
        <h1>校园失物大厅</h1>
        <p>查找寻物、招领信息，并公开交流之前先确认记录与联系方式权限。</p>
    </div>
    <a class="button button-primary" href="/posts/new">发布信息</a>
</section>

<form class="panel filter-bar" method="get" action="/">
    <label class="form-field grow">
        <span>关键词</span>
        <input type="search" name="q" value="<?= h($filters['q']) ?>" placeholder="标题、描述或地点">
    </label>
    <label class="form-field">
        <span>类型</span>
        <select name="type">
            <option value="">全部</option>
            <option value="lost" <?= $filters['type'] === 'lost' ? 'selected' : '' ?>>寻物</option>
            <option value="found" <?= $filters['type'] === 'found' ? 'selected' : '' ?>>招领</option>
        </select>
    </label>
    <label class="form-field">
        <span>状态</span>
        <select name="status">
            <option value="">全部</option>
            <option value="open" <?= $filters['status'] === 'open' ? 'selected' : '' ?>>开放</option>
            <option value="resolved" <?= $filters['status'] === 'resolved' ? 'selected' : '' ?>>已解决</option>
        </select>
    </label>
    <button class="button button-secondary" type="submit">筛选</button>
</form>

<?php if (!$posts): ?>
    <div class="panel empty-state">
        <h2>没有符合条件的记录</h2>
        <p>可以修改关键词或筛选条件后再试。</p>
    </div>
<?php else: ?>
    <div class="post-grid">
        <?php foreach ($posts as $post): ?>
            <article class="post-card panel">
                <div class="post-card-head">
                    <span class="badge badge-<?= h($post['post_type']) ?>"><?= h(post_type_label($post['post_type'])) ?></span>
                    <span class="badge status-<?= h($post['status']) ?>"><?= h(post_status_label($post['status'])) ?></span>
                </div>
                <h2><a href="/posts/<?= (int) $post['id'] ?>"><?= h($post['title']) ?></a></h2>
                <p class="post-summary"><?= h(mb_strimwidth($post['description'], 0, 120, '...', 'UTF-8')) ?></p>
                <dl class="meta-list">
                    <div><dt>地点</dt><dd><?= h($post['location']) ?></dd></div>
                    <div><dt>分类</dt><dd><?= h($post['category']) ?></dd></div>
                    <div><dt>发布者</dt><dd><?= h($post['username']) ?></dd></div>
                </dl>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

