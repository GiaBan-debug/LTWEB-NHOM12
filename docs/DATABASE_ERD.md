# Sơ đồ thực thể quan hệ (ERD) & Tài liệu Cơ sở Dữ liệu

Dự án: **BTA STORE (Nhóm 12) — Website Thương Mại Điện Tử Cửa Hàng Thiết Bị Công Nghệ**  
Cập nhật: **17/11/2026** (Phiên bản đồng bộ TV6: Firebase UID, Profile, Vận chuyển, Specs/Warranty)

---

## 1. Sơ đồ ERD (Entity Relationship Diagram)

```mermaid
erDiagram
    users ||--o| carts : "sở hữu 1"
    users ||--o{ orders : "đặt nhiều"
    users ||--o{ posts : "đăng"
    categories ||--o{ products : "chứa nhiều"
    products ||--o{ product_images : "có nhiều ảnh phụ"
    carts ||--o{ cart_items : "chứa nhiều mục"
    products ||--o{ cart_items : "được thêm vào"
    orders ||--o{ order_items : "gồm nhiều dòng"
    products ||--o{ order_items : "được mua trong"

    users {
        int id PK "Khóa chính nội bộ"
        string name "Họ và tên người dùng"
        string email UK "Email đăng nhập"
        string firebase_uid UK "Định danh duy nhất từ Firebase Auth"
        string password "Mật khẩu mã hóa (NULL khi dùng thuần Firebase)"
        enum role "Quyền hạn: user, admin"
        string phone "Số điện thoại nhận hàng mặc định"
        text address "Địa chỉ nhận hàng mặc định"
        string avatar "Đường dẫn ảnh đại diện"
        timestamp created_at "Ngày tạo"
        timestamp updated_at "Ngày cập nhật"
    }

    categories {
        int id PK "Khóa chính danh mục"
        string name "Tên danh mục (Điện thoại, Laptop...)"
        string slug UK "Đường dẫn thân thiện URL"
        text description "Mô tả danh mục"
        timestamp created_at "Ngày tạo"
        timestamp updated_at "Ngày cập nhật"
    }

    products {
        int id PK "Khóa chính sản phẩm"
        int category_id FK "Liên kết categories.id"
        string name "Tên sản phẩm thiết bị"
        string slug UK "Đường dẫn SEO sản phẩm"
        text description "Mô tả tổng quan sản phẩm"
        text specs "Thông số kỹ thuật chi tiết"
        int warranty_months "Thời hạn bảo hành (tháng, mặc định 12)"
        decimal price "Giá niêm yết (VNĐ)"
        int stock "Số lượng tồn kho thực tế"
        string thumbnail "Đường dẫn ảnh chính uploads/products/..."
        enum status "Trạng thái: active (đang bán), inactive (ngừng bán)"
        timestamp created_at "Ngày tạo"
        timestamp updated_at "Ngày cập nhật"
    }

    product_images {
        int id PK "Khóa chính ảnh"
        int product_id FK "Liên kết products.id"
        string image_path "Đường dẫn ảnh phụ"
        tinyint sort_order "Thứ tự sắp xếp hiển thị gallery"
        timestamp created_at "Ngày tạo"
    }

    carts {
        int id PK "Khóa chính giỏ hàng"
        int user_id FK "Mỗi user sở hữu duy nhất 1 giỏ"
        timestamp created_at "Ngày tạo"
        timestamp updated_at "Ngày cập nhật"
    }

    cart_items {
        int id PK "Khóa chính dòng giỏ hàng"
        int cart_id FK "Liên kết carts.id"
        int product_id FK "Liên kết products.id"
        int quantity "Số lượng sản phẩm trong giỏ"
    }

    orders {
        int id PK "Khóa chính mã đơn hàng"
        int user_id FK "Liên kết users.id khách đặt"
        decimal total_price "Tổng tiền hàng (VNĐ)"
        decimal shipping_fee "Phí giao hàng (VNĐ, miễn phí từ 2 triệu)"
        string receiver_name "Họ tên người nhận"
        string phone "Số điện thoại nhận hàng"
        text address "Địa chỉ nhận hàng chi tiết"
        text note "Ghi chú giao hàng"
        string carrier "Đơn vị vận chuyển (GHN, Viettel Post...)"
        string tracking_code "Mã vận đơn giao hàng"
        date estimated_delivery "Ngày giao dự kiến"
        string checkout_key UK "Khóa chống đặt trùng (idempotency)"
        enum status "Trạng thái: pending, processing, shipped, completed, cancelled"
        timestamp created_at "Ngày tạo đơn"
        timestamp updated_at "Ngày cập nhật trạng thái"
    }

    order_items {
        int id PK "Khóa chính dòng chi tiết đơn"
        int order_id FK "Liên kết orders.id"
        int product_id FK "Liên kết products.id"
        int quantity "Số lượng mua"
        decimal unit_price "Đơn giá snapshot tại thời điểm mua"
        string product_name "Tên sản phẩm snapshot tại thời điểm mua"
        string image "Ảnh sản phẩm snapshot"
    }

    posts {
        int id PK "Khóa chính bài viết"
        int user_id FK "Tác giả bài viết"
        string title "Tiêu đề bài viết"
        string slug UK "Đường dẫn bài viết"
        longtext content "Nội dung bài viết"
        string thumbnail "Ảnh bìa bài viết"
        enum status "draft, published"
        timestamp created_at "Ngày tạo"
        timestamp updated_at "Ngày cập nhật"
    }

    banners {
        int id PK "Khóa chính banner"
        string title "Tiêu đề banner"
        string image_path "Ảnh banner"
        string link "Liên kết khi nhấp"
        tinyint sort_order "Thứ tự hiển thị"
        tinyint is_active "1: Kích hoạt, 0: Ẩn"
        timestamp created_at "Ngày tạo"
    }
```

