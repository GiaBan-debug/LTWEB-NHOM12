<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

/** App session is distinct from Firebase persistence; no ID token is stored here. */
function startAppSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;
    session_name('BTA' . substr(hash('sha256', appConfig('DB_NAME', 'ltweb_nhom12') . ':' . authMode()), 0, 16));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}
startAppSession();

function publicUser(array $user): array
{
    return [
        'id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role'],
        'phone' => $user['phone'] ?? '', 'address' => $user['address'] ?? '',
        'email_verified' => (bool) ($user['email_verified'] ?? false),
        'auth_provider' => $_SESSION['auth_mode'] ?? authMode(),
    ];
}
function establishSession(array $user, string $mode, ?int $tokenExpires = null): void
{
    session_regenerate_id(true);
    $ttl = max(300, min(28800, (int) appConfig('SESSION_TTL_SECONDS', '3600')));
    $_SESSION['user'] = ['id' => (int) $user['id']];
    $_SESSION['auth_mode'] = $mode;
    $_SESSION['firebase_uid'] = $mode === 'firebase' ? $user['firebase_uid'] : null;
    $_SESSION['firebase_project'] = $mode === 'firebase' ? appConfig('FIREBASE_PROJECT_ID', 'bta-store') : null;
    $_SESSION['expires_at'] = min(time() + $ttl, $tokenExpires ?? PHP_INT_MAX);
}
function getCurrentUser(): ?array
{
    if (empty($_SESSION['user']['id'])) return null;
    if (($_SESSION['expires_at'] ?? 0) <= time() || ($_SESSION['auth_mode'] ?? '') !== authMode()
        || (authMode() === 'firebase' && ($_SESSION['firebase_project'] ?? '') !== appConfig('FIREBASE_PROJECT_ID', 'bta-store'))) {
        unset($_SESSION['user'], $_SESSION['expires_at'], $_SESSION['firebase_uid']);
        return null;
    }
    // Re-read roles and identity so demotion/deletion takes effect on every request.
    $stmt = getDB()->prepare('SELECT id, name, email, role, phone, address, firebase_uid, email_verified FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user']['id']]);
    $user = $stmt->fetch();
    if (!$user || (authMode() === 'firebase' && (!$user['firebase_uid'] || $user['firebase_uid'] !== ($_SESSION['firebase_uid'] ?? null)))
        || (authMode() === 'legacy' && $user['firebase_uid'])) {
        unset($_SESSION['user']);
        return null;
    }
    return $user;
}
function requireLogin(): void
{
    if (!getCurrentUser()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập lại.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
function requireAdmin(): void
{
    requireLogin();
    if ((getCurrentUser()['role'] ?? '') !== 'admin') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
function sessionUserId(): int { return (int) ($_SESSION['user']['id'] ?? 0); }
