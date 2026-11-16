<?php
declare(strict_types=1);
function getOrder(PDO $db, int $id, ?int $userId = null): array
{
    $sql = 'SELECT id, user_id, total_price AS total_amount, status, receiver_name, phone, address, note, created_at FROM orders WHERE id = ?';
    $params = [$id];
    if ($userId !== null) { $sql .= ' AND user_id = ?'; $params[] = $userId; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $order = $stmt->fetch();
    if (!$order) throw new ApiException('Không tìm thấy đơn hàng.', 404);
    $stmt = $db->prepare('SELECT product_id, product_name AS name, image, unit_price AS price, quantity, unit_price * quantity AS subtotal FROM order_items WHERE order_id = ? ORDER BY id');
    $stmt->execute([$id]);
    $order['items'] = $stmt->fetchAll();
    $order['payment_method'] = 'COD';
    $order['shipping_fee'] = 0;
    return $order;
}
