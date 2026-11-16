<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/orders.php';
allowMethods(['GET', 'POST', 'PUT', 'DELETE']);
requireAdmin();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? 'dashboard';
$id = positiveInt($_GET['id'] ?? null);
if (!in_array($resource, ['dashboard', 'products', 'categories', 'orders'], true)) throw new ApiException('Không tìm thấy chức năng.', 404);
if ($method === 'GET') {
    if ($resource === 'dashboard') {
        jsonResponse(true, [
            'products' => (int) $db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
            'orders' => (int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'pending' => (int) $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
            'revenue' => (int) $db->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE status = 'completed'")->fetchColumn(),
        ]);
    }
    if ($resource === 'categories') jsonResponse(true, $db->query('SELECT * FROM categories ORDER BY id')->fetchAll());
    if ($resource === 'orders' && $id) jsonResponse(true, getOrder($db, $id));
    if ($resource === 'products' && $id) {
        $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) throw new ApiException('Không tìm thấy sản phẩm.', 404);
        jsonResponse(true, $product);
    }
    $page = min(100000, positiveInt($_GET['page'] ?? 1) ?? 1);
    $offset = ($page - 1) * 20;
    $search = inputString($_GET, 'search', 150);
    $params = [];
    $where = '1=1';
    if ($search !== '') {
        $where = $resource === 'products' ? 'name LIKE ?' : '(receiver_name LIKE ? OR CAST(id AS CHAR) = ?)';
        $params = $resource === 'products' ? ["%$search%"] : ["%$search%", $search];
    }
    $count = $db->prepare("SELECT COUNT(*) FROM $resource WHERE $where");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $stmt = $db->prepare("SELECT * FROM $resource WHERE $where ORDER BY id DESC LIMIT 20 OFFSET $offset");
    $stmt->execute($params);
    jsonResponse(true, ['items' => $stmt->fetchAll(), 'page' => $page, 'total' => $total, 'total_pages' => max(1, (int) ceil($total / 20))]);
}

if ($resource === 'products' && $method === 'DELETE') {
    if (!$id) throw new ApiException('ID không hợp lệ.');
    // Soft deletion preserves historical order references.
    $db->prepare("UPDATE products SET status = 'inactive' WHERE id = ?")->execute([$id]);
    jsonResponse(true, null, 'Đã ngừng bán sản phẩm; lịch sử đơn vẫn được giữ.');
}
$input = getJsonBody();
if ($resource === 'products' && in_array($method, ['POST', 'PUT'], true)) {
    if ($method === 'PUT' && !$id) throw new ApiException('ID không hợp lệ.');
    $name = inputString($input, 'name', 200, true);
    $categoryId = positiveInt($input['category_id'] ?? null) ?? throw new ApiException('Danh mục không hợp lệ.');
    $price = filter_var($input['price'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000000]]);
    $stock = filter_var($input['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
    if ($price === false || $price === null || $stock === false || $stock === null) throw new ApiException('Giá hoặc tồn kho không hợp lệ.');
    $description = inputString($input, 'description', 10000);
    $thumbnail = inputString($input, 'thumbnail', 255);
    if ($thumbnail !== '' && !preg_match('#^products/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp|avif)$#i', $thumbnail)) throw new ApiException('Ảnh phải có dạng products/ten-anh.jpg trong uploads.');
    if ($thumbnail !== '' && !is_file(dirname(__DIR__, 2) . '/uploads/' . $thumbnail)) throw new ApiException('Ảnh chưa có trong thư mục uploads.');
    $status = $input['status'] ?? 'active';
    if (!in_array($status, ['active', 'inactive'], true)) throw new ApiException('Trạng thái không hợp lệ.');
    $stmt = $db->prepare('SELECT id FROM categories WHERE id = ?');
    $stmt->execute([$categoryId]);
    if (!$stmt->fetch()) throw new ApiException('Danh mục không tồn tại.');
    if ($method === 'POST') {
        $slug = makeSlug($name) . '-' . bin2hex(random_bytes(4));
        $db->prepare('INSERT INTO products (category_id, name, slug, description, price, stock, thumbnail, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$categoryId, $name, $slug, $description, $price, $stock, $thumbnail, $status]);
        $id = (int) $db->lastInsertId();
    } else {
        $stmt = $db->prepare('SELECT id FROM products WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) throw new ApiException('Không tìm thấy sản phẩm.', 404);
        $db->prepare('UPDATE products SET category_id=?, name=?, description=?, price=?, stock=?, thumbnail=?, status=? WHERE id=?')->execute([$categoryId, $name, $description, $price, $stock, $thumbnail, $status, $id]);
    }
    jsonResponse(true, ['id' => $id], 'Đã lưu sản phẩm.');
}
if ($resource === 'categories' && $method === 'POST') {
    $name = inputString($input, 'name', 100, true);
    $description = inputString($input, 'description', 1000);
    $slug = makeSlug($name) . '-' . bin2hex(random_bytes(3));
    $db->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)')->execute([$name, $slug, $description]);
    jsonResponse(true, ['id' => (int) $db->lastInsertId()], 'Đã thêm danh mục.', 201);
}
if ($resource === 'orders' && $method === 'PUT' && $id) {
    $next = inputString($input, 'status', 20, true);
    transaction($db, function () use ($db, $id, $next): void {
        $stmt = $db->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        if ($current === false) throw new ApiException('Không tìm thấy đơn.', 404);
        if ($current === $next) return;
        $allowed = ['pending' => ['processing', 'cancelled'], 'processing' => ['shipped', 'cancelled'], 'shipped' => ['completed'], 'completed' => [], 'cancelled' => []];
        if (!in_array($next, $allowed[$current] ?? [], true)) throw new ApiException('Không thể chuyển trạng thái đơn hàng như yêu cầu.', 409);
        if ($next === 'cancelled') {
            $stmt = $db->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? ORDER BY product_id');
            $stmt->execute([$id]);
            $update = $db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            foreach ($stmt->fetchAll() as $item) $update->execute([$item['quantity'], $item['product_id']]);
        }
        $db->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$next, $id]);
    });
    jsonResponse(true, getOrder($db, $id), 'Đã cập nhật đơn hàng.');
}
throw new ApiException('Thao tác không được hỗ trợ.', 405);
