<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/orders.php';
allowMethods(['GET', 'POST']);
requireLogin();
$db = getDB();
$userId = sessionUserId();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {
        $id = positiveInt($_GET['id']) ?? throw new ApiException('Mã đơn không hợp lệ.');
        jsonResponse(true, getOrder($db, $id, $userId));
    }
    $page = min(100000, positiveInt($_GET['page'] ?? 1) ?? 1);
    $offset = ($page - 1) * 10;
    $stmt = $db->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
    $stmt->execute([$userId]);
    $total = (int) $stmt->fetchColumn();
    $stmt = $db->prepare("SELECT id, total_price AS total_amount, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 10 OFFSET $offset");
    $stmt->execute([$userId]);
    jsonResponse(true, ['items' => $stmt->fetchAll(), 'page' => $page, 'total_pages' => max(1, (int) ceil($total / 10)), 'total' => $total]);
}
$input = getJsonBody();
$name = inputString($input, 'receiver_name', 100, true);
$phone = inputString($input, 'phone', 15, true);
$address = inputString($input, 'address', 255, true);
$note = inputString($input, 'note', 500);
$key = inputString($input, 'checkout_key', 64, true);
if (mb_strlen($name) < 2 || mb_strlen($address) < 5 || !preg_match('/^(0|\\+84)\\d{9}$/', $phone) || !preg_match('/^[a-zA-Z0-9-]{16,64}$/', $key)) throw new ApiException('Hãy kiểm tra họ tên, điện thoại và địa chỉ.');
$result = transaction($db, function () use ($db, $userId, $name, $phone, $address, $note, $key): array {
    lockUser($db, $userId);
    $stmt = $db->prepare('SELECT id, total_price FROM orders WHERE user_id = ? AND checkout_key = ?');
    $stmt->execute([$userId, $key]);
    if ($existing = $stmt->fetch()) return ['order_id' => (int) $existing['id'], 'total_amount' => (int) $existing['total_price']];
    $stmt = $db->prepare('SELECT ci.product_id, ci.quantity, p.name, p.thumbnail, p.price, p.stock, p.status FROM cart_items ci JOIN carts c ON c.id = ci.cart_id JOIN products p ON p.id = ci.product_id WHERE c.user_id = ? ORDER BY p.id FOR UPDATE');
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll();
    if (!$items) throw new ApiException('Giỏ hàng đang trống.');
    $total = 0;
    foreach ($items as $item) {
        if ($item['status'] !== 'active' || (int) $item['quantity'] > (int) $item['stock']) throw new ApiException('Sản phẩm không đủ tồn kho hoặc đã ngừng bán: ' . $item['name'], 409);
        $total += (int) $item['price'] * (int) $item['quantity'];
    }
    $db->prepare('INSERT INTO orders (user_id, total_price, receiver_name, phone, address, note, checkout_key) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$userId, $total, $name, $phone, $address, $note, $key]);
    $id = (int) $db->lastInsertId();
    $insert = $db->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price, product_name, image) VALUES (?, ?, ?, ?, ?, ?)');
    $stock = $db->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
    foreach ($items as $item) {
        $insert->execute([$id, $item['product_id'], $item['quantity'], $item['price'], $item['name'], $item['thumbnail']]);
        $stock->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
        if ($stock->rowCount() !== 1) throw new ApiException('Tồn kho vừa thay đổi. Hãy thử lại.', 409);
    }
    $db->prepare('DELETE ci FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = ?')->execute([$userId]);
    return ['order_id' => $id, 'total_amount' => $total];
});
jsonResponse(true, $result, 'Đặt hàng thành công.', 201);
