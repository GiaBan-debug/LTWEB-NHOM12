<?php
// backend/api/login.php
// Đăng nhập, session_regenerate_id, không trả hash

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/checkout.php';

applyApiCors(['POST', 'OPTIONS']);
if (authMode() !== 'legacy') throw new ApiException('Hãy đăng nhập bằng Firebase trên giao diện website.', 410);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$body = getJsonBody();
$phone = normalizePhone(inputString($body, 'phone', 30, true));
// Explicit test identities only. Never infer role from a real user's editable phone.
$fixtures = [
    '0916111111' => ['email' => 'legacy-admin@demo.invalid', 'name' => 'Admin thử nghiệm', 'role' => 'admin'],
    '0916222222' => ['email' => 'legacy-user@demo.invalid', 'name' => 'Khách thử nghiệm', 'role' => 'user'],
];
$fixture = $fixtures[$phone] ?? null;
if (!$fixture) throw new ApiException('Chế độ thử chỉ nhận 0916111111 (admin) hoặc 0916222222 (user).', 401);
$db = getDB();
// Stable rows keep demo carts/orders on subsequent logins. Existing accounts are untouched.
$user = transaction($db, static function () use ($db, $fixture, $phone): array {
    $db->prepare('INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, NULL, ?) ON DUPLICATE KEY UPDATE id = id')
        ->execute([$fixture['name'], $fixture['email'], $phone, $fixture['role']]);
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? FOR UPDATE');
    $stmt->execute([$fixture['email']]);
    $user = $stmt->fetch();
    if (!$user || $user['firebase_uid'] || $user['role'] !== $fixture['role']) {
        throw new ApiException('Tài khoản thử bị trùng dữ liệu. Hãy kiểm tra database.', 409);
    }
    return $user;
});

// Đổi session ID sau khi đăng nhập để tránh session fixation
establishSession($user, 'legacy');

// Lưu vào session — KHÔNG lưu hash password
jsonResponse(true, publicUser($user), 'Đăng nhập thành công');
