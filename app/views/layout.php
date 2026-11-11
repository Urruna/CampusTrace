<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> - CampusTrace</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="/">
            <span class="brand-mark">CT</span>
            <span>
                <strong>CampusTrace</strong>
                <small>A Secure Campus Lost-and-Found System</small>
            </span>
        </a>
        <nav class="site-nav" aria-label="主导航">
            <a href="/">失物大厅</a>
            <a href="/health">环境检查</a>
            <?php if ($currentUser): ?>
                <span class="user-chip"><?= h($currentUser['username']) ?></span>
                <a href="/account/contact">联系方式</a>
                <form method="post" action="/logout" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">退出</button>
                </form>
            <?php else: ?>
                <a href="/login">登录</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="page">
    <?php foreach ($flashes as $flash): ?>
        <div class="notice notice-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="footer-inner">
        CampusTrace 课程项目 · 仅使用模拟数据和本地环境
    </div>
</footer>
</body>
</html>

