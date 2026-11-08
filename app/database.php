<?php

declare(strict_types=1);

function db(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $config = app_config();
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $connection = new mysqli(
        (string) $config['db_host'],
        (string) $config['db_user'],
        (string) $config['db_password'],
        (string) $config['db_name'],
        (int) $config['db_port']
    );
    $connection->set_charset((string) ($config['db_charset'] ?? 'utf8mb4'));

    return $connection;
}

