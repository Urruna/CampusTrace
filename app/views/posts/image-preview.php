<?php

declare(strict_types=1);

$displayImageId = $imageId ?? ($post['image_id'] ?? null);
$canManageImage = $currentUser !== null
    && isset($post['owner_id'])
    && (int) $currentUser['id'] === (int) $post['owner_id']
    && ($post['status'] ?? 'open') !== 'removed';
?>
<section class="post-image-block" aria-labelledby="post-image-heading">
    <h2 id="post-image-heading">帖子图片</h2>
    <?php if (!empty($displayImageId)): ?>
        <figure class="post-image">
            <img
                src="/images/<?= (int) $displayImageId ?>"
                alt="<?= h(($post['title'] ?? '帖子') . '的图片') ?>">
            <figcaption>当前显示图片 #<?= (int) $displayImageId ?></figcaption>
        </figure>
    <?php else: ?>
        <p class="hint">该帖子暂未上传图片。</p>
    <?php endif; ?>

    <?php if ($canManageImage): ?>
        <form class="post-image-upload"
              method="post"
              action="/posts/<?= (int) $post['id'] ?>/image"
              enctype="multipart/form-data">
            <?= csrf_field() ?>
            <label class="form-field grow">
                <span><?= !empty($displayImageId) ? '替换图片' : '上传图片' ?></span>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
            </label>
            <button class="button button-primary" type="submit">
                <?= !empty($displayImageId) ? '保存替换' : '上传图片' ?>
            </button>
        </form>
        <p class="hint">Baseline 每帖保留一个当前引用；替换后旧图片地址仍可读取。</p>
    <?php endif; ?>
</section>
