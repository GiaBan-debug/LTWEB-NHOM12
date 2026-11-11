<?php
// backend/api/categories.php
// API danh mục (GET all / GET by id)

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $cat = $stmt->fetch();
    if (!$cat) jsonResponse(false, null, 'Không tìm thấy danh mục', 404);
    jsonResponse(true, $cat);
}

// Lấy tất cả kèm số lượng sản phẩm
$stmt = $db->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
    GROUP BY c.id
    ORDER BY c.name ASC
");
jsonResponse(true, $stmt->fetchAll());