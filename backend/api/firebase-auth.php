<?php
// backend/api/firebase-auth.php
// Cầu nối xác thực Firebase Authentication sang PHP Session

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

applyApiCors(['POST', 'OPTIONS']);
allowMethods(['POST']);

$input = getJsonBody();
$db = getDB();

$firebaseUid = null;
$email = null;
$name = null;

// 1. Nhận Firebase ID Token hoặc Firebase UID (cho môi trường demo/test)
if (!empty($input['id_token']) && is_string($input['id_token'])) {
    $parts = explode('.', $input['id_token']);
    if (count($parts) !== 3) {
        throw new ApiException('Firebase ID Token không hợp lệ (sai cấu trúc JWT).', 401);
    }

    $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
    if ($payloadJson === false) {
        throw new ApiException('Không thể giải mã payload Firebase JWT.', 401);
    }

    $payload = json_decode($payloadJson, true);
    if (!is_array($payload) || empty($payload['sub'])) {
        throw new ApiException('Firebase token thiếu định danh người dùng (sub).', 401);
    }

    // Kiểm tra thời hạn token (nếu có trường exp)
    if (isset($payload['exp']) && is_numeric($payload['exp']) && (int)$payload['exp'] < time()) {
        throw new ApiException('Firebase ID Token đã hết hạn. Vui lòng đăng nhập lại.', 401);
    }

    $firebaseUid = (string) $payload['sub'];
    $email = isset($payload['email']) && is_string($payload['email']) ? trim($payload['email']) : null;
    $name = isset($payload['name']) && is_string($payload['name']) ? trim($payload['name']) : null;
} elseif (!empty($input['firebase_uid']) && is_string($input['firebase_uid'])) {
    // Luồng demo/test trực tiếp với Firebase UID
    $firebaseUid = trim($input['firebase_uid']);
    $email = isset($input['email']) && is_string($input['email']) ? trim($input['email']) : null;
    $name = isset($input['name']) && is_string($input['name']) ? trim($input['name']) : null;
} else {
    throw new ApiException('Yêu cầu id_token hoặc firebase_uid hợp lệ.', 422);
}

if ($firebaseUid === '' || mb_strlen($firebaseUid) > 128) {
    throw new ApiException('Firebase UID không hợp lệ (tối đa 128 ký tự).', 422);
}

// 2. Tìm người dùng trong MySQL theo firebase_uid
$stmt = $db->prepare('SELECT id, name, email, role, phone, address, firebase_uid FROM users WHERE firebase_uid = ?');
$stmt->execute([$firebaseUid]);
$user = $stmt->fetch();

if (!$user) {
    // Nếu chưa liên kết UID nhưng có email: kiểm tra trùng lặp để bảo vệ tài khoản cũ
    if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $checkStmt = $db->prepare('SELECT id, firebase_uid FROM users WHERE email = ?');
        $checkStmt->execute([$email]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            // Không tự động bind nếu tài khoản cũ chưa được xác minh liên kết (chống email collision)
            throw new ApiException('Email đã tồn tại trong hệ thống MySQL. Vui lòng đăng nhập tài khoản hiện tại để liên kết Firebase.', 409);
        }

        // Tạo tài khoản mới từ Firebase
        $displayName = ($name !== null && mb_strlen($name) >= 2) ? mb_substr($name, 0, 100) : 'Khách hàng';
        $insert = $db->prepare('INSERT INTO users (name, email, firebase_uid, password, role) VALUES (?, ?, ?, NULL, "user")');
        $insert->execute([$displayName, $email, $firebaseUid]);
        $newId = (int) $db->lastInsertId();

        // Tạo giỏ hàng mặc định
        $db->prepare('INSERT IGNORE INTO carts (user_id) VALUES (?)')->execute([$newId]);

        $user = [
            'id' => $newId,
            'name' => $displayName,
            'email' => $email,
            'role' => 'user',
            'phone' => '',
            'address' => '',
            'firebase_uid' => $firebaseUid,
        ];
    } else {
        throw new ApiException('Không tìm thấy tài khoản tương ứng với Firebase UID này.', 404);
    }
}

// 3. Khởi tạo PHP Session ứng dụng
session_regenerate_id(true);
$_SESSION['user'] = [
    'id'    => (int) $user['id'],
    'name'  => (string) $user['name'],
    'email' => (string) $user['email'],
    'role'  => (string) $user['role'],
];

// 4. Trả kết quả JSON an toàn
jsonResponse(true, [
    'id'           => (int) $user['id'],
    'name'         => (string) $user['name'],
    'email'        => (string) $user['email'],
    'role'         => (string) $user['role'],
    'phone'        => (string) ($user['phone'] ?? ''),
    'address'      => (string) ($user['address'] ?? ''),
    'firebase_uid' => (string) $user['firebase_uid'],
], 'Đăng nhập Firebase thành công.');
