<?php
// backend/api/me.php — user đang đăng nhập (không trả password/hash)

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

applyApiCors(['GET', 'OPTIONS']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, null, 'Method not allowed', 405);
}

$user = getCurrentUser();
if (!$user) {
    jsonResponse(false, null, 'Vui lòng đăng nhập', 401);
}

jsonResponse(true, [
    'id'    => $user['id'] ?? null,
    'name'  => $user['name'] ?? '',
    'email' => $user['email'] ?? '',
    'role'  => $user['role'] ?? 'user',
], 'OK');
