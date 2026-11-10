<?php $labels = ['pending' => '待审批', 'approved' => '已批准', 'rejected' => '已拒绝', 'revoked' => '已撤回']; ?>
<section class="panel">
    <h1>管理认领申请</h1>
    <p>物品：<?= h($post['title']) ?></p>
    <?php if (!$claims): ?>
        <p>目前还没有认领申请。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">申请人</th><th scope="col">状态</th><th scope="col">提交时间</th><th scope="col">操作</th></tr></thead>
                <tbody>
                <?php foreach ($claims as $claim): ?>
                    <tr>
                        <td><?= h($claim['requester_name']) ?></td>
                        <td><span class="badge"><?= h($labels[$claim['status']]) ?></span></td>
                        <td><?= h($claim['created_at']) ?></td>
                        <td><a href="/claims/<?= (int) $claim['id'] ?>">查看申请</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <p><a class="button button-secondary" href="<?= $post['status'] === 'removed' ? '/' : '/posts/' . (int) $post['id'] ?>">返回<?= $post['status'] === 'removed' ? '列表' : '帖子' ?></a></p>
</section>
