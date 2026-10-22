# HƯỚNG DẪN SỬ DỤNG BACKEND FASTAPI (LTWEB - NHÓM 12)

Backend REST API hoàn chỉnh cho dự án sàn thương mại điện tử, được xây dựng bằng **FastAPI**, **SQLAlchemy ORM**, **MySQL** và **JWT Authentication**.

---

## 1. Yêu cầu hệ thống
- Python 3.10+ (Đã cài sẵn Python 3.12 trên máy)
- MySQL Server 5.7+ hoặc 8.0+ (qua XAMPP, WampServer, Laragon hoặc MySQL Installer độc lập)

---

## 2. Cấu trúc thư mục Backend

```text
BACKEND/
├── app/
│   ├── models/             # SQLAlchemy Models (User, Category, Product, CartItem, Order, OrderItem)
│   ├── schemas/            # Pydantic Schemas validate dữ liệu đầu vào & đầu ra
│   ├── routers/            # Các module API endpoints
│   │   ├── auth.py         # /api/auth (Đăng ký, Đăng nhập, Hồ sơ, Đổi mật khẩu)
│   │   ├── categories.py   # /api/categories (Danh mục sản phẩm)
│   │   ├── products.py     # /api/products (Sản phẩm, tìm kiếm, lọc giá, phân trang)
│   │   ├── cart.py         # /api/cart (Giỏ hàng người dùng)
│   │   ├── orders.py       # /api/orders (Đặt hàng checkout, xem lịch sử đơn)
│   │   └── admin.py        # /api/admin (Dashboard thống kê, quản lý user & đơn hàng)
│   ├── utils/              # Tiện ích mã hóa mật khẩu bcrypt, JWT token, slugify
│   ├── config.py           # Cấu hình biến môi trường
│   ├── database.py         # Kết nối SQLAlchemy & Engine MySQL
│   ├── dependencies.py     # Dependency phân quyền User & Admin
│   └── main.py             # FastAPI App chính & CORS Middleware
├── seed_data.py            # Script nạp dữ liệu mẫu & tạo bảng tự động
├── test_api.py             # Bộ test tự động 16/16 trường hợp kiểm thử
├── run.py                  # Script khởi động server nhanh
├── requirements.txt        # Danh sách thư viện phụ thuộc
└── .env                    # File cấu hình biến môi trường cục bộ
```

---

## 3. Cài đặt và Chạy Server

### Bước 1: Khởi động MySQL Server
- Mở **XAMPP Control Panel** và bấm **Start** tại mục **MySQL** (hoặc mở dịch vụ MySQL trên máy của bạn).

### Bước 2: Nạp dữ liệu mẫu ban đầu (Seed Data)
Mở terminal trong thư mục `BACKEND` và chạy:
```bash
python seed_data.py
```
> Script sẽ tự động:
> 1. Tạo database `ltweb_nhom12` trên MySQL (nếu chưa có).
> 2. Khởi tạo toàn bộ cấu trúc bảng: `users`, `categories`, `products`, `cart_items`, `orders`, `order_items`.
> 3. Tạo tài khoản mẫu:
>    - **Admin**: username `admin` | mật khẩu `admin123`
>    - **Khách hàng**: username `customer` | mật khẩu `customer123`
> 4. Tạo các danh mục & sản phẩm mẫu khớp giao diện Shop.

*(Ngoài ra bạn cũng có thể import trực tiếp file [DATABASE/schema.sql](../DATABASE/schema.sql) và [DATABASE/seed.sql](../DATABASE/seed.sql) vào phpMyAdmin)*

### Bước 3: Khởi động FastAPI Server
```bash
python run.py
```
Hoặc dùng uvicorn trực tiếp:
```bash
uvicorn app.main:app --host 0.0.0.0 --port 8000 --reload
```

---

