<?php
// BACKEND/api/products.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

$method = $_SERVER['REQUEST_METHOD'];
// Lay id tu ?id=... hoac segment URL
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

$controller = new ProductController();

match (true) {
    $method === 'GET'    && $id === null => $controller->index(),
    $method === 'GET'    && $id !== null => $controller->show($id),
    $method === 'POST'                   => (AuthMiddleware::admin() && $controller->store()),
    $method === 'PUT'    && $id !== null => (AuthMiddleware::admin() && $controller->update($id)),
    $method === 'DELETE' && $id !== null => (AuthMiddleware::admin() && $controller->destroy($id)),
    default => http_response_code(405),
};