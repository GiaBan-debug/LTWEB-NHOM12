<?php
// backend/api/products.php
// API danh sách và chi tiết sản phẩm, tìm kiếm, lọc, phân trang

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$slug   = isset($_GET['slug']) ? trim($_GET['slug']) : null;

// ── GET chi tiết theo id hoặc slug ──────────────────────────────
if ($method === 'GET' && ($id || $slug)) {
    if ($id) {
        $stmt = $db->prepare("
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id = ? AND p.status = 'active'
        ");
        $stmt->execute([$id]);
    } else {
        $stmt = $db->prepare("
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.slug = ? AND p.status = 'active'
        ");
        $stmt->execute([$slug]);
    }

    $product = $stmt->fetch();
    if (!$product) {
        jsonResponse(false, null, 'Không tìm thấy sản phẩm', 404);
    }

    // Lấy ảnh phụ
    $imgStmt = $db->prepare(
        "SELECT id, image_path, sort_order FROM product_images
         WHERE product_id = ? ORDER BY sort_order ASC"
    );
    $imgStmt->execute([$product['id']]);
    $product['images'] = $imgStmt->fetchAll();

    jsonResponse(true, $product);
}

// ── GET danh sách (lọc + tìm kiếm + phân trang) ─────────────────
if ($method === 'GET') {
    // --- Tham số lọc ---
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
    $search     = isset($_GET['search'])      ? trim($_GET['search'])      : null;
    $minPrice   = isset($_GET['min_price'])   ? (float)$_GET['min_price'] : null;
    $maxPrice   = isset($_GET['max_price'])   ? (float)$_GET['max_price'] : null;

    // --- Sắp xếp (whitelist) ---
    $allowSort = ['created_at', 'price', 'name'];
    $sortBy    = in_array($_GET['sort_by'] ?? '', $allowSort, true) ? $_GET['sort_by'] : 'created_at';
    $sortDir   = strtoupper($_GET['sort_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    // --- Phân trang ---
    $page  = max(1, (int)($_GET['page']  ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 12)));

    // --- Build WHERE ---
    $where  = ["p.status = 'active'"];
    $params = [];

    if ($categoryId) { $where[] = 'p.category_id = ?'; $params[] = $categoryId; }
    if ($search)     { $where[] = 'p.name LIKE ?';     $params[] = '%' . $search . '%'; }
    if ($minPrice !== null) { $where[] = 'p.price >= ?'; $params[] = $minPrice; }
    if ($maxPrice !== null) { $where[] = 'p.price <= ?'; $params[] = $maxPrice; }

    $whereSQL = implode(' AND ', $where);

    // Đếm tổng
    $countStmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE {$whereSQL}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Lấy dữ liệu trang hiện tại
    $offset = ($page - 1) * $limit;
    $stmt   = $db->prepare("
        SELECT p.id, p.name, p.slug, p.price, p.stock, p.thumbnail,
               c.name AS category_name, p.created_at
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE {$whereSQL}
        ORDER BY p.{$sortBy} {$sortDir}
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([...$params, $limit, $offset]);
    $items = $stmt->fetchAll();

    jsonResponse(true, [
        'items'       => $items,
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => (int)ceil($total / $limit),
    ]);
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);