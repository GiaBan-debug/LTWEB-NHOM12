<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

final class ApiException extends RuntimeException
{
    public function __construct(string $message, public int $status = 422) { parent::__construct($message); }
}

set_exception_handler(static function (Throwable $error): void {
    $status = $error instanceof ApiException ? $error->status : 500;
    if ($status === 500) error_log((string) $error);
    jsonResponse(false, null, $status === 500 ? 'Có lỗi máy chủ. Vui lòng thử lại.' : $error->getMessage(), $status);
});

applyApiCors(['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD', 'OPTIONS'], true)) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new ApiException('Phiên biểu mẫu đã hết hạn. Hãy tải lại trang.', 403);
    }
}

function allowMethods(array $methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        throw new ApiException('Phương thức không được hỗ trợ.', 405);
    }
}

function transaction(PDO $db, callable $operation): mixed
{
    $db->beginTransaction();
    try {
        $result = $operation();
        $db->commit();
        return $result;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

// Serialize changes to the same user's cart, including checkout.
function lockUser(PDO $db, int $userId): void
{
    $stmt = $db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) throw new ApiException('Tài khoản không tồn tại.', 401);
}
