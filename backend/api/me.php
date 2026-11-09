<?php
// backend/api/me.php
// Trả user đang đăng nhập từ PHP session (không trả password/hash)

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;
if (!$user || !is_array($user)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'OK',
    'data' => [
        'id'    => $user['id'] ?? null,
        'name'  => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'role'  => $user['role'] ?? 'user',
    ],
], JSON_UNESCAPED_UNICODE);
