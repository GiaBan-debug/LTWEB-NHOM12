<?php
// backend/api/me.php — user đang đăng nhập (không trả password/hash)

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

applyApiCors(['GET', 'PUT', 'OPTIONS']);

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'PUT'], true)) {
    jsonResponse(false, null, 'Method not allowed', 405);
}

$user = getCurrentUser();
if (!$user) {
    jsonResponse(false, null, 'Vui lòng đăng nhập', 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = getJsonBody();
    $name = inputString($input, 'name', 100, true);
    $phone = inputString($input, 'phone', 20);
    $address = inputString($input, 'address', 255);
    getDB()->prepare('UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?')->execute([$name, $phone, $address, $user['id']]);
    $_SESSION['user']['name'] = $user['name'] = $name;
    $_SESSION['user']['phone'] = $user['phone'] = $phone;
    $_SESSION['user']['address'] = $user['address'] = $address;
}

// Lấy thông tin mới nhất từ database
$stmt = getDB()->prepare('SELECT id, name, email, role, phone, address, firebase_uid FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$fresh = $stmt->fetch() ?: $user;

jsonResponse(true, [
    'id'           => $fresh['id'] ?? null,
    'name'         => $fresh['name'] ?? '',
    'email'        => $fresh['email'] ?? '',
    'role'         => $fresh['role'] ?? 'user',
    'phone'        => $fresh['phone'] ?? '',
    'address'      => $fresh['address'] ?? '',
    'firebase_uid' => $fresh['firebase_uid'] ?? null,
], 'OK');
