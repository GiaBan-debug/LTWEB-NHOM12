<?php
// BACKEND/controllers/ProductController.php

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../services/UploadService.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class ProductController {
    private Product $product;
    private Category $category;
    private UploadService $upload;

    public function __construct() {
        $this->product  = new Product();
        $this->category = new Category();
        $this->upload   = new UploadService();
    }

    /** GET /api/products */
    public function index(): void {
        $filters = [
            'category_id' => $_GET['category_id'] ?? null,
            'search'      => $_GET['search']      ?? null,
            'min_price'   => $_GET['min_price']   ?? null,
            'max_price'   => $_GET['max_price']   ?? null,
        ];
        $page  = max(1, (int) ($_GET['page']  ?? 1));
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 12)));

        $result = $this->product->getAll($filters, $page, $limit);
        Response::json($result);
    }

    /** GET /api/products/{id} */
    public function show(int $id): void {
        $product = $this->product->getById($id);
        if (!$product) {
            Response::json(['message' => 'Không tìm thấy sản phẩm'], 404);
            return;
        }
        Response::json($product);
    }

    /** POST /api/products  (admin only) */
    public function store(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $errors = Validator::validate($data, [
            'category_id' => 'required|integer',
            'name'        => 'required|max:200',
            'price'       => 'required|numeric',
        ]);
        if ($errors) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $data['slug'] = $this->makeSlug($data['name']);

        // Xu ly anh thumbnail neu co upload
        if (!empty($_FILES['thumbnail'])) {
            $path = $this->upload->uploadImage($_FILES['thumbnail'], 'products');
            if (!$path) {
                Response::json(['message' => 'Upload ảnh thất bại'], 500);
                return;
            }
            $data['thumbnail'] = $path;
        }

        $id = $this->product->create($data);

        // Xu ly nhieu anh
        if (!empty($_FILES['images'])) {
            $files = $this->normalizeFiles($_FILES['images']);
            foreach ($files as $i => $file) {
                $path = $this->upload->uploadImage($file, 'products');
                if ($path) $this->product->addImage($id, $path, $i);
            }
        }

        Response::json(['message' => 'Tạo sản phẩm thành công', 'id' => $id], 201);
    }

    /** PUT /api/products/{id}  (admin only) */
    public function update(int $id): void {
        $product = $this->product->getById($id);
        if (!$product) {
            Response::json(['message' => 'Không tìm thấy sản phẩm'], 404);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $errors = Validator::validate($data, [
            'category_id' => 'required|integer',
            'name'        => 'required|max:200',
            'price'       => 'required|numeric',
        ]);
        if ($errors) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $data['slug'] = $this->makeSlug($data['name']);

        if (!empty($_FILES['thumbnail'])) {
            $path = $this->upload->uploadImage($_FILES['thumbnail'], 'products');
            if ($path) $data['thumbnail'] = $path;
        }

        $this->product->update($id, $data);
        Response::json(['message' => 'Cập nhật sản phẩm thành công']);
    }

    /** DELETE /api/products/{id}  (admin only) */
    public function destroy(int $id): void {
        $product = $this->product->getById($id);
        if (!$product) {
            Response::json(['message' => 'Không tìm thấy sản phẩm'], 404);
            return;
        }
        $this->product->delete($id);
        Response::json(['message' => 'Xóa sản phẩm thành công']);
    }

    // ---- Helper ----

    private function makeSlug(string $text): string {
        $text = mb_strtolower($text, 'UTF-8');
        $map  = ['à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
                  'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
                  'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
                  'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
                  'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
                  'ý'=>'y','ÿ'=>'y','đ'=>'d',
                  'ắ'=>'a','ặ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a',
                  'ấ'=>'a','ậ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a',
                  'ế'=>'e','ệ'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e',
                  'ố'=>'o','ộ'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o',
                  'ớ'=>'o','ợ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o',
                  'ứ'=>'u','ự'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u',
                  'ị'=>'i','ỉ'=>'i','ĩ'=>'i',
                  'ỳ'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',
                  'ạ'=>'a','ả'=>'a','ã'=>'a',
                  'ọ'=>'o','ỏ'=>'o',
                  'ụ'=>'u','ủ'=>'u'];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', trim($text));
        return $text . '-' . time();
    }

    private function normalizeFiles(array $files): array {
        $result = [];
        if (is_array($files['name'])) {
            foreach ($files['name'] as $i => $name) {
                $result[] = [
                    'name'     => $name,
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
            }
        } else {
            $result[] = $files;
        }
        return $result;
    }
}