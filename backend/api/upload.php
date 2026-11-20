<?php
/**
 * backend/api/upload.php  — TV4
 * Upload ảnh sản phẩm từ trình duyệt (admin only).
 * Kiểm tra định dạng, dung lượng, bảo vệ thư mục upload.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

allowMethods(['POST']);
requireAdmin();

/* ---- Nhận file ---- */
$file = $_FILES['image'] ?? null;
if (!$file || !is_array($file)) {
    throw new ApiException('Không tìm thấy trường file "image".', 422);
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errMsg = match ($file['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File vượt giới hạn kích thước.',
        UPLOAD_ERR_PARTIAL  => 'Upload bị gián đoạn.',
        UPLOAD_ERR_NO_FILE  => 'Chưa chọn file.',
        default             => 'Upload thất bại (mã ' . $file['error'] . ').',
    };
    throw new ApiException($errMsg, 422);
}

/* ---- Kiểm tra dung lượng (5 MB) ---- */
$maxBytes = 5 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    throw new ApiException('Ảnh phải nhỏ hơn 5 MB.', 422);
}

/* ---- Kiểm tra định dạng bằng MIME thực (không tin tên file) ---- */
$mime = mime_content_type($file['tmp_name']);
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/avif' => 'avif',
];
if (!isset($allowed[$mime])) {
    throw new ApiException('Chỉ chấp nhận ảnh JPEG, PNG, WebP hoặc AVIF.', 422);
}
$ext = $allowed[$mime];

/* ---- Bảo vệ thư mục: đảm bảo đường dẫn không bị traversal ---- */
$uploadDir = realpath(dirname(__DIR__, 2) . '/uploads/products');
if ($uploadDir === false || !is_dir($uploadDir)) {
    throw new ApiException('Thư mục uploads/products chưa tồn tại.', 500);
}

/* ---- Lưu file với tên ngẫu nhiên ---- */
$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$dest     = $uploadDir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    throw new ApiException('Không thể lưu file. Kiểm tra quyền thư mục uploads/products.', 500);
}

/* Đặt quyền an toàn cho file (không cho thực thi) */
@chmod($dest, 0644);

jsonResponse(true, ['path' => 'products/' . $filename], 'Đã upload ảnh thành công.');
