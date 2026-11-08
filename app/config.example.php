<?php

declare(strict_types=1);

return [
    'app_name' => 'CampusTrace',
    'app_url' => 'http://localhost:8080',
    'timezone' => 'Asia/Shanghai',
    'debug' => false,

    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'campustrace_baseline',
    'db_user' => 'campustrace_app',
    'db_password' => 'CHANGE_ME',
    'db_charset' => 'utf8mb4',

    'session_name' => 'CampusTraceSession',
    'storage_dir' => dirname(__DIR__) . '/storage/baseline_images',
    'log_dir' => dirname(__DIR__) . '/storage/logs',
];

