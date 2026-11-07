<?php
declare(strict_types=1);

/**
 * API giỏ hàng — chỉ thao tác trên giỏ của CHÍNH người đang đăng nhập.
 *
 *   GET    /backend/api/cart.php                        xem giỏ
 *   POST   /backend/api/cart.php   {product_id, quantity}   thêm (cộng dồn nếu đã có)
 *   PUT    /backend/api/cart.php   {product_id, quantity}   đặt số lượng mới
 *   DELETE /backend/api/cart.php?product_id=ID              xoá 1 sản phẩm
 *   DELETE /backend/api/cart.php?clear=1                    xoá cả giỏ
 *
 * user_id luôn lấy từ session, KHÔNG nhận từ client => không sửa được giỏ người khác.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

const MAX_QTY_PER_ITEM = 99;

run_api(function (): void {
    $userId = require_login();
    $pdo    = db();

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            json_response(['success' => true, 'data' => fetch_cart($pdo, $userId)]);

        case 'POST':
            add_item($pdo, $userId, json_input());
            json_response([
                'success' => true,
                'message' => 'Đã thêm vào giỏ hàng.',
                'data'    => fetch_cart($pdo, $userId),
            ], 201);

        case 'PUT':
            update_item($pdo, $userId, json_input());
            json_response([
                'success' => true,
                'message' => 'Đã cập nhật số lượng.',
                'data'    => fetch_cart($pdo, $userId),
            ]);

        case 'DELETE':
            delete_item($pdo, $userId);
            json_response([
                'success' => true,
                'message' => 'Đã xoá khỏi giỏ hàng.',
                'data'    => fetch_cart($pdo, $userId),
            ]);

        default:
            header('Allow: GET, POST, PUT, DELETE');
            throw new ApiException('Phương thức không được hỗ trợ.', 405);
    }
});

/** Đọc giỏ kèm thông tin sản phẩm; giá & thành tiền luôn lấy từ bảng products. */
function fetch_cart(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare(
        'SELECT ci.product_id, ci.quantity, p.name, p.price, p.image, p.stock
           FROM cart_items ci
           JOIN products p ON p.id = ci.product_id
          WHERE ci.user_id = ?
          ORDER BY ci.updated_at DESC, ci.id DESC'
    );
    $st->execute([$userId]);

    $items = [];
    $total = 0;
    $count = 0;
    foreach ($st->fetchAll() as $row) {
        $price    = (int) round((float) $row['price']);
        $qty      = (int) $row['quantity'];
        $stock    = (int) $row['stock'];
        $subtotal = $price * $qty;

        $items[] = [
            'product_id' => (int) $row['product_id'],
            'name'       => $row['name'],
            'image'      => $row['image'],
            'price'      => $price,
            'quantity'   => $qty,
            'stock'      => $stock,
            'subtotal'   => $subtotal,
            'available'  => $stock >= $qty,   // false => vượt tồn kho, chặn đặt hàng
        ];
        $total += $subtotal;
        $count += $qty;
    }

    return [
        'items'       => $items,
        'total'       => $total,
        'total_items' => $count,
        'is_empty'    => $items === [],
        'can_checkout'=> $items !== [] && !in_array(false, array_column($items, 'available'), true),
    ];
}

function parse_qty(mixed $value, string $label = 'Số lượng'): int
{
    $qty = positive_int($value);
    if ($qty === null) {
        throw new ApiException("$label phải là số nguyên dương.", 422);
    }
    if ($qty > MAX_QTY_PER_ITEM) {
        throw new ApiException("Tối đa " . MAX_QTY_PER_ITEM . " sản phẩm cho mỗi mặt hàng.", 422);
    }
    return $qty;
}

function add_item(PDO $pdo, int $userId, array $in): void
{
    $productId = positive_int($in['product_id'] ?? null)
        ?? throw new ApiException('product_id không hợp lệ.', 422);
    $addQty = parse_qty($in['quantity'] ?? 1);

    $pdo->beginTransaction();
    try {
        // Khoá dòng sản phẩm để tránh 2 request cùng vượt tồn kho
        $st = $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
        $st->execute([$productId]);
        $product = $st->fetch();
        if (!$product) {
            throw new ApiException('Sản phẩm không tồn tại.', 404);
        }

        $st = $pdo->prepare('SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
        $st->execute([$userId, $productId]);
        $current = (int) ($st->fetchColumn() ?: 0);

        $newQty = $current + $addQty;
        $stock  = (int) $product['stock'];
        if ($newQty > $stock) {
            throw new ApiException(
                $stock > 0 ? "Chỉ còn $stock sản phẩm trong kho." : 'Sản phẩm đã hết hàng.',
                409,
                ['stock' => $stock]
            );
        }
        if ($newQty > MAX_QTY_PER_ITEM) {
            throw new ApiException("Tối đa " . MAX_QTY_PER_ITEM . " sản phẩm cho mỗi mặt hàng.", 422);
        }

        $st = $pdo->prepare(
            'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = ?'
        );
        $st->execute([$userId, $productId, $newQty, $newQty]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function update_item(PDO $pdo, int $userId, array $in): void
{
    $productId = positive_int($in['product_id'] ?? null)
        ?? throw new ApiException('product_id không hợp lệ.', 422);
    $qty = parse_qty($in['quantity'] ?? null);

    $st = $pdo->prepare(
        'SELECT p.stock
           FROM cart_items ci JOIN products p ON p.id = ci.product_id
          WHERE ci.user_id = ? AND ci.product_id = ?'
    );
    $st->execute([$userId, $productId]);
    $stock = $st->fetchColumn();
    if ($stock === false) {
        throw new ApiException('Sản phẩm không có trong giỏ hàng.', 404);
    }
    $stock = (int) $stock;
    if ($qty > $stock) {
        throw new ApiException(
            $stock > 0 ? "Chỉ còn $stock sản phẩm trong kho." : 'Sản phẩm đã hết hàng.',
            409,
            ['stock' => $stock]
        );
    }

    $st = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?');
    $st->execute([$qty, $userId, $productId]);
}

function delete_item(PDO $pdo, int $userId): void
{
    if (($_GET['clear'] ?? '') === '1') {
        $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$userId]);
        return;
    }

    $productId = positive_int($_GET['product_id'] ?? null)
        ?? throw new ApiException('product_id không hợp lệ.', 422);

    $st = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
    $st->execute([$userId, $productId]);
    if ($st->rowCount() === 0) {
        throw new ApiException('Sản phẩm không có trong giỏ hàng.', 404);
    }
}