## 4. Tài liệu API tương tác (Swagger UI / ReDoc)
Sau khi server chạy, bạn mở trình duyệt:
- **Swagger UI (Test trực tiếp)**: [http://localhost:8000/docs](http://localhost:8000/docs)
- **ReDoc**: [http://localhost:8000/redoc](http://localhost:8000/redoc)

---

## 5. Tổng hợp danh sách REST API Endpoints

### 🔐 1. Authentication & Tài khoản (`/api/auth`)
| Phương thức | Endpoint | Quyền | Mô tả |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/auth/register` | Public | Đăng ký tài khoản mới (tài khoản đầu tiên tự động thành Admin) |
| `POST` | `/api/auth/login` | Public | Đăng nhập nhận JWT Access Token |
| `GET` | `/api/auth/me` | User | Xem thông tin tài khoản hiện tại |
| `PUT` | `/api/auth/me` | User | Cập nhật họ tên, số điện thoại, địa chỉ |
| `POST` | `/api/auth/change-password` | User | Đổi mật khẩu |

### 📁 2. Danh mục sản phẩm (`/api/categories`)
| Phương thức | Endpoint | Quyền | Mô tả |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/categories` | Public | Lấy tất cả danh mục kèm số lượng sản phẩm |
| `GET` | `/api/categories/{id_or_slug}` | Public | Chi tiết danh mục |
| `POST` | `/api/categories` | Admin | Tạo danh mục mới |
| `PUT` | `/api/categories/{id}` | Admin | Sửa danh mục |
| `DELETE` | `/api/categories/{id}` | Admin | Xóa danh mục |

### 💻 3. Sản phẩm (`/api/products`)
| Phương thức | Endpoint | Quyền | Mô tả |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/products` | Public | Lấy danh sách sản phẩm (có tìm kiếm `q`, lọc `category_id`, khoảng giá `min_price`/`max_price`, sắp xếp, phân trang) |
| `GET` | `/api/products/{id_or_slug}` | Public | Xem chi tiết 1 sản phẩm |
| `POST` | `/api/products` | Admin | Thêm sản phẩm mới |
| `PUT` | `/api/products/{id}` | Admin | Cập nhật thông tin sản phẩm |
| `DELETE` | `/api/products/{id}` | Admin | Xóa sản phẩm |

### 🛒 4. Giỏ hàng (`/api/cart`)
| Phương thức | Endpoint | Quyền | Mô tả |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/cart` | User | Xem giỏ hàng của tài khoản đang đăng nhập |
| `POST` | `/api/cart` | User | Thêm sản phẩm vào giỏ (tự động cộng dồn số lượng) |
| `PUT` | `/api/cart/{cart_item_id}` | User | Sửa số lượng sản phẩm trong giỏ |
| `DELETE` | `/api/cart/{cart_item_id}` | User | Xóa 1 sản phẩm khỏi giỏ |
| `DELETE` | `/api/cart` | User | Xóa toàn bộ giỏ hàng |

### 📦 5. Đơn hàng & Thanh toán (`/api/orders`)
| Phương thức | Endpoint | Quyền | Mô tả |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/orders` | User | Tạo đơn hàng (Checkout) - Tự động tính tiền, trừ kho, xóa giỏ hàng |
| `GET` | `/api/orders` | User | Lịch sử đơn hàng của tôi (phân trang) |
| `GET` | `/api/orders/{order_id}` | User/Admin | Chi tiết đơn hàng |
| `PUT` | `/api/orders/{order_id}/cancel` | User/Admin | Hủy đơn hàng (Chỉ khi status = `pending`, tự động hoàn lại tồn kho) |

### 🛠️ 6. Quản trị viên (`/api/admin`)
| Phương thức | Endpoint | Quyền | Mô tả |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/admin/stats` | Admin | Bảng thống kê doanh thu, đơn hàng, người dùng, sản phẩm sắp hết hàng |
| `GET` | `/api/admin/users` | Admin | Quản lý danh sách người dùng (tìm kiếm, phân trang) |
| `PUT` | `/api/admin/users/{user_id}/role` | Admin | Phân quyền Admin / Customer hoặc khóa tài khoản |
| `GET` | `/api/admin/orders` | Admin | Xem toàn bộ đơn hàng trong hệ thống (lọc theo status) |
| `PUT` | `/api/admin/orders/{order_id}/status` | Admin | Cập nhật trạng thái đơn (`pending`, `confirmed`, `shipping`, `completed`, `cancelled`) |

---

## 6. Chạy Kiểm Thử Tự Động (Unit & Integration Tests)
Để xác nhận toàn bộ hệ thống API hoạt động hoàn hảo:
```bash
python test_api.py
```
Kết quả kiểm tra: **16/16 Test Cases Đạt 100%**.
