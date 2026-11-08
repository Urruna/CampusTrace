<?php

declare(strict_types=1);
?>
<section class="panel">
    <p class="eyebrow">Runtime check</p>
    <h1>环境检查</h1>
    <p>用于确认当前 web 环境、图片处理能力、数据库连接和存储目录。</p>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>检查项</th>
                <th>当前值</th>
                <th>结果</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($checks as $check): ?>
                <tr>
                    <td><?= h($check[0]) ?></td>
                    <td><?= h($check[1]) ?></td>
                    <td>
                        <span class="badge <?= $check[2] ? 'status-open' : 'status-removed' ?>">
                            <?= $check[2] ? '通过' : '需处理' ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

