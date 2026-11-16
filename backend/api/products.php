<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
allowMethods(['GET']);
$db = getDB();
$id = isset($_GET['id']) ? positiveInt($_GET['id']) : null;
$slug = inputString($_GET, 'slug', 220);
if (isset($_GET['id']) && !$id) throw new ApiException('ID sản phẩm không hợp lệ.');
if ($id || $slug !== '') {
    $stmt = $db->prepare('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.status = ? AND ' . ($id ? 'p.id' : 'p.slug') . ' = ?');
    $stmt->execute(['active', $id ?: $slug]);
    $product = $stmt->fetch();
    if (!$product) throw new ApiException('Không tìm thấy sản phẩm.', 404);
    $stmt = $db->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
    $stmt->execute([$product['id']]);
    $product['images'] = $stmt->fetchAll();
    jsonResponse(true, $product);
}
$where = ["p.status = 'active'"];
$params = [];
$search = inputString($_GET, 'search', 150);
if ($search !== '') { $where[] = '(p.name LIKE ? OR p.description LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
    $cat = positiveInt($_GET['category_id']) ?? throw new ApiException('Danh mục không hợp lệ.');
    $where[] = 'p.category_id = ?'; $params[] = $cat;
}
foreach (['min_price' => '>=', 'max_price' => '<='] as $key => $op) {
    if (!isset($_GET[$key]) || $_GET[$key] === '') continue;
    $value = filter_var($_GET[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000000]]);
    if ($value === false) throw new ApiException('Khoảng giá không hợp lệ.');
    $where[] = "p.price $op ?"; $params[] = $value;
}
if (isset($_GET['min_price'], $_GET['max_price']) && $_GET['min_price'] !== '' && $_GET['max_price'] !== '' && (int) $_GET['min_price'] > (int) $_GET['max_price']) throw new ApiException('Giá thấp nhất phải nhỏ hơn giá cao nhất.');
$sort = $_GET['sort_by'] ?? '';
$sort = in_array($sort, ['price', 'name', 'created_at'], true) ? $sort : 'created_at';
$dir = ($_GET['sort_dir'] ?? '') === 'ASC' ? 'ASC' : 'DESC';
$page = min(100000, positiveInt($_GET['page'] ?? 1) ?? 1);
$limit = min(48, positiveInt($_GET['limit'] ?? 12) ?? 12);
$offset = ($page - 1) * $limit;
$whereSql = implode(' AND ', $where);
$stmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$stmt = $db->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE $whereSql ORDER BY p.$sort $dir, p.id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
jsonResponse(true, ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => max(1, (int) ceil($total / $limit))]);
