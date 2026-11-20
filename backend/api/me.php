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
    $rawPhone = inputString($input, 'phone', 25);
    $address = inputString($input, 'address', 255);
    $errors = [];
    if (mb_strlen($name) < 2) $errors['name'] = 'Vui lòng nhập họ tên.';
    $phone = '';
    if ($rawPhone !== '') {
        try {
            $phone = normalizePhone($rawPhone);
        } catch (ApiException $e) {
            $errors['phone'] = 'Số điện thoại không hợp lệ (VD: 0912345678 hoặc +84 912 345 678).';
        }
    }
    if ($address !== '' && mb_strlen($address) < 5) $errors['address'] = 'Địa chỉ nhận hàng cần ít nhất 5 ký tự.';
    if ($errors) jsonResponse(false, ['errors' => $errors], 'Hãy kiểm tra thông tin hồ sơ.', 422);
    getDB()->prepare('UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?')->execute([$name, $phone, $address, $user['id']]);
    $user = getCurrentUser();
}

jsonResponse(true, publicUser($user), 'OK');
