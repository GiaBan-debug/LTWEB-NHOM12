<?php
// backend/api/logout.php
// Hủy session hoàn toàn

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if (session_status() === PHP_SESSION_NONE) session_start();

// Xóa toàn bộ session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

require_once __DIR__ . '/../includes/functions.php';
jsonResponse(true, null, 'Đăng xuất thành công');