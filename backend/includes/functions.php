<?php
// backend/includes/functions.php
// Hàm tiện ích dùng chung

declare(strict_types=1);

/** Same-origin by default; allow cross-origin credentials only for configured exact origins. */
function applyApiCors(array $methods = ['GET', 'POST', 'OPTIONS']): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = array_filter(array_map('trim', explode(',', appConfig('APP_ALLOWED_ORIGINS'))));
    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
        header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
        header('Vary: Origin');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/** Trả JSON chuẩn theo đặc tả nhóm */
function jsonResponse(bool $success, mixed $data = null, string $message = '', int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $body = ['success' => $success, 'message' => $message];
    if ($data !== null) $body['data'] = $data;
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Tạo slug từ tiếng Việt */
function makeSlug(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $map = [
        'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ả'=>'a','ạ'=>'a',
        'ắ'=>'a','ặ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'ấ'=>'a','ậ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a',
        'è'=>'e','é'=>'e','ê'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e',
        'ế'=>'e','ệ'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','î'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ọ'=>'o','ỏ'=>'o',
        'ố'=>'o','ộ'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o',
        'ớ'=>'o','ợ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u',
        'ứ'=>'u','ự'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u',
        'ý'=>'y','ỳ'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',
        'đ'=>'d',
    ];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', trim($text));
    return $text;
}

/** Sanitize chuỗi đầu vào */
function clean(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/** Lấy body JSON từ request */
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $value = json_decode($raw ?: '{}');
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($value)) {
        jsonResponse(false, null, 'Body phải là JSON object hợp lệ.', 422);
    }
    return (array) $value;
}

function inputString(array $input, string $key, int $max = 255, bool $required = false): string
{
    $value = $input[$key] ?? '';
    if (!is_string($value)) jsonResponse(false, null, "$key phải là chuỗi.", 422);
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value) > $max) {
        jsonResponse(false, null, "$key không hợp lệ (tối đa $max ký tự).", 422);
    }
    return $value;
}

function positiveInt(mixed $value): ?int
{
    if (!is_int($value) && !is_string($value)) return null;
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    return $id === false ? null : $id;
}

/** Chuẩn hóa SĐT Việt Nam: bỏ khoảng trắng/dấu chấm/gạch, nhận 0…, +84… và 84…. */
function normalizePhone(string $raw): ?string
{
    $phone = preg_replace('/[\s.\-()]/', '', trim($raw)) ?? '';
    if ($phone === '') return null;
    if (preg_match('/^84\d{9}$/', $phone)) $phone = '+' . $phone;
    if (preg_match('/^\+840(\d{9})$/', $phone, $match)) $phone = '0' . $match[1];
    if (preg_match('/^840(\d{9})$/', $phone, $match)) $phone = '0' . $match[1];
    return preg_match('/^(0|\+84)\d{9}$/', $phone) ? $phone : null;
}

/** Phí giao hàng demo: miễn phí từ 2.000.000 ₫, đơn có hàng dưới mức đó 30.000 ₫. */
function shippingFee(int $subtotal): int
{
    if ($subtotal <= 0) return 0;
    return $subtotal >= 2000000 ? 0 : 30000;
}
