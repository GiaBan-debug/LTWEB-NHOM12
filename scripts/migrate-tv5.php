<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/config/database.php';
function migrateTv5Database(PDO $db): void
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
    migrateTv5Database(getDB());
    echo "TV5 migration complete; existing users, passwords, roles, carts and orders preserved.\n";
}
