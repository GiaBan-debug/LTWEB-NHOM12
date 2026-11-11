<?php
// backend/api/register.php
// Đăng ký tài khoản, password_hash, validate email

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

applyApiCors(['POST', 'OPTIONS']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$body = getJsonBody();

$name     = trim($body['name']     ?? '');
$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');

// --- Validate ---
$errors = [];
if ($name === '')                            $errors[] = 'Tên không được để trống';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ';
if (strlen($password) < 6)                  $errors[] = 'Mật khẩu phải ít nhất 6 ký tự';

if ($errors) {
    jsonResponse(false, null, implode('; ', $errors), 422);
}

$db = getDB();

// Kiểm tra email trùng
$check = $db->prepare("SELECT id FROM users WHERE email = ?");
$check->execute([$email]);
if ($check->fetch()) {
    jsonResponse(false, null, 'Email đã được sử dụng', 409);
}

// Hash mật khẩu — không lưu rõ
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $db->prepare(
    "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')"
);
$stmt->execute([$name, $email, $hash]);
$newId = (int)$db->lastInsertId();

jsonResponse(true, ['id' => $newId], 'Đăng ký thành công', 201);