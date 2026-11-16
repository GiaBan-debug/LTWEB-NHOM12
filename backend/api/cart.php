<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
allowMethods(['GET', 'POST', 'PUT', 'DELETE']);
requireLogin();
$db = getDB();
$userId = sessionUserId();
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    $input = $method === 'DELETE' ? $_GET : getJsonBody();
    $productId = positiveInt($input['product_id'] ?? null);
    if (!$productId) throw new ApiException('Sản phẩm không hợp lệ.');
    $quantity = $method === 'DELETE' ? 1 : positiveInt($input['quantity'] ?? 1);
    if (!$quantity || $quantity > 99) throw new ApiException('Số lượng phải từ 1 đến 99.');
    transaction($db, function () use ($db, $userId, $method, $productId, $quantity): void {
        lockUser($db, $userId);
        $db->prepare('INSERT INTO carts (user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)')->execute([$userId]);
        $stmt = $db->prepare('SELECT id FROM carts WHERE user_id = ?');
        $stmt->execute([$userId]);
        $cartId = (int) $stmt->fetchColumn();
        if ($method === 'DELETE') {
            $db->prepare('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?')->execute([$cartId, $productId]);
            return;
        }
        $stmt = $db->prepare('SELECT stock, status FROM products WHERE id = ? FOR UPDATE');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product || $product['status'] !== 'active') throw new ApiException('Sản phẩm đã ngừng bán.', 404);
        $stmt = $db->prepare('SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ?');
        $stmt->execute([$cartId, $productId]);
        $current = $stmt->fetchColumn();
        if ($method === 'PUT' && $current === false) throw new ApiException('Sản phẩm không có trong giỏ.', 404);
        $newQuantity = $method === 'POST' ? (int) $current + $quantity : $quantity;
        if ($newQuantity > 99 || $newQuantity > (int) $product['stock']) throw new ApiException('Số lượng vượt tồn kho hoặc giới hạn 99 sản phẩm.', 409);
        $db->prepare('INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)')->execute([$cartId, $productId, $newQuantity]);
    });
}
jsonResponse(true, fetchCart($db, $userId), $method === 'GET' ? '' : 'Đã cập nhật giỏ hàng.');
