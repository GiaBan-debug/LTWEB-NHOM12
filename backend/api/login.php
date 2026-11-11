<?php
// backend/api/login.php
// Đăng nhập, session_regenerate_id, không trả hash

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

applyApiCors(['POST', 'OPTIONS']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$body     = getJsonBody();
$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    jsonResponse(false, null, 'Email hoặc mật khẩu không hợp lệ', 422);
}

$db   = getDB();
$stmt = $db->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

// Dùng password_verify để so sánh — không để lộ lý do sai cụ thể
if (!$user || !password_verify($password, $user['password'])) {
    jsonResponse(false, null, 'Email hoặc mật khẩu không đúng', 401);
}

// Đổi session ID sau khi đăng nhập để tránh session fixation
session_regenerate_id(true);

// Lưu vào session — KHÔNG lưu hash password
$_SESSION['user'] = [
    'id'    => $user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $user['role'],
];

jsonResponse(true, [
    'id'    => $user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $user['role'],
], 'Đăng nhập thành công');