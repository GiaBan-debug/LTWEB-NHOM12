<?php
declare(strict_types=1);

function getOrder(PDO $db, int $id, ?int $userId = null, bool $withLogs = false): array
{
    $sql = 'SELECT id, user_id, total_price AS total_amount, shipping_fee,
                   status, receiver_name, phone, address, note, created_at
            FROM orders WHERE id = ?';
    $params = [$id];
    if ($userId !== null) {
        $sql    .= ' AND user_id = ?';
        $params[] = $userId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $order = $stmt->fetch();
    if (!$order) throw new ApiException('Không tìm thấy đơn hàng.', 404);

    $stmt = $db->prepare(
        'SELECT product_id, product_name AS name, image,
                unit_price AS price, quantity, unit_price * quantity AS subtotal
         FROM order_items WHERE order_id = ? ORDER BY id'
    );
    $stmt->execute([$id]);
    $order['items']          = $stmt->fetchAll();
    $order['payment_method'] = 'COD';
    $order['subtotal']       = (int) $order['total_amount'];

    if ($withLogs) {
        try {
            $stmt = $db->prepare(
                'SELECT from_status, to_status, handler_name, cancel_reason, created_at
                 FROM order_status_logs WHERE order_id = ? ORDER BY id'
            );
            $stmt->execute([$id]);
            $order['status_logs'] = $stmt->fetchAll();
        } catch (PDOException) {
            $order['status_logs'] = [];
        }
    }

    return $order;
}

function cancelCustomerOrder(PDO $db, int $id, int $userId): void
{
    transaction($db, static function () use ($db, $id, $userId): void {
        $stmt = $db->prepare('SELECT status FROM orders WHERE id = ? AND user_id = ? FOR UPDATE');
        $stmt->execute([$id, $userId]);
        $status = $stmt->fetchColumn();
        if ($status === false)      throw new ApiException('Không tìm thấy đơn hàng.', 404);
        if ($status === 'cancelled') return;
        if ($status !== 'pending')  throw new ApiException('Chỉ có thể tự hủy đơn đang chờ xác nhận. Vui lòng liên hệ cửa hàng.', 409);

        $stmt = $db->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? ORDER BY product_id');
        $stmt->execute([$id]);
        $stock = $db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        foreach ($stmt->fetchAll() as $item) $stock->execute([$item['quantity'], $item['product_id']]);

        $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$id]);

        try {
            $db->prepare(
                "INSERT INTO order_status_logs (order_id, from_status, to_status, handler_name, cancel_reason)
                 VALUES (?, 'pending', 'cancelled', 'Khách hàng', 'Khách tự hủy')"
            )->execute([$id]);
        } catch (PDOException) {}
    });
}
