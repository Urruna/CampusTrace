<?php

declare(strict_types=1);
?>
<section class="panel report-admin-panel">
    <div class="page-title-row">
        <div>
            <p class="eyebrow">Report administration</p>
            <h1>举报审核</h1>
        </div>
        <span class="badge status-open">Baseline R10</span>
    </div>
    <p class="hint">教学 baseline 未强制管理员角色，也未阻止普通账号直接访问或下架（R10）。</p>

    <form class="filter-bar report-filter" method="get" action="/admin/reports">
        <label class="form-field grow">
            <span>举报状态</span>
            <select name="status">
                <option value="">全部</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>待处理</option>
                <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>已处理</option>
                <option value="dismissed" <?= $statusFilter === 'dismissed' ? 'selected' : '' ?>>已驳回</option>
            </select>
        </label>
        <button class="button button-secondary" type="submit">筛选</button>
    </form>

    <?php if (!$reports): ?>
        <div class="empty-state">
            <h2>没有符合条件的举报</h2>
            <p>可以切换状态筛选后重试。</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>编号</th>
                    <th>帖子</th>
                    <th>举报人</th>
                    <th>原因</th>
                    <th>状态</th>
                    <th>处理</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td>#<?= (int) $report['id'] ?></td>
                        <td>
                            <strong><?= h($report['title']) ?></strong>
                            <small>帖子 #<?= (int) $report['post_id'] ?> · <?= h(post_status_label($report['post_status'])) ?></small>
                        </td>
                        <td><?= h($report['reporter_username']) ?></td>
                        <td><?= h($report['reason']) ?></td>
                        <td>
                            <span class="badge report-status-<?= h($report['status']) ?>">
                                <?= h(report_status_label($report['status'])) ?>
                            </span>
                            <?php if ($report['handler_username']): ?>
                                <small><?= h($report['handler_username']) ?> · <?= h($report['handled_at']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($report['post_status'] !== 'removed'): ?>
                                <form method="post" action="/admin/posts/<?= (int) $report['post_id'] ?>/remove">
                                    <button class="button button-danger button-small" type="submit">下架帖子</button>
                                </form>
                            <?php else: ?>
                                <span class="hint">已下架</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
