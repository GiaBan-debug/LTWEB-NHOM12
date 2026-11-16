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
    getDB()->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $user['id']]);
    $_SESSION['user']['name'] = $user['name'] = $name;
}

jsonResponse(true, [
    'id'    => $user['id'] ?? null,
    'name'  => $user['name'] ?? '',
    'email' => $user['email'] ?? '',
    'role'  => $user['role'] ?? 'user',
], 'OK');
