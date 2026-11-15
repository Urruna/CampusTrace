<?php

declare(strict_types=1);

$gallery = isset($postImages) && is_array($postImages) ? $postImages : [];
if (!$gallery && !empty($post['image_id'])) {
    $gallery = [['id' => (int) $post['image_id']]];
}
$coverId = isset($post['current_image_id']) && $post['current_image_id'] !== null
    ? (int) $post['current_image_id']
    : (isset($post['image_id']) ? (int) $post['image_id'] : null);
$canManageImage = $currentUser !== null
    && isset($post['owner_id'])
    && (int) $currentUser['id'] === (int) $post['owner_id']
    && ($post['status'] ?? 'open') !== 'removed';
?>
<section class="post-image-block" aria-labelledby="post-image-heading">
    <h2 id="post-image-heading">
        帖子图片
        <?php if ($gallery): ?>
            <span class="image-count"><?= count($gallery) ?> 张</span>
        <?php endif; ?>
    </h2>
    <?php if ($gallery): ?>
        <div class="post-image-gallery">
            <?php foreach ($gallery as $index => $image): ?>
                <?php $galleryImageId = (int) $image['id']; ?>
                <figure class="post-image">
                    <a href="/images/<?= $galleryImageId ?>">
                        <img
                            src="/images/<?= $galleryImageId ?>"
                            alt="<?= h(($post['title'] ?? '帖子') . '的图片 ' . ($index + 1)) ?>"
                            loading="lazy">
                    </a>
                    <figcaption>
                        图片 <?= $index + 1 ?>
                        <?php if ($coverId === $galleryImageId): ?>
                            <span class="cover-badge">封面</span>
                        <?php endif; ?>
                    </figcaption>
                    <?php if ($canManageImage): ?>
                        <form method="post"
                              action="/posts/<?= (int) $post['id'] ?>/images/<?= $galleryImageId ?>/delete">
                            <?= csrf_field() ?>
                            <button class="link-button danger-link" type="submit">删除此图片</button>
                        </form>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>
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
                <span><?= $gallery ? '继续添加图片' : '上传图片' ?></span>
                <input
                    type="file"
                    name="images[]"
                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                    multiple
                    required>
            </label>
            <button class="button button-primary" type="submit">上传</button>
        </form>
        <p class="hint">首次上传时，第一张成功图片会作为封面；后续图片会追加到相册。</p>
    <?php endif; ?>
</section>
