<?php

declare(strict_types=1);
?>
<section class="panel post-form-panel">
    <p class="eyebrow"><?= $mode === 'edit' ? 'Edit post' : 'New post' ?></p>
    <h1><?= $mode === 'edit' ? '编辑帖子' : '发布寻物或招领信息' ?></h1>
    <p class="hint">当前为教学 baseline，本表单的写请求暂未校验 CSRF 令牌（R08）。</p>

    <?php if ($errors): ?>
        <div class="notice notice-error">请检查表单中的错误后重新提交。</div>
    <?php endif; ?>

    <form method="post" action="<?= h($formAction) ?>" class="post-form">
        <fieldset class="form-field">
            <legend>信息类型</legend>
            <label class="choice">
                <input type="radio" name="post_type" value="lost"
                    <?= $post['post_type'] === 'lost' ? 'checked' : '' ?> required>
                <span>寻物：我丢失了物品</span>
            </label>
            <label class="choice">
                <input type="radio" name="post_type" value="found"
                    <?= $post['post_type'] === 'found' ? 'checked' : '' ?> required>
                <span>招领：我捡到了物品</span>
            </label>
            <?php if (!empty($errors['post_type'])): ?>
                <span class="field-error"><?= h($errors['post_type']) ?></span>
            <?php endif; ?>
        </fieldset>

        <label class="form-field">
            <span>标题</span>
            <input type="text" name="title" maxlength="120" value="<?= h($post['title']) ?>" required>
            <?php if (!empty($errors['title'])): ?>
                <span class="field-error"><?= h($errors['title']) ?></span>
            <?php endif; ?>
        </label>

        <label class="form-field">
            <span>详细描述</span>
            <textarea name="description" rows="7" maxlength="10000" required><?= h($post['description']) ?></textarea>
            <span class="hint">请勿填写真实手机号、身份证号、学号或社交账号。</span>
            <?php if (!empty($errors['description'])): ?>
                <span class="field-error"><?= h($errors['description']) ?></span>
            <?php endif; ?>
        </label>

        <div class="form-row">
            <label class="form-field">
                <span>分类</span>
                <input type="text" name="category" maxlength="40" value="<?= h($post['category']) ?>"
                       placeholder="例如：证件与卡包" required>
                <?php if (!empty($errors['category'])): ?>
                    <span class="field-error"><?= h($errors['category']) ?></span>
                <?php endif; ?>
            </label>
            <label class="form-field">
                <span>地点</span>
                <input type="text" name="location" maxlength="120" value="<?= h($post['location']) ?>"
                       placeholder="例如：图书馆二楼" required>
                <?php if (!empty($errors['location'])): ?>
                    <span class="field-error"><?= h($errors['location']) ?></span>
                <?php endif; ?>
            </label>
        </div>

        <div class="form-actions">
            <button class="button button-primary" type="submit">
                <?= $mode === 'edit' ? '保存修改' : '发布信息' ?>
            </button>
            <a class="button button-secondary"
               href="<?= $mode === 'edit' ? '/posts/' . (int) $post['id'] : '/' ?>">取消</a>
        </div>
    </form>
</section>
