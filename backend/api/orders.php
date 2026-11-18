<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/orders.php';
allowMethods(['GET', 'POST', 'PUT']);
requireLogin();
$db = getDB();
$userId = sessionUserId();
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $id = positiveInt($_GET['id'] ?? null) ?? throw new ApiException('Mã đơn không hợp lệ.');
    $input = getJsonBody();
    if (($input['status'] ?? '') !== 'cancelled') throw new ApiException('Chỉ hỗ trợ yêu cầu hủy đơn.');
    transaction($db, function () use ($db, $id, $userId): void {
        lockUser($db, $userId);
        $stmt = $db->prepare('SELECT status FROM orders WHERE id = ? AND user_id = ? FOR UPDATE');
        $stmt->execute([$id, $userId]);
        $status = $stmt->fetchColumn();
        if ($status === false) throw new ApiException('Không tìm thấy đơn hàng.', 404);
        if ($status !== 'pending') throw new ApiException('Chỉ hủy được đơn đang chờ xác nhận.', 409);
        $stmt = $db->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? ORDER BY product_id');
        $stmt->execute([$id]);
        $update = $db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        foreach ($stmt->fetchAll() as $item) $update->execute([$item['quantity'], $item['product_id']]);
        $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'")->execute([$id, $userId]);
    });
    jsonResponse(true, getOrder($db, $id, $userId), 'Đã hủy đơn hàng.');
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
$name = inputString($input, 'receiver_name', 100, true);
$rawPhone = inputString($input, 'phone', 25, true);
$phone = normalizePhone($rawPhone);
$address = inputString($input, 'address', 255, true);
$note = inputString($input, 'note', 500);
$key = inputString($input, 'checkout_key', 64, true);
$errors = [];
if (mb_strlen($name) < 2) $errors['receiver_name'] = 'Vui lòng nhập họ tên.';
if ($phone === null) $errors['phone'] = 'Số điện thoại không hợp lệ (VD: 0912345678 hoặc +84 912 345 678).';
if (mb_strlen($address) < 5) $errors['address'] = 'Vui lòng nhập địa chỉ nhận hàng.';
if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/', $key)) $errors['checkout_key'] = 'Phiên đặt hàng không hợp lệ. Hãy tải lại trang.';
if ($errors) jsonResponse(false, ['errors' => $errors], 'Hãy kiểm tra họ tên, điện thoại và địa chỉ.', 422);
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
    $fee = shippingFee($total);
    $db->prepare('INSERT INTO orders (user_id, total_price, shipping_fee, receiver_name, phone, address, note, checkout_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$userId, $total, $fee, $name, $phone, $address, $note, $key]);
    $id = (int) $db->lastInsertId();
    $insert = $db->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price, product_name, image) VALUES (?, ?, ?, ?, ?, ?)');
    $stock = $db->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
    foreach ($items as $item) {
        $insert->execute([$id, $item['product_id'], $item['quantity'], $item['price'], $item['name'], $item['thumbnail']]);
        $stock->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
        if ($stock->rowCount() !== 1) throw new ApiException('Tồn kho vừa thay đổi. Hãy thử lại.', 409);
    }
    $db->prepare('DELETE ci FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = ?')->execute([$userId]);
    return [
        'order_id' => $id,
        'subtotal' => $total,
        'total_amount' => $total,
        'shipping_fee' => $fee,
        'payable_total' => $total + $fee,
    ];
});
jsonResponse(true, $result, 'Đặt hàng thành công.', 201);
