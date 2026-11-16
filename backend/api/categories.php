<?php
require_once __DIR__ . '/../includes/bootstrap.php';
allowMethods(['GET']);
$db = getDB();
jsonResponse(true, $db->query("SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active' GROUP BY c.id ORDER BY c.id")->fetchAll());
