<?php
// BACKEND/models/ProductImage.php

require_once __DIR__ . '/../config/database.php';

class ProductImage {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function getByProduct(int $productId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC"
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public function add(int $productId, string $path, int $order = 0): int {
        $stmt = $this->db->prepare(
            "INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)"
        );
        $stmt->execute([$productId, $path, $order]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM product_images WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function deleteByProduct(int $productId): bool {
        $stmt = $this->db->prepare("DELETE FROM product_images WHERE product_id = ?");
        return $stmt->execute([$productId]);
    }
}