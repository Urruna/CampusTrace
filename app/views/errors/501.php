<?php

declare(strict_types=1);
?>
<section class="panel error-page">
    <p class="eyebrow">Module pending</p>
    <h1>模块尚未接入</h1>
    <p><?= h($message ?? '该功能由后续模块实现。') ?></p>
    <p>当前公共骨架已经冻结路由，成员可以基于该入口继续开发。</p>
    <a class="button button-secondary" href="/">返回首页</a>
</section>

