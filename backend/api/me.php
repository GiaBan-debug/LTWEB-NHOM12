<?php
// backend/api/me.php — user đang đăng nhập (không trả password/hash)

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/checkout.php';

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
    $phone = array_key_exists('phone', $input) ? inputString($input, 'phone', 30) : $user['phone'];
    $phone = $phone === '' ? '' : normalizePhone($phone);
    $address = array_key_exists('address', $input) ? inputString($input, 'address', 255) : $user['address'];
    getDB()->prepare('UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?')->execute([$name, $phone, $address, $user['id']]);
    $user = getCurrentUser();
}

jsonResponse(true, publicUser($user), 'OK');
