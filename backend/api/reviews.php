<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/reviews.php';
allowMethods(['GET', 'POST', 'PUT']);
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') requireLogin();
$input = $method === 'GET' ? $_GET : getJsonBody();
$productId = positiveInt($input['product_id'] ?? null) ?? throw new ApiException('Mã sản phẩm không hợp lệ.', 422);
$stmt = $db->prepare('SELECT id FROM products WHERE id = ?');
$stmt->execute([$productId]);
if (!$stmt->fetchColumn()) throw new ApiException('Không tìm thấy sản phẩm.', 404);

if ($method === 'GET') {
    $page = min(100000, positiveInt($_GET['page'] ?? 1) ?? 1);
    $offset = ($page - 1) * 10;
    $stats = reviewStats($db, $productId);
    $stmt = $db->prepare("SELECT r.id, r.rating, r.feedback, r.created_at, r.updated_at, u.name AS reviewer_name FROM product_reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = ? ORDER BY r.id DESC LIMIT 10 OFFSET $offset");
    $stmt->execute([$productId]);
    $items = $stmt->fetchAll();
    foreach ($items as &$item) { $item['id'] = (int) $item['id']; $item['rating'] = (int) $item['rating']; }
    unset($item);
    $user = getCurrentUser();
    jsonResponse(true, $stats + [
        'items' => $items, 'page' => $page, 'total_pages' => max(1, (int) ceil($stats['rating_count'] / 10)),
        'logged_in' => (bool) $user,
        'can_review' => $user ? completedPurchase($db, (int) $user['id'], $productId) !== null : false,
        'my_review' => $user ? ownReview($db, (int) $user['id'], $productId) : null,
    ]);
}

$rating = $input['rating'] ?? null;
$feedback = $input['feedback'] ?? null;
$errors = [];
if ((!is_int($rating) && !is_string($rating)) || !preg_match('/^[1-5]$/D', (string) $rating)) $errors['rating'] = 'Chọn từ 1 đến 5 sao.';
if (!is_string($feedback) || trim($feedback) === '' || mb_strlen(trim($feedback)) > 2000) $errors['feedback'] = 'Nhập feedback từ 1 đến 2.000 ký tự.';
if ($errors) throw new ApiException('Hãy kiểm tra nội dung đánh giá.', 422, ['errors' => $errors]);
$userId = sessionUserId();
transaction($db, static function () use ($db, $userId, $productId, $rating, $feedback, $method): void {
    // Serialize two submissions by the same user; the unique constraint is the final guard.
    lockUser($db, $userId);
    $orderId = completedPurchase($db, $userId, $productId);
    if ($orderId === null) throw new ApiException('Chỉ có thể đánh giá sản phẩm thuộc đơn của bạn đã hoàn thành.', 403);
    $existing = ownReview($db, $userId, $productId);
    if ($method === 'POST' && $existing) throw new ApiException('Bạn đã đánh giá sản phẩm này. Hãy sửa đánh giá hiện có.', 409);
    if ($method === 'PUT' && !$existing) throw new ApiException('Bạn chưa có đánh giá cho sản phẩm này.', 404);
    if ($existing) {
        $db->prepare('UPDATE product_reviews SET rating = ?, feedback = ? WHERE user_id = ? AND product_id = ?')->execute([(int) $rating, trim($feedback), $userId, $productId]);
    } else {
        $db->prepare('INSERT INTO product_reviews (user_id, product_id, order_id, rating, feedback) VALUES (?, ?, ?, ?, ?)')->execute([$userId, $productId, $orderId, (int) $rating, trim($feedback)]);
    }
});
jsonResponse(true, reviewStats($db, $productId) + ['my_review' => ownReview($db, $userId, $productId)], 'Đã lưu đánh giá.', $method === 'POST' ? 201 : 200);
