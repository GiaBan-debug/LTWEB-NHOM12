<?php
// backend/includes/functions.php
// Hàm tiện ích dùng chung

declare(strict_types=1);

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
    return json_decode($raw ?: '{}', true) ?? [];
}