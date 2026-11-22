<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../backend/includes/bootstrap.php';
require_once __DIR__ . '/../backend/includes/firebase.php';
$options = getopt('', ['user-id:', 'email:', 'role:', 'link']);
$id = positiveInt($options['user-id'] ?? null);
$db = getDB();
if (!$id && is_string($options['email'] ?? null) && filter_var($options['email'], FILTER_VALIDATE_EMAIL)) {
    $lookup = $db->prepare('SELECT id FROM users WHERE email = ?');
    $lookup->execute([$options['email']]);
    $id = positiveInt($lookup->fetchColumn());
}
if (!$id) { fwrite(STDERR, "Usage: php scripts/firebase-user.php --user-id=ID --role=user|admin\nOr: supply a Firebase ID token via stdin with --user-id=ID --link\n"); exit(1); }
if (isset($options['link'])) {
    // Token must come through stdin, never a process argument/log/file in the repository.
    $token = trim(stream_get_contents(STDIN));
    $claims = firebaseVerifier()->verify($token);
    transaction($db, static function () use ($db, $id, $claims): void {
        $stmt = $db->prepare('SELECT id, email, firebase_uid FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user || !is_string($user['email']) || !is_string($claims['email'] ?? null) || strcasecmp($user['email'], $claims['email']) !== 0) throw new ApiException('UID phải thuộc đúng email của tài khoản cũ.');
        if ($user['firebase_uid'] && $user['firebase_uid'] !== $claims['sub']) throw new ApiException('Tài khoản đã liên kết với UID khác.', 409);
        if (($claims['email_verified'] ?? false) !== true) throw new ApiException('Cần email Firebase đã xác minh để liên kết tài khoản cũ.', 403);
        $db->prepare('UPDATE users SET firebase_uid=?, email_verified=1, password=NULL WHERE id=?')->execute([$claims['sub'], $id]);
    });
    echo "Linked verified Firebase identity; user id, role, carts and orders preserved.\n";
    exit;
}
$role = $options['role'] ?? '';
if (!in_array($role, ['user', 'admin'], true)) throw new ApiException('Role must be user or admin.');
$stmt = $db->prepare('SELECT id, firebase_uid FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user || !$user['firebase_uid']) throw new ApiException('Chỉ cấp quyền cho tài khoản Firebase đã đăng nhập và có UID trong MySQL.');
$db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
echo "Role updated for user #$id. Sessions re-read MySQL roles on the next request.\n";
