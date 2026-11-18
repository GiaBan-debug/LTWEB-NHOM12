<?php
// backend/api/login.php
// Đăng nhập, session_regenerate_id, không trả hash

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

applyApiCors(['POST', 'OPTIONS']);
if (authMode() !== 'legacy') throw new ApiException('Hãy đăng nhập bằng Firebase trên giao diện website.', 410);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$body     = getJsonBody();
$email    = inputString($body, 'email', 150, true);
$password = $body['password'] ?? '';
if (!is_string($password) || strlen($password) > 72) jsonResponse(false, null, 'Mật khẩu không hợp lệ.', 422);

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    jsonResponse(false, null, 'Email hoặc mật khẩu không hợp lệ', 422);
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND firebase_uid IS NULL");
$stmt->execute([$email]);
$user = $stmt->fetch();

// Dùng password_verify để so sánh — không để lộ lý do sai cụ thể
if (!$user || !$user['password'] || !password_verify($password, $user['password'])) {
    jsonResponse(false, null, 'Email hoặc mật khẩu không đúng', 401);
}

// Đổi session ID sau khi đăng nhập để tránh session fixation
establishSession($user, 'legacy');

// Lưu vào session — KHÔNG lưu hash password
jsonResponse(true, publicUser($user), 'Đăng nhập thành công');
