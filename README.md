
# LTWEB-NHOM12 - WEBSITE BÁN HÀNG

## 1. Phân chia công việc cho 6 thành viên

```text
LTWEB-NHOM12/
│
├── THANH VIEN 1 - FRONTEND SAN PHAM
│   └── FRONTEND/
│       ├── index.html
│       ├── pages/users/
│       │   ├── products.html
│       │   ├── product-detail.html
│       │   └── categories.html
│       └── assets/
│           ├── css/
│           │   ├── style.css
│           │   ├── products.css
│           │   └── responsive.css
│           └── js/
│               └── products.js
│
├── THANH VIEN 2 - GIO HANG VA DAT HANG
│   └── FRONTEND/
│       ├── pages/users/
│       │   ├── cart.html
│       │   ├── checkout.html
│       │   └── order-history.html
│       └── assets/js/
│           ├── cart.js
│           └── checkout.js
│
├── THANH VIEN 3 - TAI KHOAN VA XAC THUC
│   ├── FRONTEND/
│   │   ├── pages/users/
│   │   │   ├── login.html
│   │   │   ├── register.html
│   │   │   └── profile.html
│   │   └── assets/js/auth.js
│   └── BACKEND/
│       ├── api/auth.php
│       ├── controllers/AuthController.php
│       ├── models/User.php
│       └── middleware/AuthMiddleware.php
│
├── THANH VIEN 4 - DATABASE VA API SAN PHAM
│   ├── DATABASE/
│   │   ├── schema.sql
│   │   └── seed.sql
│   └── BACKEND/
│       ├── config/database.php
│       ├── api/
│       │   ├── products.php
│       │   └── categories.php
│       ├── controllers/
│       │   ├── ProductController.php
│       │   └── CategoryController.php
│       ├── models/
│       │   ├── Product.php
│       │   ├── Category.php
│       │   └── ProductImage.php
│       └── services/UploadService.php
│
├── THANH VIEN 5 - TRANG ADMIN VA QUAN LY
│   ├── FRONTEND/
│   │   ├── pages/admin/
│   │   └── assets/
│   │       ├── css/admin.css
│   │       └── js/admin.js
│   └── BACKEND/
│       ├── api/admin.php
│       ├── controllers/
│       │   ├── OrderController.php
│       │   ├── UserController.php
│       │   ├── PostController.php
│       │   └── BannerController.php
│       └── models/
│           ├── Post.php
│           └── Banner.php
│
└── THANH VIEN 6 - TICH HOP HE THONG VA GITHUB
    ├── BACKEND/
    │   ├── public/index.php
    │   └── routes/
    │       ├── web.php
    │       └── api.php
    ├── docs/
    │   ├── api-contract.md
    │   └── installation.md
    ├── README.md
    ├── .gitignore
    └── .env.example
```

## 2. Nhiệm vụ và sản phẩm bàn giao

| Thành viên | Nhiệm vụ chính | Sản phẩm bàn giao |
|---|---|---|
| 1 | Giao diện trang chủ, danh sách sản phẩm, chi tiết sản phẩm, danh mục | HTML, CSS, JavaScript giao diện sản phẩm |
| 2 | Giỏ hàng, cập nhật số lượng, thanh toán, lịch sử đơn hàng | Giao diện và logic giỏ hàng, đặt hàng |
| 3 | Đăng ký, đăng nhập, đăng xuất, hồ sơ người dùng | Giao diện tài khoản và API xác thực |
| 4 | Thiết kế cơ sở dữ liệu, kết nối MySQL, quản lý sản phẩm và danh mục | SQL, kết nối PDO, model và API sản phẩm |
| 5 | Giao diện quản trị, quản lý sản phẩm, đơn hàng, người dùng, bài viết và banner | Trang Admin và chức năng quản trị |
| 6 | Tích hợp các module, thống nhất API, quản lý GitHub và tài liệu | Router, hợp đồng API, README và hướng dẫn cài đặt |

## 3. Quy trình bàn giao

1. Mỗi thành viên làm việc trên nhánh Git riêng.
2. Hoàn thành chức năng thì commit và push lên GitHub.
3. Tạo Pull Request để xem xét và hợp nhất vào nhánh `main`.
4. Thống nhất tên API, cấu trúc JSON và cách kết nối giữa Frontend với Backend trước khi tích hợp.
5. Không tự ý sửa file thuộc module của thành viên khác nếu chưa trao đổi.
6. Không đưa mật khẩu database hoặc thông tin bí mật vào GitHub.

## 4. Nguyên tắc phối hợp

- Thành viên 4 cung cấp cấu trúc database và API sản phẩm cho thành viên 1.
- Thành viên 3 thống nhất cơ chế đăng nhập với thành viên 5 để bảo vệ trang Admin.
- Thành viên 2 thống nhất dữ liệu giỏ hàng và đơn hàng với thành viên 4.
- Thành viên 5 thống nhất API quản trị với thành viên 4 và thành viên 6.
- Thành viên 6 quản lý tích hợp và tài liệu, không tự nhận toàn bộ phần code của các thành viên khác.

