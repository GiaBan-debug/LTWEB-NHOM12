<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/config/database.php';

// Import the current catalogue without importing users/orders or replacing existing IDs.
$seed = file_get_contents(__DIR__ . '/../database/seed.sql');
$start = strpos($seed, 'INSERT INTO products (');
$terminator = 'warranty_months = VALUES(warranty_months);';
$end = $start === false ? false : strpos($seed, $terminator, $start);
if ($start === false || $end === false) throw new RuntimeException('Product seed section not found.');
$sql = substr($seed, $start, $end + strlen($terminator) - $start);
$sql = preg_replace('/^INSERT INTO products /', 'INSERT INTO demo_catalog_import ', $sql, 1);
$db = getDB();
$db->exec('CREATE TEMPORARY TABLE demo_catalog_import LIKE products');
$db->exec($sql);
$rows = $db->query('SELECT * FROM demo_catalog_import ORDER BY id')->fetchAll();
$categorySlugs = [1 => 'dien-thoai', 2 => 'laptop', 3 => 'phu-kien', 4 => 'may-tinh-bang'];
$findCategory = $db->prepare('SELECT id FROM categories WHERE slug = ?');
$findProduct = $db->prepare('SELECT id FROM products WHERE slug = ? OR name = ? LIMIT 1');
$insert = $db->prepare('INSERT INTO products (category_id, name, slug, description, specs, warranty_months, price, stock, thumbnail, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$imageExists = $db->prepare('SELECT id FROM product_images WHERE product_id = ? AND image_path = ?');
$insertImage = $db->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, 0)');
$before = [];
foreach (['users', 'orders', 'order_items'] as $table) $before[$table] = (int) $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
$added = 0;
$skipped = 0;
$db->beginTransaction();
try {
    foreach ($rows as $row) {
        $findProduct->execute([$row['slug'], $row['name']]);
        if ($findProduct->fetchColumn()) { $skipped++; continue; }
        $findCategory->execute([$categorySlugs[(int) $row['category_id']] ?? '']);
        $categoryId = $findCategory->fetchColumn();
        if (!$categoryId) throw new RuntimeException('Missing category for ' . $row['name']);
        if (!preg_match('#^products/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp|avif)$#', $row['thumbnail'])
            || !is_file(__DIR__ . '/../uploads/' . $row['thumbnail'])) {
            throw new RuntimeException('Missing product image: ' . $row['thumbnail']);
        }
        $insert->execute([$categoryId, $row['name'], $row['slug'], $row['description'], $row['specs'], $row['warranty_months'], $row['price'], $row['stock'], $row['thumbnail'], $row['status']]);
        $id = (int) $db->lastInsertId();
        $imageExists->execute([$id, $row['thumbnail']]);
        if (!$imageExists->fetchColumn()) $insertImage->execute([$id, $row['thumbnail']]);
        $added++;
    }
    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
}
echo "Added $added products; preserved $skipped existing products.\n";
foreach ($before as $table => $count) {
    $after = (int) $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    echo "$table: $count -> $after\n";
}
echo 'Total products: ' . $db->query('SELECT COUNT(*) FROM products')->fetchColumn() . "\n";
