<?php

declare(strict_types=1);

define('CAMPUS_ROOT', dirname(__DIR__));
define('CAMPUS_APP', __DIR__);

$configFile = __DIR__ . '/config.local.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('CampusTrace 尚未完成本地配置。请复制 app/config.example.php 为 app/config.local.php。');
}

$GLOBALS['campus_config'] = require $configFile;
$config = $GLOBALS['campus_config'];

date_default_timezone_set((string) ($config['timezone'] ?? 'Asia/Shanghai'));

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

$logDir = (string) ($config['log_dir'] ?? (CAMPUS_ROOT . '/storage/logs'));
if (is_dir($logDir) || @mkdir($logDir, 0770, true)) {
    ini_set('error_log', $logDir . '/php-error.log');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) ($config['session_name'] ?? 'CampusTraceSession'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
}

function app_config(?string $key = null)
{
    $config = $GLOBALS['campus_config'] ?? [];
    if ($key === null) {
        return $config;
    }

    return $config[$key] ?? null;
}

require_once CAMPUS_APP . '/helpers.php';
require_once CAMPUS_APP . '/database.php';
require_once CAMPUS_APP . '/auth.php';

