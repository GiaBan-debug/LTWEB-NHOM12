<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/config/database.php';
function migrateDatabase(PDO $db): void
{
    // Inspect before each ALTER: safe to rerun on original/demo/current schemas.
    $columns = array_column($db->query('SHOW FULL COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC), null, 'Field');
    $additions = [
        'firebase_uid' => 'VARCHAR(128) COLLATE utf8mb4_bin NULL UNIQUE',
        'email_verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'phone' => "VARCHAR(15) NOT NULL DEFAULT ''", 'address' => "VARCHAR(255) NOT NULL DEFAULT ''",
    ];
    foreach ($additions as $column => $definition) {
        if (!isset($columns[$column])) $db->exec("ALTER TABLE users ADD COLUMN `$column` $definition");
    }
    if (($columns['password']['Null'] ?? '') === 'NO') $db->exec('ALTER TABLE users MODIFY password VARCHAR(255) NULL');
    if (isset($columns['firebase_uid']) && $columns['firebase_uid']['Collation'] !== 'utf8mb4_bin') $db->exec('ALTER TABLE users MODIFY firebase_uid VARCHAR(128) COLLATE utf8mb4_bin NULL');
    $db->exec("UPDATE users SET firebase_uid = NULL WHERE password IS NOT NULL AND ((email = 'admin@ltweb.com' AND firebase_uid = 'firebase_demo_admin') OR (email = 'user1@ltweb.com' AND firebase_uid = 'firebase_demo_user1') OR (email = 'user2@ltweb.com' AND firebase_uid = 'firebase_demo_user2'))");
    $productColumns = array_column($db->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC), null, 'Field');
    foreach (['specs' => 'TEXT NULL', 'warranty_months' => 'INT UNSIGNED NOT NULL DEFAULT 12'] as $column => $definition) {
        if (!isset($productColumns[$column])) $db->exec("ALTER TABLE products ADD COLUMN `$column` $definition");
    }
    $orderColumns = array_column($db->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_ASSOC), null, 'Field');
    // Also supports the original pre-demo schema without dropping existing data.
    foreach (['receiver_name' => "VARCHAR(100) NOT NULL DEFAULT ''", 'phone' => "VARCHAR(15) NOT NULL DEFAULT ''", 'checkout_key' => 'VARCHAR(64) NULL', 'shipping_fee' => 'DECIMAL(15,2) NOT NULL DEFAULT 0'] as $column => $definition) {
        if (!isset($orderColumns[$column])) $db->exec("ALTER TABLE orders ADD COLUMN `$column` $definition");
    }
    $indexes = array_column($db->query('SHOW INDEX FROM orders')->fetchAll(PDO::FETCH_ASSOC), 'Key_name');
    foreach (['carrier' => 'VARCHAR(100) NULL', 'tracking_code' => 'VARCHAR(100) NULL', 'estimated_delivery' => 'DATE NULL'] as $column => $definition) {
        if (!isset($orderColumns[$column])) $db->exec("ALTER TABLE orders ADD COLUMN `$column` $definition");
    }
    if (!in_array('uq_checkout', $indexes, true)) $db->exec('ALTER TABLE orders ADD UNIQUE KEY uq_checkout (user_id, checkout_key)');
    $itemColumns = array_column($db->query('SHOW COLUMNS FROM order_items')->fetchAll(PDO::FETCH_ASSOC), null, 'Field');
    if (!isset($itemColumns['product_name'])) {
        $db->exec("ALTER TABLE order_items ADD COLUMN product_name VARCHAR(200) NOT NULL DEFAULT ''");
        $db->exec('UPDATE order_items oi JOIN products p ON p.id = oi.product_id SET oi.product_name = p.name');
    }
    if (!isset($itemColumns['image'])) {
        $db->exec('ALTER TABLE order_items ADD COLUMN image VARCHAR(255) NULL');
        $db->exec('UPDATE order_items oi JOIN products p ON p.id = oi.product_id SET oi.image = p.thumbnail');
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $options = getopt('', ['initialize', 'seed']);
    if (isset($options['seed']) && !isset($options['initialize'])) throw new RuntimeException('--seed requires --initialize and an empty database.');
    if (isset($options['initialize'])) {
        $name = appConfig('DB_NAME', 'ltweb_nhom12');
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) throw new RuntimeException('Invalid database name.');
        $dsn = 'mysql:host=' . appConfig('DB_HOST', '127.0.0.1') . ';port=' . appConfig('DB_PORT', '3306') . ';charset=utf8mb4';
        $optionsPDO = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        try {
            $db = new PDO($dsn . ';dbname=' . $name, appConfig('DB_USER', 'root'), appConfig('DB_PASSWORD'), $optionsPDO);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) !== 1049) throw $error;
            $db = new PDO($dsn, appConfig('DB_USER', 'root'), appConfig('DB_PASSWORD'), $optionsPDO);
            $db->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $db->exec("USE `$name`");
        }
        if (!$db->query('SHOW TABLES')->fetchAll()) {
            $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
            if (isset($options['seed'])) $db->exec(file_get_contents(__DIR__ . '/../database/seed.sql'));
            echo "Database initialized.\n";
        } else {
            echo "Existing database preserved; seed was not imported.\n";
        }
    } else {
        $db = getDB();
    }
    migrateDatabase($db);
    // Admin history migration shares this entry point and is safe to rerun.
    $historySql = file_get_contents(__DIR__ . '/../database/migration-tv4.sql');
    $historySql = preg_replace('/CREATE INDEX IF NOT EXISTS idx_order_created ON orders\(created_at\);/', '', $historySql);
    $db->exec($historySql);
    $indexes = array_column($db->query('SHOW INDEX FROM orders')->fetchAll(PDO::FETCH_ASSOC), 'Key_name');
    if (!in_array('idx_order_created', $indexes, true)) $db->exec('CREATE INDEX idx_order_created ON orders(created_at)');
    echo "Database up to date; existing accounts and business data preserved.\n";
}
