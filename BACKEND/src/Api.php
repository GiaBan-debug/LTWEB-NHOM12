<?php
declare(strict_types=1);

final class Api
{
    private const ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipping', 'completed', 'cancelled'];
    private const PAYMENT_METHODS = ['cod', 'banking', 'momo'];
    private PDO $db;
    private string $method;
    private array $segments;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
        $this->segments = $path === '' ? [] : array_map('rawurldecode', explode('/', $path));
    }

    public function run(): never
    {
        if ($this->segments === []) {
            sendJson(['app' => envValue('APP_NAME', 'LTWEB NHOM12 API - PHP'), 'status' => 'online']);
        }
        if ($this->segments === ['api', 'health'] && $this->method === 'GET') {
            $this->db->query('SELECT 1');
            sendJson(['status' => 'healthy', 'database' => 'connected']);
        }

        if (($this->segments[0] ?? '') !== 'api') {
            fail(404, 'Không tìm thấy API.');
        }
        $resource = $this->segments[1] ?? '';
        $id = $this->segments[2] ?? null;
        $action = $this->segments[3] ?? null;

        match ($resource) {
            'auth' => $this->authRoutes($id),
            'products' => $this->productRoutes($id),
            'categories' => $this->categoryRoutes($id),
            'cart' => $this->cartRoutes($id),
            'orders' => $this->orderRoutes($id, $action),
            'admin' => $this->adminRoutes($id, $action),
            default => fail(404, 'Không tìm thấy API.'),
        };
        fail(404, 'Không tìm thấy API.');
    }

    private function authRoutes(?string $id): never
    {
        if ($id === 'register' && $this->method === 'POST') {
            $data = requestBody();
            $username = requiredString($data, 'username', 3, 50);
            $email = requiredString($data, 'email', 3, 255);
            validate(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, 'email', 'Email không hợp lệ.');
            $password = requiredPassword($data, 'password', 6, 100);
            $check = $this->db->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $check->execute([$username, $email]);
            validate($check->fetch() === false, 'username/email', 'Tên đăng nhập hoặc email đã được sử dụng.');

            $insert = $this->db->prepare(
                'INSERT INTO users (username, email, hashed_password, full_name, phone, address, role, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $insert->execute([
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                optionalString($data, 'full_name', 255),
                optionalString($data, 'phone', 20),
                optionalString($data, 'address', 500),
                'customer',
            ]);
            $user = $this->findUser((int) $this->db->lastInsertId());
            sendJson($this->tokenResponse($user), 201);
        }
        if ($id === 'login' && $this->method === 'POST') {
            $data = requestBody();
            $login = requiredString($data, 'username_or_email', 3, 255);
            $password = requiredPassword($data, 'password', 1, 100);
            $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['hashed_password'])) {
                fail(401, 'Tài khoản hoặc mật khẩu không chính xác.');
            }
            if (!(bool) $user['is_active']) {
                fail(403, 'Tài khoản này đã bị khóa.');
            }
            sendJson($this->tokenResponse($user));
        }

        if ($id === 'me' && $this->method === 'GET') {
            sendJson(publicUser($this->currentUser()));
        }
        if ($id === 'me' && $this->method === 'PUT') {
            $user = $this->currentUser();
            $data = requestBody();
            $allowed = ['full_name' => 255, 'phone' => 20, 'address' => 500];
            $updates = [];
            foreach ($allowed as $field => $max) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = optionalString($data, $field, $max);
                }
            }
            $this->updateRow('users', (int) $user['id'], $updates);
            sendJson(publicUser($this->findUser((int) $user['id'])));
        }
        if ($id === 'change-password' && $this->method === 'POST') {
            $user = $this->currentUser();
            $data = requestBody();
            $oldPassword = requiredPassword($data, 'old_password', 1, 100);
            $newPassword = requiredPassword($data, 'new_password', 6, 100);
            if (!password_verify($oldPassword, $user['hashed_password'])) {
                fail(400, 'Mật khẩu hiện tại không đúng.');
            }
            $stmt = $this->db->prepare('UPDATE users SET hashed_password = ? WHERE id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            sendJson(['message' => 'Đổi mật khẩu thành công.']);
        }
        fail(404, 'Không tìm thấy endpoint xác thực.');
    }

    private function productRoutes(?string $id): never
    {
        if ($id === null && $this->method === 'GET') {
            $query = 'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE 1=1';
            $params = [];
            if (($_GET['only_active'] ?? 'true') !== 'false') {
                $query .= ' AND p.is_active = 1';
            }
            if (isset($_GET['q']) && trim((string) $_GET['q']) !== '') {
                $query .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
                $search = '%' . trim((string) $_GET['q']) . '%';
                array_push($params, $search, $search);
            }
            if (isset($_GET['category_id'])) {
                $query .= ' AND p.category_id = ?';
                $params[] = positiveInt($_GET['category_id'], 'category_id');
            }
            if (isset($_GET['category_slug'])) {
                $query .= ' AND p.category_id = (SELECT id FROM categories WHERE slug = ? LIMIT 1)';
                $params[] = (string) $_GET['category_slug'];
            }
            foreach (['min_price' => '>=', 'max_price' => '<='] as $field => $operator) {
                if (isset($_GET[$field])) {
                    validate(is_numeric($_GET[$field]) && (float) $_GET[$field] >= 0, $field, 'Giá phải là số không âm.');
                    $query .= " AND p.price $operator ?";
                    $params[] = (float) $_GET[$field];
                }
            }
            if (isset($_GET['min_price'], $_GET['max_price'])) {
                validate((float) $_GET['min_price'] <= (float) $_GET['max_price'], 'price', 'Giá thấp nhất không thể lớn hơn giá cao nhất.');
            }
            $sorts = [
                'price_asc' => 'p.price ASC',
                'price_desc' => 'p.price DESC',
                'name_asc' => 'p.name ASC',
                'newest' => 'p.created_at DESC',
            ];
            $sort = (string) ($_GET['sort_by'] ?? 'newest');
            $query .= ' ORDER BY ' . ($sorts[$sort] ?? $sorts['newest']);
            $this->sendPaginated($query, $params, 'products');
        }
        if ($id === null && $this->method === 'POST') {
            $this->requireAdmin();
            $data = requestBody();
            $name = requiredString($data, 'name', 2, 255);
            $price = $this->money($data['price'] ?? null, 'price', 0, false);
            $categoryId = isset($data['category_id']) ? positiveInt($data['category_id'], 'category_id') : null;
            if ($categoryId !== null && !$this->exists('categories', $categoryId)) {
                fail(400, 'Danh mục liên kết không tồn tại.');
            }
            $slug = $this->uniqueSlug('products', optionalString($data, 'slug', 255) ?: $name);
            $stock = isset($data['stock']) ? positiveInt($data['stock'], 'stock', 0) : 0;
            $originalPrice = isset($data['original_price']) ? $this->money($data['original_price'], 'original_price', 0) : null;
            $stmt = $this->db->prepare(
                'INSERT INTO products (category_id, name, slug, description, price, original_price, stock, image_url, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $categoryId, $name, $slug, optionalString($data, 'description', 16000), $price, $originalPrice,
                $stock, optionalString($data, 'image_url', 500), $this->boolValue($data['is_active'] ?? true, 'is_active'),
            ]);
            sendJson($this->product((int) $this->db->lastInsertId()), 201);
        }
        if ($id !== null && ctype_digit($id) && $this->method === 'PUT') {
            $this->requireAdmin();
            $product = $this->product((int) $id);
            if (!$product) {
                fail(404, 'Không tìm thấy sản phẩm.');
            }
            $data = requestBody();
            $fields = [];
            $allowed = ['name' => 255, 'description' => 16000, 'image_url' => 500];
            foreach ($allowed as $field => $max) {
                if (array_key_exists($field, $data)) {
                    $fields[$field] = $field === 'name' ? requiredString($data, $field, 2, $max) : optionalString($data, $field, $max);
                }
            }
            if (array_key_exists('category_id', $data)) {
                $categoryId = $data['category_id'] === null ? null : positiveInt($data['category_id'], 'category_id');
                if ($categoryId !== null && !$this->exists('categories', $categoryId)) {
                    fail(400, 'Danh mục không tồn tại.');
                }
                $fields['category_id'] = $categoryId;
            }
            if (array_key_exists('slug', $data)) {
                $fields['slug'] = $this->uniqueSlug('products', requiredString($data, 'slug', 1, 255), (int) $id);
            }
            if (array_key_exists('price', $data)) {
                $fields['price'] = $this->money($data['price'], 'price', 0, false);
            }
            if (array_key_exists('original_price', $data)) {
                $fields['original_price'] = $data['original_price'] === null ? null : $this->money($data['original_price'], 'original_price', 0);
            }
            if (array_key_exists('stock', $data)) {
                $fields['stock'] = positiveInt($data['stock'], 'stock', 0);
            }
            if (array_key_exists('is_active', $data)) {
                $fields['is_active'] = $this->boolValue($data['is_active'], 'is_active');
            }
            $this->updateRow('products', (int) $id, $fields);
            sendJson($this->product((int) $id));
        }
        if ($id !== null && ctype_digit($id) && $this->method === 'DELETE') {
            $this->requireAdmin();
            if (!$this->exists('products', (int) $id)) {
                fail(404, 'Không tìm thấy sản phẩm.');
            }
            $stmt = $this->db->prepare('DELETE FROM products WHERE id = ?');
            $stmt->execute([(int) $id]);
            sendJson(['message' => 'Đã xóa sản phẩm thành công.']);
        }
        if ($id !== null && $this->method === 'GET') {
            $product = ctype_digit($id) ? $this->product((int) $id) : $this->productBySlug($id);
            if (!$product) {
                fail(404, 'Không tìm thấy sản phẩm.');
            }
            sendJson($product);
        }
        fail(405, 'Phương thức hoặc endpoint sản phẩm không được hỗ trợ.');
    }

    private function categoryRoutes(?string $id): never
    {
        if ($id === null && $this->method === 'GET') {
            $stmt = $this->db->query(
                'SELECT c.*, COUNT(p.id) AS product_count FROM categories c
                 LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.name'
            );
            sendJson($stmt->fetchAll());
        }
        if ($id === null && $this->method === 'POST') {
            $this->requireAdmin();
            $data = requestBody();
            $name = requiredString($data, 'name', 2, 150);
            $slug = $this->uniqueSlug('categories', optionalString($data, 'slug', 150) ?: $name);
            $stmt = $this->db->prepare('INSERT INTO categories (name, slug, description, image_url) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $slug, optionalString($data, 'description', 16000), optionalString($data, 'image_url', 500)]);
            sendJson($this->category((int) $this->db->lastInsertId()), 201);
        }
        if ($id !== null && $this->method === 'GET') {
            $category = ctype_digit($id) ? $this->category((int) $id) : $this->categoryBySlug($id);
            if (!$category) {
                fail(404, 'Không tìm thấy danh mục.');
            }
            sendJson($category);
        }
        if ($id !== null && ctype_digit($id) && $this->method === 'PUT') {
            $this->requireAdmin();
            if (!$this->category((int) $id)) {
                fail(404, 'Không tìm thấy danh mục.');
            }
            $data = requestBody();
            $fields = [];
            foreach (['name' => 150, 'description' => 16000, 'image_url' => 500] as $field => $max) {
                if (array_key_exists($field, $data)) {
                    $fields[$field] = $field === 'name' ? requiredString($data, $field, 2, $max) : optionalString($data, $field, $max);
                }
            }
            if (array_key_exists('slug', $data)) {
                $fields['slug'] = $this->uniqueSlug('categories', requiredString($data, 'slug', 1, 150), (int) $id);
            }
            $this->updateRow('categories', (int) $id, $fields);
            sendJson($this->category((int) $id));
        }
        if ($id !== null && ctype_digit($id) && $this->method === 'DELETE') {
            $this->requireAdmin();
            if (!$this->exists('categories', (int) $id)) {
                fail(404, 'Không tìm thấy danh mục.');
            }
            $stmt = $this->db->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([(int) $id]);
            sendJson(['message' => 'Đã xóa danh mục thành công.']);
        }
        fail(405, 'Phương thức hoặc endpoint danh mục không được hỗ trợ.');
    }

    private function cartRoutes(?string $id): never
    {
        $user = $this->currentUser();
        if ($id === null && $this->method === 'GET') {
            sendJson($this->cart((int) $user['id']));
        }
        if ($id === null && $this->method === 'POST') {
            $data = requestBody();
            $productId = positiveInt($data['product_id'] ?? null, 'product_id');
            $quantity = positiveInt($data['quantity'] ?? 1, 'quantity');
            $product = $this->product($productId);
            if (!$product || !(bool) $product['is_active']) {
                fail(404, 'Sản phẩm không tồn tại hoặc đang ngừng kinh doanh.');
            }
            $stmt = $this->db->prepare('SELECT * FROM cart_items WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$user['id'], $productId]);
            $existing = $stmt->fetch();
            $nextQuantity = $quantity + (int) ($existing['quantity'] ?? 0);
            if ($nextQuantity > (int) $product['stock']) {
                fail(400, 'Số lượng trong giỏ vượt quá tồn kho.');
            }
            if ($existing) {
                $update = $this->db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
                $update->execute([$nextQuantity, $existing['id']]);
            } else {
                $insert = $this->db->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)');
                $insert->execute([$user['id'], $productId, $quantity]);
            }
            sendJson($this->cart((int) $user['id']), 201);
        }
        if ($id === null && $this->method === 'DELETE') {
            $stmt = $this->db->prepare('DELETE FROM cart_items WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            sendJson(['message' => 'Đã xóa toàn bộ giỏ hàng thành công.']);
        }
        if ($id !== null && ctype_digit($id) && $this->method === 'PUT') {
            $data = requestBody();
            $quantity = positiveInt($data['quantity'] ?? null, 'quantity');
            $stmt = $this->db->prepare(
                'SELECT ci.id, p.stock FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.id = ? AND ci.user_id = ?'
            );
            $stmt->execute([(int) $id, $user['id']]);
            $item = $stmt->fetch();
            if (!$item) {
                fail(404, 'Mục giỏ hàng không tồn tại.');
            }
            if ($quantity > (int) $item['stock']) {
                fail(400, 'Số lượng yêu cầu vượt quá tồn kho.');
            }
            $update = $this->db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
            $update->execute([$quantity, (int) $id]);
            sendJson($this->cart((int) $user['id']));
        }
        if ($id !== null && ctype_digit($id) && $this->method === 'DELETE') {
            $stmt = $this->db->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?');
            $stmt->execute([(int) $id, $user['id']]);
            if ($stmt->rowCount() === 0) {
                fail(404, 'Mục giỏ hàng không tồn tại.');
            }
            sendJson($this->cart((int) $user['id']));
        }
        fail(405, 'Phương thức hoặc endpoint giỏ hàng không được hỗ trợ.');
    }

    private function orderRoutes(?string $id, ?string $action): never
    {
        $user = $this->currentUser();
        if ($id === null && $this->method === 'POST') {
            $this->createOrder($user, requestBody());
        }
        if ($id === null && $this->method === 'GET') {
            $page = positiveInt($_GET['page'] ?? 1, 'page');
            $limit = min(50, positiveInt($_GET['limit'] ?? 10, 'limit'));
            $where = ' WHERE user_id = ?';
            $count = $this->db->prepare("SELECT COUNT(*) FROM orders$where");
            $count->execute([$user['id']]);
            $total = (int) $count->fetchColumn();
            $stmt = $this->db->prepare("SELECT id FROM orders$where ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $user['id'], PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->bindValue(3, ($page - 1) * $limit, PDO::PARAM_INT);
            $stmt->execute();
            $orders = array_map(fn(array $row): array => $this->order((int) $row['id']), $stmt->fetchAll());
            sendJson(['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => max(1, (int) ceil($total / $limit)), 'items' => $orders]);
        }
        if ($id !== null && ctype_digit($id) && $action === null && $this->method === 'GET') {
            $order = $this->order((int) $id);
            if (!$order) {
                fail(404, 'Không tìm thấy đơn hàng.');
            }
            if ((int) $order['user_id'] !== (int) $user['id'] && $user['role'] !== 'admin') {
                fail(403, 'Bạn không có quyền xem đơn hàng này.');
            }
            sendJson($order);
        }
        if ($id !== null && ctype_digit($id) && $action === 'cancel' && $this->method === 'PUT') {
            $this->cancelOrder((int) $id, $user);
        }
        fail(405, 'Phương thức hoặc endpoint đơn hàng không được hỗ trợ.');
    }

    private function adminRoutes(?string $id, ?string $action): never
    {
        $admin = $this->requireAdmin();
        if ($id === 'stats' && $this->method === 'GET') {
            $recentIds = $this->db->query('SELECT id FROM orders ORDER BY created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_COLUMN);
            $recent = array_map(fn(string|int $orderId): array => $this->order((int) $orderId), $recentIds);
            sendJson([
                'total_users' => $this->scalar('SELECT COUNT(*) FROM users'),
                'total_products' => $this->scalar('SELECT COUNT(*) FROM products'),
                'total_categories' => $this->scalar('SELECT COUNT(*) FROM categories'),
                'total_orders' => $this->scalar('SELECT COUNT(*) FROM orders'),
                'total_revenue' => (float) $this->scalar("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed'"),
                'pending_orders' => $this->scalar("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
                'low_stock_products' => $this->scalar('SELECT COUNT(*) FROM products WHERE stock <= 5'),
                'recent_orders' => $recent,
            ]);
        }
        if ($id === 'users' && $action === null && $this->method === 'GET') {
            $query = 'SELECT * FROM users WHERE 1=1';
            $params = [];
            if (isset($_GET['q']) && trim((string) $_GET['q']) !== '') {
                $query .= ' AND (username LIKE ? OR email LIKE ?)';
                $term = '%' . trim((string) $_GET['q']) . '%';
                array_push($params, $term, $term);
            }
            if (isset($_GET['role'])) {
                validate(in_array($_GET['role'], ['admin', 'customer'], true), 'role', 'Quyền không hợp lệ.');
                $query .= ' AND role = ?';
                $params[] = $_GET['role'];
            }
            $this->sendPaginated($query . ' ORDER BY created_at DESC', $params, 'users');
        }
        if ($id === 'users' && $action !== null && preg_match('/^\d+$/', $action) && ($this->segments[4] ?? null) === 'role' && $this->method === 'PUT') {
            $targetId = (int) $action;
            $target = $this->findUser($targetId);
            if (!$target) {
                fail(404, 'Không tìm thấy người dùng.');
            }
            $data = requestBody();
            $fields = [];
            if (array_key_exists('role', $data)) {
                validate(in_array($data['role'], ['admin', 'customer'], true), 'role', "Quyền chỉ được là 'admin' hoặc 'customer'.");
                if ($targetId === (int) $admin['id'] && $data['role'] !== 'admin') {
                    fail(400, 'Bạn không thể tự hạ quyền Admin của chính mình.');
                }
                $fields['role'] = $data['role'];
            }
            if (array_key_exists('is_active', $data)) {
                $active = $this->boolValue($data['is_active'], 'is_active');
                if ($targetId === (int) $admin['id'] && !$active) {
                    fail(400, 'Bạn không thể tự khóa tài khoản của chính mình.');
                }
                $fields['is_active'] = $active;
            }
            validate($fields !== [], 'body', 'Cần cung cấp role hoặc is_active.');
            $this->updateRow('users', $targetId, $fields);
            sendJson(publicUser($this->findUser($targetId)));
        }
        if ($id === 'orders' && $action === null && $this->method === 'GET') {
            $query = 'SELECT id FROM orders WHERE 1=1';
            $params = [];
            if (isset($_GET['status'])) {
                validate(in_array($_GET['status'], self::ORDER_STATUSES, true), 'status', 'Trạng thái đơn hàng không hợp lệ.');
                $query .= ' AND status = ?';
                $params[] = $_GET['status'];
            }
            $page = positiveInt($_GET['page'] ?? 1, 'page');
            $limit = min(100, positiveInt($_GET['limit'] ?? 10, 'limit'));
            $countQuery = preg_replace('/^SELECT id/', 'SELECT COUNT(*)', $query);
            $count = $this->db->prepare($countQuery);
            $count->execute($params);
            $total = (int) $count->fetchColumn();
            $stmt = $this->db->prepare("$query ORDER BY created_at DESC LIMIT ? OFFSET ?");
            foreach ($params as $index => $param) {
                $stmt->bindValue($index + 1, $param);
            }
            $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(count($params) + 2, ($page - 1) * $limit, PDO::PARAM_INT);
            $stmt->execute();
            $orders = array_map(fn(array $row): array => $this->order((int) $row['id']), $stmt->fetchAll());
            sendJson(['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => max(1, (int) ceil($total / $limit)), 'items' => $orders]);
        }
        if ($id === 'orders' && $action !== null && ctype_digit($action) && ($this->segments[4] ?? null) === 'status' && $this->method === 'PUT') {
            $this->updateOrderStatus((int) $action, requestBody());
        }
        fail(404, 'Không tìm thấy endpoint quản trị.');
    }

    private function createOrder(array $user, array $data): never
    {
        $shippingName = requiredString($data, 'shipping_name', 2, 255);
        $shippingPhone = requiredString($data, 'shipping_phone', 9, 20);
        $shippingAddress = requiredString($data, 'shipping_address', 5, 16000);
        $paymentMethod = $data['payment_method'] ?? 'cod';
        validate(in_array($paymentMethod, self::PAYMENT_METHODS, true), 'payment_method', 'Hình thức thanh toán không hợp lệ.');
        $note = optionalString($data, 'note', 16000);

        $requestedItems = $data['items'] ?? [];
        validate(is_array($requestedItems) && array_is_list($requestedItems), 'items', 'Danh sách sản phẩm không hợp lệ.');
        $quantities = [];
        foreach ($requestedItems as $item) {
            validate(is_array($item), 'items', 'Mỗi sản phẩm phải là một object.');
            $productId = positiveInt($item['product_id'] ?? null, 'product_id');
            $quantity = positiveInt($item['quantity'] ?? null, 'quantity');
            $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        }

        $this->db->beginTransaction();
        try {
            if ($quantities === []) {
                $cart = $this->db->prepare('SELECT product_id, quantity FROM cart_items WHERE user_id = ?');
                $cart->execute([$user['id']]);
                foreach ($cart->fetchAll() as $row) {
                    $quantities[(int) $row['product_id']] = (int) $row['quantity'];
                }
            }
            if ($quantities === []) {
                fail(400, 'Giỏ hàng đang trống, không thể đặt hàng.');
            }

            $items = [];
            $total = 0.0;
            foreach ($quantities as $productId => $quantity) {
                $stmt = $this->db->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                if (!$product || !(bool) $product['is_active']) {
                    fail(400, "Sản phẩm ID $productId không tồn tại hoặc ngừng kinh doanh.");
                }
                if ((int) $product['stock'] < $quantity) {
                    fail(400, "Sản phẩm '{$product['name']}' không đủ số lượng tồn kho.");
                }
                $subtotal = round((float) $product['price'] * $quantity, 2);
                $total += $subtotal;
                $items[] = ['product' => $product, 'quantity' => $quantity, 'subtotal' => $subtotal];
            }
            $insertOrder = $this->db->prepare(
                'INSERT INTO orders (user_id, total_amount, status, shipping_name, shipping_phone, shipping_address, payment_method, payment_status, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insertOrder->execute([$user['id'], round($total, 2), 'pending', $shippingName, $shippingPhone, $shippingAddress, $paymentMethod, 'unpaid', $note]);
            $orderId = (int) $this->db->lastInsertId();
            $insertItem = $this->db->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, price, quantity, total_price) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $decreaseStock = $this->db->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            foreach ($items as $item) {
                $product = $item['product'];
                $insertItem->execute([$orderId, $product['id'], $product['name'], $product['price'], $item['quantity'], $item['subtotal']]);
                $decreaseStock->execute([$item['quantity'], $product['id']]);
            }
            $productIds = array_column(array_map(static fn(array $item): array => ['id' => $item['product']['id']], $items), 'id');
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $deleteCart = $this->db->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id IN ($placeholders)");
            $deleteCart->execute([$user['id'], ...$productIds]);
            $this->db->commit();
            sendJson($this->order($orderId), 201);
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function cancelOrder(int $orderId, array $user): never
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order) {
                fail(404, 'Không tìm thấy đơn hàng.');
            }
            if ((int) $order['user_id'] !== (int) $user['id'] && $user['role'] !== 'admin') {
                fail(403, 'Bạn không có quyền hủy đơn hàng này.');
            }
            if ($order['status'] !== 'pending') {
                fail(400, "Chỉ có thể hủy đơn hàng đang pending. Trạng thái hiện tại: {$order['status']}.");
            }
            $items = $this->db->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
            $items->execute([$orderId]);
            $restore = $this->db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            foreach ($items->fetchAll() as $item) {
                if ($item['product_id'] !== null) {
                    $restore->execute([$item['quantity'], $item['product_id']]);
                }
            }
            $update = $this->db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
            $update->execute([$orderId]);
            $this->db->commit();
            sendJson($this->order($orderId));
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function updateOrderStatus(int $orderId, array $data): never
    {
        $status = $data['status'] ?? null;
        validate(is_string($status) && in_array($status, self::ORDER_STATUSES, true), 'status', 'Trạng thái đơn hàng không hợp lệ.');
        if (isset($data['payment_status'])) {
            validate(in_array($data['payment_status'], ['unpaid', 'paid'], true), 'payment_status', 'Trạng thái thanh toán không hợp lệ.');
        }
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order) {
                fail(404, 'Không tìm thấy đơn hàng.');
            }
            if ($order['status'] === 'cancelled' && $status !== 'cancelled') {
                fail(400, 'Không thể mở lại đơn hàng đã hủy.');
            }
            if ($status === 'cancelled' && $order['status'] !== 'cancelled') {
                $items = $this->db->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
                $items->execute([$orderId]);
                $restore = $this->db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                foreach ($items->fetchAll() as $item) {
                    if ($item['product_id'] !== null) {
                        $restore->execute([$item['quantity'], $item['product_id']]);
                    }
                }
            }
            $paymentStatus = $data['payment_status'] ?? $order['payment_status'];
            $update = $this->db->prepare('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?');
            $update->execute([$status, $paymentStatus, $orderId]);
            $this->db->commit();
            sendJson($this->order($orderId));
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function currentUser(): array
    {
        $token = bearerToken();
        $claims = $token === null ? null : decodeToken($token);
        if (!$claims || !ctype_digit((string) $claims['sub'])) {
            fail(401, 'Phiên đăng nhập không hợp lệ hoặc đã hết hạn.');
        }
        $user = $this->findUser((int) $claims['sub']);
        if (!$user || !(bool) $user['is_active']) {
            fail(401, 'Tài khoản không tồn tại hoặc đã bị vô hiệu hóa.');
        }
        return $user;
    }

    private function requireAdmin(): array
    {
        $user = $this->currentUser();
        if ($user['role'] !== 'admin') {
            fail(403, 'Bạn không có quyền quản trị viên.');
        }
        return $user;
    }

    private function tokenResponse(array $user): array
    {
        return [
            'access_token' => createToken(['sub' => (string) $user['id'], 'role' => $user['role']]),
            'token_type' => 'bearer',
            'user' => publicUser($user),
        ];
    }

    private function findUser(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user === false ? null : $user;
    }

    private function product(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if ($product === false) {
            return null;
        }
        $product['is_active'] = (bool) $product['is_active'];
        $product['price'] = (float) $product['price'];
        $product['original_price'] = $product['original_price'] === null ? null : (float) $product['original_price'];
        $product['stock'] = (int) $product['stock'];
        return $product;
    }

    private function productBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT id FROM products WHERE slug = ?');
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : $this->product((int) $id);
    }

    private function category(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, COUNT(p.id) AS product_count FROM categories c
             LEFT JOIN products p ON p.category_id = c.id WHERE c.id = ? GROUP BY c.id'
        );
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        return $category === false ? null : $category;
    }

    private function categoryBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT id FROM categories WHERE slug = ?');
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : $this->category((int) $id);
    }

    private function cart(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ci.id, p.id AS product_id, p.name AS product_name, p.slug AS product_slug, p.price AS product_price,
                    p.image_url AS product_image, p.stock, ci.quantity
             FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.user_id = ? ORDER BY ci.created_at DESC'
        );
        $stmt->execute([$userId]);
        $items = [];
        $total = 0.0;
        $count = 0;
        foreach ($stmt->fetchAll() as $item) {
            $item['id'] = (int) $item['id'];
            $item['product_id'] = (int) $item['product_id'];
            $item['product_price'] = (float) $item['product_price'];
            $item['stock'] = (int) $item['stock'];
            $item['quantity'] = (int) $item['quantity'];
            $item['total_item_price'] = round($item['product_price'] * $item['quantity'], 2);
            $total += $item['total_item_price'];
            $count += $item['quantity'];
            $items[] = $item;
        }
        return ['total_items' => $count, 'total_price' => round($total, 2), 'items' => $items];
    }

    private function order(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if ($order === false) {
            return null;
        }
        $items = $this->db->prepare('SELECT id, product_id, product_name, price, quantity, total_price FROM order_items WHERE order_id = ?');
        $items->execute([$id]);
        $order['id'] = (int) $order['id'];
        $order['user_id'] = $order['user_id'] === null ? null : (int) $order['user_id'];
        $order['total_amount'] = (float) $order['total_amount'];
        $order['items'] = array_map(static function (array $item): array {
            $item['id'] = (int) $item['id'];
            $item['product_id'] = $item['product_id'] === null ? null : (int) $item['product_id'];
            $item['price'] = (float) $item['price'];
            $item['quantity'] = (int) $item['quantity'];
            $item['total_price'] = (float) $item['total_price'];
            return $item;
        }, $items->fetchAll());
        return $order;
    }

    private function sendPaginated(string $query, array $params, string $kind): never
    {
        $page = positiveInt($_GET['page'] ?? 1, 'page');
        $maxLimit = $kind === 'products' ? 100 : 100;
        $defaultLimit = $kind === 'products' ? 12 : 10;
        $limit = min($maxLimit, positiveInt($_GET['limit'] ?? $defaultLimit, 'limit'));
        $countQuery = preg_replace('/^SELECT .*? FROM /i', 'SELECT COUNT(*) FROM ', $query, 1);
        $countQuery = preg_replace('/ ORDER BY .*$/i', '', (string) $countQuery);
        $count = $this->db->prepare($countQuery);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->db->prepare("$query LIMIT ? OFFSET ?");
        foreach ($params as $index => $param) {
            $stmt->bindValue($index + 1, $param);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, ($page - 1) * $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        if ($kind === 'products') {
            $items = array_map(function (array $row): array {
                $row['id'] = (int) $row['id'];
                $row['category_id'] = $row['category_id'] === null ? null : (int) $row['category_id'];
                $row['price'] = (float) $row['price'];
                $row['original_price'] = $row['original_price'] === null ? null : (float) $row['original_price'];
                $row['stock'] = (int) $row['stock'];
                $row['is_active'] = (bool) $row['is_active'];
                return $row;
            }, $items);
        } else {
            $items = array_map('publicUser', $items);
        }
        sendJson(['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => max(1, (int) ceil($total / $limit)), 'items' => $items]);
    }

    private function updateRow(string $table, int $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }
        $allowedTables = ['users', 'products', 'categories'];
        if (!in_array($table, $allowedTables, true)) {
            throw new LogicException('Unsupported table update.');
        }
        $set = implode(', ', array_map(static fn(string $field): string => "`$field` = ?", array_keys($fields)));
        $stmt = $this->db->prepare("UPDATE `$table` SET $set WHERE id = ?");
        $stmt->execute([...array_values($fields), $id]);
    }

    private function uniqueSlug(string $table, string $value, ?int $exceptId = null): string
    {
        if (!in_array($table, ['products', 'categories'], true)) {
            throw new LogicException('Unsupported slug table.');
        }
        $slug = slugify($value);
        $base = $slug;
        $suffix = 2;
        do {
            $sql = "SELECT id FROM `$table` WHERE slug = ?";
            $params = [$slug];
            if ($exceptId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = $exceptId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetchColumn() === false) {
                return $slug;
            }
            $slug = $base . '-' . $suffix++;
        } while (true);
    }

    private function exists(string $table, int $id): bool
    {
        if (!in_array($table, ['products', 'categories'], true)) {
            throw new LogicException('Unsupported existence check.');
        }
        $stmt = $this->db->prepare("SELECT 1 FROM `$table` WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
    }

    private function scalar(string $query): int|float
    {
        $value = $this->db->query($query)->fetchColumn();
        return str_contains($query, 'SUM(') ? (float) $value : (int) $value;
    }

    private function money(mixed $value, string $field, float $min, bool $allowMin = true): float
    {
        validate(is_numeric($value) && is_finite((float) $value), $field, 'Giá trị phải là số hợp lệ.');
        $amount = (float) $value;
        validate($allowMin ? $amount >= $min : $amount > $min, $field, $allowMin ? "Giá trị không được nhỏ hơn $min." : "Giá trị phải lớn hơn $min.");
        return round($amount, 2);
    }

    private function boolValue(mixed $value, string $field): bool
    {
        validate(is_bool($value) || $value === 0 || $value === 1, $field, 'Giá trị phải là true hoặc false.');
        return (bool) $value;
    }
}
