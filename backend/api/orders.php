<?php
declare(strict_types=1);

/**
 * API đơn hàng.
 *
 *   GET  /backend/api/orders.php?page=1&per_page=10   lịch sử đơn của tôi
 *   GET  /backend/api/orders.php?id=ID                chi tiết 1 đơn của tôi
 *   POST /backend/api/orders.php  {receiver_name, phone, address, note?}
 *        => tạo đơn từ giỏ hàng (transaction, kiểm tra tồn kho, tính tiền ở server)
 *
 * Giá, tên, thành tiền KHÔNG nhận từ client; luôn đọc lại từ bảng products.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

run_api(function (): void {
    $userId = require_login();
    $pdo    = db();

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['id'])) {
                $id = positive_int($_GET['id']) ?? throw new ApiException('id không hợp lệ.', 422);
                json_response(['success' => true, 'data' => get_order($pdo, $userId, $id)]);
            }
            json_response(['success' => true] + list_orders($pdo, $userId));

        case 'POST':
            $order = create_order($pdo, $userId, json_input());
            json_response(['success' => true, 'message' => 'Đặt hàng thành công.', 'data' => $order], 201);

        default:
            header('Allow: GET, POST');
            throw new ApiException('Phương thức không được hỗ trợ.', 405);
    }
});

function list_orders(PDO $pdo, int $userId): array
{
    $page    = max(1, positive_int($_GET['page'] ?? 1) ?? 1);
    $perPage = min(50, positive_int($_GET['per_page'] ?? 10) ?? 10);
    $offset  = ($page - 1) * $perPage;

    $st = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
    $st->execute([$userId]);
    $total = (int) $st->fetchColumn();

    // LIMIT/OFFSET là số nguyên đã ép kiểu nên ghép thẳng vào câu SQL là an toàn
    $st = $pdo->prepare(
        "SELECT o.id, o.total_amount, o.status, o.created_at,
                (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
           FROM orders o
          WHERE o.user_id = ?
          ORDER BY o.created_at DESC, o.id DESC
          LIMIT $perPage OFFSET $offset"
    );
    $st->execute([$userId]);

    $orders = array_map(static fn(array $r): array => [
        'id'           => (int) $r['id'],
        'total_amount' => (int) $r['total_amount'],
        'status'       => $r['status'],
        'item_count'   => (int) $r['item_count'],
        'created_at'   => $r['created_at'],
    ], $st->fetchAll());

    return [
        'data' => $orders,
        'pagination' => [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ],
    ];
}

function get_order(PDO $pdo, int $userId, int $orderId): array
{
    // Điều kiện user_id: người khác truy cập sẽ nhận 404 (không lộ việc đơn có tồn tại)
    $st = $pdo->prepare(
        'SELECT id, total_amount, status, receiver_name, phone, address, note, created_at
           FROM orders WHERE id = ? AND user_id = ?'
    );
    $st->execute([$orderId, $userId]);
    $order = $st->fetch();
    if (!$order) {
        throw new ApiException('Không tìm thấy đơn hàng.', 404);
    }

    $st = $pdo->prepare(
        'SELECT product_id, product_name, image, price, quantity
           FROM order_items WHERE order_id = ? ORDER BY id'
    );
    $st->execute([$orderId]);

    $items = array_map(static function (array $r): array {
        $price = (int) $r['price'];
        $qty   = (int) $r['quantity'];
        return [
            'product_id' => $r['product_id'] !== null ? (int) $r['product_id'] : null,
            'name'       => $r['product_name'],
            'image'      => $r['image'],
            'price'      => $price,
            'quantity'   => $qty,
            'subtotal'   => $price * $qty,
        ];
    }, $st->fetchAll());

    return [
        'id'            => (int) $order['id'],
        'total_amount'  => (int) $order['total_amount'],
        'status'        => $order['status'],
        'receiver_name' => $order['receiver_name'],
        'phone'         => $order['phone'],
        'address'       => $order['address'],
        'note'          => $order['note'],
        'created_at'    => $order['created_at'],
        'items'         => $items,
    ];
}

function validate_shipping(array $in): array
{
    $name    = trim((string) ($in['receiver_name'] ?? ''));
    $phone   = preg_replace('/[\s.\-]/', '', (string) ($in['phone'] ?? ''));
    $address = trim((string) ($in['address'] ?? ''));
    $note    = trim((string) ($in['note'] ?? ''));

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['receiver_name'] = 'Họ tên từ 2 đến 100 ký tự.';
    }
    if (!preg_match('/^(0|\+84)\d{9}$/', $phone)) {
        $errors['phone'] = 'Số điện thoại không hợp lệ (VD: 0912345678).';
    }
    if (mb_strlen($address) < 5 || mb_strlen($address) > 255) {
        $errors['address'] = 'Địa chỉ từ 5 đến 255 ký tự.';
    }
    if (mb_strlen($note) > 500) {
        $errors['note'] = 'Ghi chú tối đa 500 ký tự.';
    }
    if ($errors) {
        throw new ApiException('Thông tin giao hàng chưa hợp lệ.', 422, ['errors' => $errors]);
    }
    return [$name, $phone, $address, $note !== '' ? $note : null];
}

function create_order(PDO $pdo, int $userId, array $in): array
{
    [$name, $phone, $address, $note] = validate_shipping($in);

    $pdo->beginTransaction();
    try {
        // Khoá cả dòng giỏ + dòng sản phẩm; ORDER BY cố định để giảm nguy cơ deadlock
        $st = $pdo->prepare(
            'SELECT ci.product_id, ci.quantity, p.name, p.price, p.image, p.stock
               FROM cart_items ci
               JOIN products p ON p.id = ci.product_id
              WHERE ci.user_id = ?
              ORDER BY ci.product_id
                FOR UPDATE'
        );
        $st->execute([$userId]);
        $rows = $st->fetchAll();

        if (!$rows) {
            throw new ApiException('Giỏ hàng đang trống.', 422);
        }

        // 1) Kiểm tra tồn kho cho TẤT CẢ sản phẩm rồi mới báo lỗi một lượt
        $problems = [];
        foreach ($rows as $r) {
            if ((int) $r['quantity'] > (int) $r['stock']) {
                $problems[] = [
                    'product_id' => (int) $r['product_id'],
                    'name'       => $r['name'],
                    'requested'  => (int) $r['quantity'],
                    'stock'      => (int) $r['stock'],
                ];
            }
        }
        if ($problems) {
            throw new ApiException('Một số sản phẩm không đủ hàng.', 409, ['items' => $problems]);
        }

        // 2) Tính tổng tiền ở server
        $total = 0;
        foreach ($rows as $r) {
            $total += (int) round((float) $r['price']) * (int) $r['quantity'];
        }

        // 3) Tạo đơn + chi tiết đơn + trừ kho
        $st = $pdo->prepare(
            'INSERT INTO orders (user_id, total_amount, status, receiver_name, phone, address, note)
             VALUES (?, ?, \'pending\', ?, ?, ?, ?)'
        );
        $st->execute([$userId, $total, $name, $phone, $address, $note]);
        $orderId = (int) $pdo->lastInsertId();

        $insItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, image, price, quantity)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $decStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');

        foreach ($rows as $r) {
            $qty = (int) $r['quantity'];
            $insItem->execute([
                $orderId,
                (int) $r['product_id'],
                $r['name'],
                $r['image'],
                (int) round((float) $r['price']),
                $qty,
            ]);
            $decStock->execute([$qty, (int) $r['product_id'], $qty]);
            if ($decStock->rowCount() !== 1) {
                // Lưới an toàn: nếu vẫn lỡ thiếu hàng thì huỷ toàn bộ
                throw new ApiException('Sản phẩm "' . $r['name'] . '" vừa hết hàng.', 409);
            }
        }

        // 4) Dọn giỏ hàng
        $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$userId]);

        $pdo->commit();
        return ['order_id' => $orderId, 'total_amount' => $total, 'status' => 'pending'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();   // lỗi bất kỳ => không đơn, không trừ kho, giỏ giữ nguyên
        }
        throw $e;
    }
}
