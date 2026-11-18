<?php
declare(strict_types=1);
require_once __DIR__ . '/checkout.php';
function fetchCart(PDO $db, int $userId): array
{
    $stmt = $db->prepare('SELECT p.id AS product_id, p.name, p.thumbnail AS image, p.price, p.stock, p.status, ci.quantity FROM cart_items ci JOIN carts c ON c.id = ci.cart_id JOIN products p ON p.id = ci.product_id WHERE c.user_id = ? ORDER BY ci.id DESC');
    $stmt->execute([$userId]);
    $items = [];
    $total = $count = 0;
    foreach ($stmt->fetchAll() as $row) {
        foreach (['product_id', 'stock', 'quantity', 'price'] as $key) $row[$key] = (int) $row[$key];
        $row['subtotal'] = $row['price'] * $row['quantity'];
        $row['available'] = $row['status'] === 'active' && $row['stock'] >= $row['quantity'];
        $total += $row['subtotal'];
        $count += $row['quantity'];
        $items[] = $row;
    }
    return ['items' => $items, 'total_items' => $count, 'is_empty' => !$items, 'can_checkout' => (bool) ($items && !in_array(false, array_column($items, 'available'), true))] + orderAmounts($total);
}
