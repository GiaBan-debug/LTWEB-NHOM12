<?php
declare(strict_types=1);

function reviewStats(PDO $db, int $productId): array
{
    $stmt = $db->prepare('SELECT COUNT(*) AS rating_count, COALESCE(ROUND(AVG(rating), 1), 0) AS rating_average FROM product_reviews WHERE product_id = ?');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    return ['rating_count' => (int) $row['rating_count'], 'rating_average' => (float) $row['rating_average']];
}

function completedPurchase(PDO $db, int $userId, int $productId): ?int
{
    $stmt = $db->prepare("SELECT o.id FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'completed' ORDER BY o.id DESC LIMIT 1");
    $stmt->execute([$userId, $productId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function ownReview(PDO $db, int $userId, int $productId): ?array
{
    $stmt = $db->prepare('SELECT id, product_id, rating, feedback, created_at, updated_at FROM product_reviews WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    foreach (['id', 'product_id', 'rating'] as $key) $row[$key] = (int) $row[$key];
    return $row;
}
