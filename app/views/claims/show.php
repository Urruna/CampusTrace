<?php
$labels = ['pending' => '待审批', 'approved' => '已批准', 'rejected' => '已拒绝', 'revoked' => '已撤回'];
$id = (int) $claim['id'];
$postId = (int) $claim['post_id'];
$actions = [];
if ($isOwner && $claim['status'] === 'pending') {
    if ($claim['post_status'] === 'open') {
        $actions['approve'] = '批准申请';
    }
    $actions['reject'] = '拒绝申请';
} elseif ($isOwner && $claim['status'] === 'approved') {
    $actions['revoke'] = '撤回授权';
}
?>
<section class="panel detail-panel">
    <h1>认领申请详情</h1>
    <dl class="meta-list">
        <div><dt>物品</dt><dd><?= h($claim['post_title']) ?></dd></div>
        <div><dt>申请人</dt><dd><?= h($claim['requester_name']) ?></dd></div>
        <div><dt>状态</dt><dd><span class="badge"><?= h($labels[$claim['status']]) ?></span></dd></div>
        <div><dt>提交时间</dt><dd><?= h($claim['created_at']) ?></dd></div>
    </dl>
    <h2>申请理由</h2>
    <p class="description"><?= nl2br(h($claim['reason'])) ?></p>
    <h2>核验线索</h2>
    <p class="description"><?= nl2br(h($claim['clue'])) ?></p>
    <?php if (isset($actions['approve'])): ?>
        <p>批准这条申请时，该帖原获批人的授权会被撤回。</p>
    <?php endif; ?>
    <div class="detail-actions">
        <?php foreach ($actions as $action => $label): ?>
            <form method="post" action="/claims/<?= $id ?>/<?= h($action) ?>">
                <?= csrf_field() ?>
                <button class="button <?= $action === 'approve' ? 'button-primary' : 'button-secondary' ?>" type="submit"><?= h($label) ?></button>
            </form>
        <?php endforeach; ?>
        <?php if ($isRequester && $claim['status'] === 'approved' && $claim['post_status'] === 'open'): ?>
            <a class="button button-primary" href="/claims/<?= $id ?>/contact">查看模拟联系方式</a>
        <?php endif; ?>
        <?php $back = $isOwner ? '/posts/' . $postId . '/claims' : ($claim['post_status'] === 'removed' ? '/' : '/posts/' . $postId); ?>
        <a class="button button-secondary" href="<?= h($back) ?>">返回<?= $isOwner ? '申请列表' : ($back === '/' ? '列表' : '帖子') ?></a>
    </div>
</section>
