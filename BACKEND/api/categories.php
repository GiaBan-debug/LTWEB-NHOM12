<?php
// BACKEND/api/categories.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

$category = new Category();

if ($method === 'GET' && $id === null) {
    Response::json($category->getAll());

} elseif ($method === 'GET' && $id !== null) {
    $data = $category->getById($id);
    $data ? Response::json($data) : Response::json(['message' => 'Không tìm thấy'], 404);

} elseif ($method === 'POST') {
    AuthMiddleware::admin();
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = Validator::validate($data, ['name' => 'required|max:100']);
    if ($errors) { Response::json(['errors' => $errors], 422); exit; }
    $data['slug'] = strtolower(preg_replace('/\s+/', '-', trim($data['name'])));
    $id2 = $category->create($data);
    Response::json(['message' => 'Tạo danh mục thành công', 'id' => $id2], 201);

} elseif ($method === 'PUT' && $id !== null) {
    AuthMiddleware::admin();
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = Validator::validate($data, ['name' => 'required|max:100']);
    if ($errors) { Response::json(['errors' => $errors], 422); exit; }
    $data['slug'] = strtolower(preg_replace('/\s+/', '-', trim($data['name'])));
    $category->update($id, $data);
    Response::json(['message' => 'Cập nhật danh mục thành công']);

} elseif ($method === 'DELETE' && $id !== null) {
    AuthMiddleware::admin();
    $category->delete($id);
    Response::json(['message' => 'Xóa danh mục thành công']);

} else {
    http_response_code(405);
}