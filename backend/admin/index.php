<?php
// Dashboard Admin — kiểm tra session + role ở server (không chỉ ẩn nút frontend).

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>401</title>';
    echo '<p>Vui lòng đăng nhập bằng tài khoản Admin.</p>';
    echo '<p><a href="../../frontend/pages/login.html">Đăng nhập</a></p>';
    exit;
}

if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>403</title>';
    echo '<p>Không có quyền truy cập trang Admin.</p>';
    echo '<p><a href="../../frontend/index.html">Về trang chủ</a></p>';
    exit;
}

$name = htmlspecialchars((string) ($user['name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin — SHOP DEMO</title>
    <style>
      body { font-family: Arial, sans-serif; max-width: 720px; margin: 40px auto; color: #0f172a; }
      a { color: #2563eb; }
    </style>
  </head>
  <body>
    <h1>Bảng điều khiển Admin</h1>
    <p>Xin chào, <?php echo $name; ?> (role: admin).</p>
    <p>Trang này chỉ mở khi server xác nhận session + role. CRUD sản phẩm/danh mục do Xuân Anh phụ trách.</p>
    <p><a href="../../frontend/index.html">Về trang chủ</a></p>
  </body>
</html>
