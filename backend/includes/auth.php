<?php
// Helper kiểm tra session và phân quyền

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Trả về thông tin user đang đăng nhập, hoặc null */
function getCurrentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Kiểm tra đã đăng nhập chưa. Nếu chưa → 401 */
function requireLogin(): void
{
    if (empty($_SESSION['user'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
        exit;
    }
}

/** Kiểm tra role admin. Nếu không phải → 403 */
function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
        exit;
    }
}

/** Lấy user_id từ session (đã requireLogin trước) */
function sessionUserId(): int
{
    return (int) ($_SESSION['user']['id'] ?? 0);
}