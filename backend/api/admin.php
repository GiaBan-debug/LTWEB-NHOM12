<?php
/**
 * backend/api/admin.php  — TV4
 * Admin API: dashboard, products, categories, orders.
 * TV4 additions:
 *  - specs / warranty_months trên sản phẩm
 *  - upload ảnh từ trình duyệt (xem upload.php)
 *  - sửa & xóa danh mục (có kiểm tra sản phẩm liên quan)
 *  - lọc đơn theo trạng thái + ngày
 *  - lịch sử đổi trạng thái + lý do hủy
 *  - kiểm tra hoàn tồn kho đúng 1 lần khi hủy (transaction)
 *  - bỏ carrier / tracking_code / estimated_delivery khỏi luồng admin
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/orders.php';

allowMethods(['GET', 'POST', 'PUT', 'DELETE']);
requireAdmin();

$db       = getDB();
$adminUser = getCurrentUser();          // để lưu tên người xử lý đơn
$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? 'dashboard';
$id       = positiveInt($_GET['id'] ?? null);

if (!in_array($resource, ['dashboard', 'products', 'categories', 'orders'], true)) {
    throw new ApiException('Không tìm thấy chức năng.', 404);
}

/* ==============================================================
   GET — đọc dữ liệu
   ============================================================== */
if ($method === 'GET') {

    /* ---- Dashboard ---- */
    if ($resource === 'dashboard') {
        jsonResponse(true, [
            'products'         => (int) $db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
            'orders'           => (int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'pending'          => (int) $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
            /* Doanh thu = tổng (total_price + shipping_fee) của đơn hoàn thành */
            'revenue'          => (int) $db->query("SELECT COALESCE(SUM(total_price + shipping_fee), 0) FROM orders WHERE status = 'completed'")->fetchColumn(),
            'revenue_note'     => 'Tổng (tạm tính + phí giao) của tất cả đơn có trạng thái "hoàn thành".',
        ]);
    }

    /* ---- Danh mục (trả kèm product_count) ---- */
    if ($resource === 'categories') {
        $rows = $db->query(
            'SELECT c.*, COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.status = \'active\'
             GROUP BY c.id ORDER BY c.id'
        )->fetchAll();
        jsonResponse(true, $rows);
    }

    /* ---- Chi tiết đơn hàng ---- */
    if ($resource === 'orders' && $id) {
        jsonResponse(true, getOrder($db, $id, null, true));   // withLogs = true
    }

    /* ---- Chi tiết sản phẩm ---- */
    if ($resource === 'products' && $id) {
        $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) throw new ApiException('Không tìm thấy sản phẩm.', 404);
        jsonResponse(true, $product);
    }

    /* ---- Danh sách phân trang ---- */
    $page   = min(100000, positiveInt($_GET['page'] ?? 1) ?? 1);
    $offset = ($page - 1) * 20;
    $search = inputString($_GET, 'search', 150);

    $params = [];
    $where  = '1=1';

    if ($resource === 'products') {
        if ($search !== '') {
            $where    = 'name LIKE ?';
            $params[] = "%$search%";
        }
    } else {
        // orders — lọc thêm theo trạng thái và ngày
        $conditions = ['1=1'];

        if ($search !== '') {
            $conditions[] = '(receiver_name LIKE ? OR CAST(id AS CHAR) = ?)';
            $params[]     = "%$search%";
            $params[]     = $search;
        }

        $statusFilter = inputString($_GET, 'status', 20);
        $validStatuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
        if ($statusFilter !== '' && in_array($statusFilter, $validStatuses, true)) {
            $conditions[] = 'status = ?';
            $params[]     = $statusFilter;
        }

        $dateFrom = inputString($_GET, 'date_from', 10);
        $dateTo   = inputString($_GET, 'date_to', 10);
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $conditions[] = 'DATE(created_at) >= ?';
            $params[]     = $dateFrom;
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $conditions[] = 'DATE(created_at) <= ?';
            $params[]     = $dateTo;
        }

        $where = implode(' AND ', $conditions);
    }

    $count = $db->prepare("SELECT COUNT(*) FROM $resource WHERE $where");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM $resource WHERE $where ORDER BY id DESC LIMIT 20 OFFSET $offset");
    $stmt->execute($params);
    jsonResponse(true, [
        'items'       => $stmt->fetchAll(),
        'page'        => $page,
        'total'       => $total,
        'total_pages' => max(1, (int) ceil($total / 20)),
    ]);
}

