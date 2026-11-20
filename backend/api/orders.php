<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/checkout.php';
allowMethods(['GET', 'POST', 'PUT']);
requireLogin();
$db = getDB();
$userId = sessionUserId();
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = getJsonBody();
    $id = positiveInt($input['id'] ?? null) ?? throw new ApiException('Mã đơn không hợp lệ.');
    if (($input['action'] ?? '') !== 'cancel') throw new ApiException('Thao tác không hợp lệ.');
    cancelCustomerOrder($db, $id, $userId);
    jsonResponse(true, getOrder($db, $id, $userId), 'Đã hủy đơn và hoàn tồn kho.');
}
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
    $stmt = $db->prepare("SELECT id, total_price AS total_amount, shipping_fee, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 10 OFFSET $offset");
    $stmt->execute([$userId]);
    jsonResponse(true, ['items' => $stmt->fetchAll(), 'page' => $page, 'total_pages' => max(1, (int) ceil($total / 10)), 'total' => $total]);
}
$input = getJsonBody();
if (isset($input['use_profile']) && !is_bool($input['use_profile'])) throw new ApiException('use_profile phải là boolean.', 422);
if (($input['use_profile'] ?? false) === true) {
    $profile = getCurrentUser();
    $input['receiver_name'] = $profile['name'];
    $input['phone'] = $profile['phone'] ?? '';
    $input['address'] = $profile['address'] ?? '';
}
if (authMode() === 'firebase' && emailVerificationRequired() && !getCurrentUser()['email_verified']) {
    throw new ApiException('Hãy xác minh email trước khi đặt hàng.', 403, ['code' => 'EMAIL_NOT_VERIFIED']);
}
$name = inputString($input, 'receiver_name', 100, true);
$phone = normalizePhone(inputString($input, 'phone', 30, true));
$address = inputString($input, 'address', 255, true);
$note = inputString($input, 'note', 500);
$key = inputString($input, 'checkout_key', 64, true);
if (mb_strlen($name) < 2) throw new ApiException('Họ tên phải có ít nhất 2 ký tự.', 422, ['errors' => ['receiver_name' => 'Nhập họ tên có ít nhất 2 ký tự.']]);
if (mb_strlen($address) < 5) throw new ApiException('Địa chỉ phải có ít nhất 5 ký tự.', 422, ['errors' => ['address' => 'Nhập địa chỉ có ít nhất 5 ký tự.']]);
if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/', $key)) throw new ApiException('Mã lượt đặt hàng không hợp lệ.');
$result = transaction($db, function () use ($db, $userId, $name, $phone, $address, $note, $key): array {
    lockUser($db, $userId);
    $stmt = $db->prepare('SELECT id, total_price, shipping_fee FROM orders WHERE user_id = ? AND checkout_key = ?');
    $stmt->execute([$userId, $key]);
    if ($existing = $stmt->fetch()) {
        $subtotal = (int) $existing['total_price'];
        $fee = (int) ($existing['shipping_fee'] ?? 0);
        return [
            'order_id' => (int) $existing['id'],
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
            'shipping_fee' => $fee,
            'payable_total' => $subtotal + $fee,
        ];
    }
    $stmt = $db->prepare('SELECT ci.product_id, ci.quantity, p.name, p.thumbnail, p.price, p.stock, p.status FROM cart_items ci JOIN carts c ON c.id = ci.cart_id JOIN products p ON p.id = ci.product_id WHERE c.user_id = ? ORDER BY p.id FOR UPDATE');
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll();
    if (!$items) throw new ApiException('Giỏ hàng đang trống.');
    $total = 0;
    foreach ($items as $item) {
        if ($item['status'] !== 'active' || (int) $item['quantity'] > (int) $item['stock']) throw new ApiException('Sản phẩm không đủ tồn kho hoặc đã ngừng bán: ' . $item['name'], 409);
        $total += (int) $item['price'] * (int) $item['quantity'];
    }
    $amounts = orderAmounts($total);
    $db->prepare('INSERT INTO orders (user_id, total_price, shipping_fee, receiver_name, phone, address, note, checkout_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$userId, $amounts['total'], $amounts['shipping_fee'], $name, $phone, $address, $note, $key]);
    $id = (int) $db->lastInsertId();
    $insert = $db->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price, product_name, image) VALUES (?, ?, ?, ?, ?, ?)');
    $stock = $db->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
    foreach ($items as $item) {
        $insert->execute([$id, $item['product_id'], $item['quantity'], $item['price'], $item['name'], $item['thumbnail']]);
        $stock->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
        if ($stock->rowCount() !== 1) throw new ApiException('Tồn kho vừa thay đổi. Hãy thử lại.', 409);
    }
    $db->prepare('DELETE ci FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = ?')->execute([$userId]);
    return ['order_id' => $id, 'total_amount' => $amounts['total'], 'subtotal' => $amounts['subtotal'], 'shipping_fee' => $amounts['shipping_fee']];
});
jsonResponse(true, $result, 'Đặt hàng thành công.', 201);