---

## 2. Chi tiết các bảng và phân tích thiết kế

### 2.1. Bảng `users`
- **Mục đích:** Quản lý tài khoản, danh tính, thông tin liên hệ và phân quyền.
- **Tích hợp Firebase Auth:** Cột `firebase_uid` có ràng buộc `UNIQUE`, lưu UID do Firebase Authentication cấp. 
- **Cột `password`:** Cho phép `NULL` khi người dùng đăng ký qua Firebase. Với tài khoản truyền thống/kiểm thử local, mật khẩu được mã hóa bằng thuật toán `bcrypt` (`password_hash`).
- **Khóa chính nội bộ `users.id`:** Luôn được giữ nguyên làm khóa ngoại cho các bảng `carts`, `orders`, `posts`. Không dùng trực tiếp UID của dịch vụ thứ ba làm khóa ngoại hệ thống, đảm bảo tính độc lập và toàn vẹn dữ liệu.

### 2.2. Bảng `products` & `categories`
- **Mục đích:** Quản lý catalogue danh mục và thiết bị điện tử (Điện thoại, Laptop, Phụ kiện, Máy tính bảng).
- **Trường `status`:** Hỗ trợ `active` (đang bán) và `inactive` (ngừng bán). Khi sản phẩm bị ngừng bán (soft delete), khách không thể đặt thêm nhưng các đơn hàng cũ trong lịch sử vẫn hiển thị đầy đủ.
- **Trường `specs` & `warranty_months`:** Lưu thông số kỹ thuật chi tiết (màn hình, chip, RAM, dung lượng pin...) và số tháng bảo hành chính hãng (12, 18, 24, 36 tháng).

### 2.3. Bảng `carts` & `cart_items`
- **Mục đích:** Giỏ hàng cá nhân hóa theo từng người dùng đã đăng nhập (`user_id UNIQUE` trong `carts`).
- **Ràng buộc duy nhất:** `uq_cart_product (cart_id, product_id)` đảm bảo mỗi sản phẩm chỉ xuất hiện 1 dòng trong giỏ, tăng số lượng khi thêm lặp lại.

### 2.4. Bảng `orders` & `order_items`
- **Mục đích:** Quản lý đơn hàng COD, thông tin giao nhận và trạng thái xử lý đơn.
- **Thông tin vận chuyển:** Tích hợp trực tiếp các trường `carrier` (Đơn vị vận chuyển), `tracking_code` (Mã vận đơn), `estimated_delivery` (Ngày giao dự kiến) vào bảng `orders`. Theo quyết định thống nhất của nhóm: loại bỏ phần "thu tiền" phức tạp, không tạo thêm bảng phụ `deliveries` khi chưa cần nghiệp vụ mở rộng riêng.
- **Phí vận chuyển (`shipping_fee`):** Lưu độc lập để bảo toàn lịch sử giao dịch khi chính sách phí thay đổi. Quy tắc demo: Miễn phí giao hàng cho đơn từ 2.000.000 ₫, đơn dưới 2 triệu có phí 30.000 ₫.
- **Ràng buộc chống đặt trùng (`checkout_key`):** `UNIQUE KEY (user_id, checkout_key)` ngăn chặn sự cố gửi lặp request (double-submit) khi khách bấm nhiều lần.
- **Snapshot trong `order_items`:** Các cột `product_name`, `unit_price`, `image` lưu bản sao dữ liệu tại thời điểm đặt hàng. Khi admin cập nhật giá hoặc tên sản phẩm sau này, lịch sử đơn hàng của khách hàng không bị sai lệch.

---

## 3. Thứ tự chạy và chính sách di chuyển dữ liệu (Migration)

1. **Cài đặt mới:** Import trực tiếp `database/schema.sql` sau đó import `database/seed.sql`.
2. **Cập nhật database cũ đã có dữ liệu:** Chạy tệp `database/migration-tv6.sql` để bổ sung các cột mới mà không làm mất dữ liệu hiện có:
   ```sql
   SOURCE database/migration-tv6.sql;
   ```