/* ==============================================================
   DELETE — xóa / ngừng bán
   ============================================================== */
if ($method === 'DELETE') {

    /* ---- Ngừng bán sản phẩm (soft delete) ---- */
    if ($resource === 'products') {
        if (!$id) throw new ApiException('ID không hợp lệ.');
        $db->prepare("UPDATE products SET status = 'inactive' WHERE id = ?")->execute([$id]);
        jsonResponse(true, null, 'Đã ngừng bán sản phẩm; lịch sử đơn vẫn được giữ.');
    }

    /* ---- Xóa danh mục (có kiểm tra sản phẩm liên quan) ---- */
    if ($resource === 'categories') {
        if (!$id) throw new ApiException('ID không hợp lệ.');

        // Kiểm tra sản phẩm đang dùng danh mục này
        $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$id]);
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            throw new ApiException("Danh mục còn $count sản phẩm. Hãy chuyển hoặc xóa sản phẩm trước.", 409);
        }

        $stmt = $db->prepare('SELECT id FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) throw new ApiException('Không tìm thấy danh mục.', 404);

        $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        jsonResponse(true, null, 'Đã xóa danh mục.');
    }
}

/* ==============================================================
   POST / PUT — tạo / cập nhật
   ============================================================== */
$input = getJsonBody();

/* ---- Sản phẩm: thêm / sửa ---- */
if ($resource === 'products' && in_array($method, ['POST', 'PUT'], true)) {
    if ($method === 'PUT' && !$id) throw new ApiException('ID không hợp lệ.');

    $name       = inputString($input, 'name', 200, true);
    $categoryId = positiveInt($input['category_id'] ?? null) ?? throw new ApiException('Danh mục không hợp lệ.');
    $price      = filter_var($input['price'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1_000_000_000]]);
    $stock      = filter_var($input['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1_000_000]]);
    if ($price === false || $price === null || $stock === false || $stock === null) {
        throw new ApiException('Giá hoặc tồn kho không hợp lệ.');
    }

    $description    = inputString($input, 'description', 10000);
    $specs          = inputString($input, 'specs', 10000);                     // TV4: thêm thông số kỹ thuật
    $warrantyMonths = filter_var($input['warranty_months'] ?? 12, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 240]]);
    if ($warrantyMonths === false) $warrantyMonths = 12;

    $thumbnail = inputString($input, 'thumbnail', 255);
    if ($thumbnail !== '' && !preg_match('#^products/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp|avif)$#i', $thumbnail)) {
        throw new ApiException('Ảnh phải có dạng products/ten-anh.jpg trong uploads.');
    }
    if ($thumbnail !== '' && !is_file(dirname(__DIR__, 2) . '/uploads/' . $thumbnail)) {
        throw new ApiException('Ảnh chưa có trong thư mục uploads.');
    }

    $status = $input['status'] ?? 'active';
    if (!in_array($status, ['active', 'inactive'], true)) throw new ApiException('Trạng thái không hợp lệ.');

    $stmt = $db->prepare('SELECT id FROM categories WHERE id = ?');
    $stmt->execute([$categoryId]);
    if (!$stmt->fetch()) throw new ApiException('Danh mục không tồn tại.');

    if ($method === 'POST') {
        $slug = makeSlug($name) . '-' . bin2hex(random_bytes(4));
        $db->prepare(
            'INSERT INTO products (category_id, name, slug, description, specs, warranty_months, price, stock, thumbnail, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$categoryId, $name, $slug, $description, $specs, $warrantyMonths, $price, $stock, $thumbnail, $status]);
        $id = (int) $db->lastInsertId();
    } else {
        $stmt = $db->prepare('SELECT id FROM products WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) throw new ApiException('Không tìm thấy sản phẩm.', 404);

        $db->prepare(
            'UPDATE products SET category_id=?, name=?, description=?, specs=?, warranty_months=?,
             price=?, stock=?, thumbnail=?, status=? WHERE id=?'
        )->execute([$categoryId, $name, $description, $specs, $warrantyMonths, $price, $stock, $thumbnail, $status, $id]);
    }
    jsonResponse(true, ['id' => $id], 'Đã lưu sản phẩm.');
}

/* ---- Danh mục: thêm mới ---- */
if ($resource === 'categories' && $method === 'POST') {
    $name        = inputString($input, 'name', 100, true);
    $description = inputString($input, 'description', 1000);
    $slug        = makeSlug($name) . '-' . bin2hex(random_bytes(3));
    $db->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)')->execute([$name, $slug, $description]);
    jsonResponse(true, ['id' => (int) $db->lastInsertId()], 'Đã thêm danh mục.', 201);
}

