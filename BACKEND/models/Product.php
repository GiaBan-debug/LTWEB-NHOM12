<?php
// BACKEND/models/Product.php

require_once __DIR__ . '/../config/database.php';

class Product {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    /** Lay danh sach san pham co phan trang va loc */
    public function getAll(array $filters = [], int $page = 1, int $limit = 12): array {
        $where  = ['p.status = "active"'];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[]  = 'p.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (!empty($filters['search'])) {
            $where[]  = 'p.name LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['min_price'])) {
            $where[]  = 'p.price >= ?';
            $params[] = (float) $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $where[]  = 'p.price <= ?';
            $params[] = (float) $filters['max_price'];
        }

        $whereSQL = implode(' AND ', $where);
        $offset   = ($page - 1) * $limit;

        // Dem tong so ban ghi
        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM products p WHERE {$whereSQL}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Lay du lieu trang hien tai
        $stmt = $this->db->prepare("
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE {$whereSQL}
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([...$params, $limit, $offset]);
        $items = $stmt->fetchAll();

        return [
            'data'        => $items,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ];
    }

    public function getById(int $id): array|false {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        if ($product) {
            $product['images'] = $this->getImages($id);
        }

        return $product;
    }

    public function getBySlug(string $slug): array|false {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.slug = ?
        ");
        $stmt->execute([$slug]);
        $product = $stmt->fetch();

        if ($product) {
            $product['images'] = $this->getImages($product['id']);
        }

        return $product;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO products
                (category_id, name, slug, description, price, stock, thumbnail, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['category_id'],
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['price'],
            $data['stock'] ?? 0,
            $data['thumbnail'] ?? null,
            $data['status'] ?? 'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE products
            SET category_id = ?, name = ?, slug = ?, description = ?,
                price = ?, stock = ?, thumbnail = ?, status = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['category_id'],
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['price'],
            $data['stock'] ?? 0,
            $data['thumbnail'] ?? null,
            $data['status'] ?? 'active',
            $id,
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getImages(int $productId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC"
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public function addImage(int $productId, string $path, int $order = 0): int {
        $stmt = $this->db->prepare(
            "INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)"
        );
        $stmt->execute([$productId, $path, $order]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteImage(int $imageId): bool {
        $stmt = $this->db->prepare("DELETE FROM product_images WHERE id = ?");
        return $stmt->execute([$imageId]);
    }
}