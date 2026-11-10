<section class="panel">
    <h1>提交认领申请</h1>
    <p>物品：<?= h($post['title']) ?></p>
    <p>请提供核验线索。申请内容只向你和发布者展示。</p>
    <?php if (isset($formError)): ?>
        <p class="notice notice-error" role="alert"><?= h($formError) ?></p>
    <?php endif; ?>
    <form method="post" action="/posts/<?= (int) $post['id'] ?>/claims">
        <?= csrf_field() ?>
        <div class="form-field">
            <label for="claim-reason">申请理由</label>
            <textarea id="claim-reason" name="reason" rows="3" maxlength="500" required><?= h($reason ?? '') ?></textarea>
        </div>
        <div class="form-field">
            <label for="claim-clue">核验线索</label>
            <textarea id="claim-clue" name="clue" rows="3" maxlength="500" required><?= h($clue ?? '') ?></textarea>
        </div>
        <div class="detail-actions">
            <button class="button button-primary" type="submit">提交认领</button>
            <a class="button button-secondary" href="/posts/<?= (int) $post['id'] ?>">返回帖子</a>
        </div>
    </form>
</section>
