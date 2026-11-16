<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/config/database.php';
$port = getenv('DB_PORT') ?: '3307';
$name = getenv('DB_NAME') ?: 'ltweb_nhom12_demo';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) throw new RuntimeException('Invalid database name.');
$db = new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$db->exec("USE `$name`");
$tables = $db->query('SHOW TABLES')->fetchAll();
if ($tables) {
    echo "Demo database already exists; no data was changed.\n";
    exit;
}
$db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$db->exec(file_get_contents(__DIR__ . '/../database/seed.sql'));
echo "Demo database created and seeded.\n";
