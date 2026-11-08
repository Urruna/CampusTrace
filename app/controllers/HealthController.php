<?php

declare(strict_types=1);

final class HealthController
{
    public function index(): void
    {
        $checks = [
            ['PHP 版本', PHP_VERSION, version_compare(PHP_VERSION, '7.3.4', '>=')],
            ['PHP SAPI', PHP_SAPI, true],
            ['mysqli', extension_loaded('mysqli') ? '已启用' : '未启用', extension_loaded('mysqli')],
            ['fileinfo', extension_loaded('fileinfo') ? '已启用' : '未启用', extension_loaded('fileinfo')],
            ['GD', extension_loaded('gd') ? '已启用' : '未启用', extension_loaded('gd')],
            ['PNG 解码', function_exists('imagecreatefrompng') ? '可用' : '不可用', function_exists('imagecreatefrompng')],
            ['JPEG 解码', function_exists('imagecreatefromjpeg') ? '可用' : '不可用', function_exists('imagecreatefromjpeg')],
            ['文件上传', ini_get('file_uploads') ? 'On' : 'Off', (bool) ini_get('file_uploads')],
            ['上传大小', ini_get('upload_max_filesize'), true],
            ['POST 大小', ini_get('post_max_size'), true],
        ];

        try {
            $db = db();
            $row = $db->query('SELECT DATABASE() AS db_name, @@character_set_database AS db_charset')->fetch_assoc();
            $checks[] = ['数据库连接', '成功：' . $row['db_name'], true];
            $checks[] = ['数据库字符集', $row['db_charset'], $row['db_charset'] === 'utf8mb4'];
        } catch (Throwable $e) {
            $checks[] = ['数据库连接', '失败，请检查本地配置和 MySQL 服务', false];
        }

        $storageDir = (string) app_config('storage_dir');
        $checks[] = [
            '图片存储目录',
            is_dir($storageDir) && is_writable($storageDir) ? '可写' : '不可写',
            is_dir($storageDir) && is_writable($storageDir),
        ];

        render('health/index', [
            'title' => '环境检查',
            'checks' => $checks,
        ]);
    }
}

