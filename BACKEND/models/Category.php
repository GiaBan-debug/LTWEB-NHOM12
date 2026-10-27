<?php
// BACKEND/models/Category.php

require_once __DIR__ . '/../config/database.php';

class Category {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function getAll(): array {
        $stmt = $this->db->query("SELECT * FROM categories ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public function getById(int $id): array|false {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getBySlug(string $slug): array|false {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)"
        );
        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare(
            "UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?"
        );
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $id,
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM categories WHERE id = ?");
        return $stmt->execute([$id]);
    }
}