/* ---- Danh mục: sửa ---- */
if ($resource === 'categories' && $method === 'PUT') {
    if (!$id) throw new ApiException('ID không hợp lệ.');
    $stmt = $db->prepare('SELECT id FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) throw new ApiException('Không tìm thấy danh mục.', 404);

    $name        = inputString($input, 'name', 100, true);
    $description = inputString($input, 'description', 1000);
    $db->prepare('UPDATE categories SET name=?, description=? WHERE id=?')->execute([$name, $description, $id]);
    jsonResponse(true, ['id' => $id], 'Đã cập nhật danh mục.');
}

/* ---- Đơn hàng: đổi trạng thái ---- */
if ($resource === 'orders' && $method === 'PUT' && $id) {
    $next = isset($input['status']) ? inputString($input, 'status', 20) : null;
    // TV4: lý do hủy
    $cancelReason = isset($input['cancel_reason']) ? inputString($input, 'cancel_reason', 500) : null;

    // Bỏ carrier / tracking_code / estimated_delivery (không cần trường vận chuyển thật)

    transaction($db, function () use ($db, $id, $next, $cancelReason, $adminUser): void {
        $stmt = $db->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        if ($current === false) throw new ApiException('Không tìm thấy đơn.', 404);

        if ($next !== null && $current !== $next) {
            $allowed = [
                'pending'    => ['processing', 'cancelled'],
                'processing' => ['shipped',    'cancelled'],
                'shipped'    => ['completed'],
                'completed'  => [],
                'cancelled'  => [],
            ];
            if (!in_array($next, $allowed[$current] ?? [], true)) {
                throw new ApiException('Không thể chuyển trạng thái đơn hàng như yêu cầu.', 409);
            }

            /* Hủy đơn → hoàn tồn kho đúng một lần (trong cùng transaction) */
            if ($next === 'cancelled') {
                $stmt = $db->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? ORDER BY product_id');
                $stmt->execute([$id]);
                $update = $db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                foreach ($stmt->fetchAll() as $item) $update->execute([$item['quantity'], $item['product_id']]);
            }

            $db->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$next, $id]);

            /* Ghi lịch sử trạng thái (TV4) */
            try {
                $db->prepare(
                    'INSERT INTO order_status_logs (order_id, from_status, to_status, handled_by, handler_name, cancel_reason)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $id,
                    $current,
                    $next,
                    (int) $adminUser['id'],
                    $adminUser['name'],
                    $next === 'cancelled' ? ($cancelReason ?: 'Admin hủy') : null,
                ]);
            } catch (PDOException) {}
        }
    });

    jsonResponse(true, getOrder($db, $id, null, true), 'Đã cập nhật đơn hàng.');
}

throw new ApiException('Thao tác không được hỗ trợ.', 405);
