<?php

declare(strict_types=1);
?>
<section class="panel error-page">
    <p class="eyebrow">500</p>
    <h1>服务器处理失败</h1>
    <p><?= h($message ?? '请求暂时无法完成，请查看服务端日志。') ?></p>
    <a class="button button-secondary" href="/">返回首页</a>
</section>

