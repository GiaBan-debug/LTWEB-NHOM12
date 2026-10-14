
website-ban-hang/
│
├── public/                              # Giao diện website
│   ├── index.html                       # Trang chủ
│   │
│   ├── pages/                           # Khu vực User
│   │   ├── products.html                # Danh sách sản phẩm
│   │   ├── product-detail.html          # Chi tiết sản phẩm
│   │   ├── categories.html              # Danh mục
│   │   ├── cart.html                    # Giỏ hàng
│   │   ├── checkout.html                # Đặt hàng
│   │   ├── login.html                   # Đăng nhập
│   │   ├── register.html                # Đăng ký
│   │   ├── profile.html                 # Hồ sơ cá nhân
│   │   └── order-history.html            # Lịch sử đơn hàng
│   │
│   ├── admin/                           # Khu vực Admin
│   │   ├── index.html                   # Dashboard
│   │   ├── products.html                # Danh sách sản phẩm
│   │   ├── product-form.html            # Thêm / sửa sản phẩm
│   │   ├── categories.html              # Danh sách danh mục
│   │   ├── category-form.html           # Thêm / sửa danh mục
│   │   ├── orders.html                  # Quản lý đơn hàng
│   │   ├── order-detail.html            # Chi tiết đơn hàng
│   │   ├── users.html                   # Quản lý tài khoản
│   │   ├── user-form.html               # Thêm / sửa tài khoản
│   │   ├── posts.html                   # Quản lý bài viết
│   │   ├── post-form.html               # Thêm / sửa bài viết
│   │   ├── banners.html                 # Quản lý banner
│   │   ├── banner-form.html             # Thêm / sửa banner
│   │   └── settings.html                # Cấu hình website
│   │
│   └── assets/
│       ├── css/
│       │   ├── style.css                # CSS dùng chung
│       │   ├── header.css               # Header và menu
│       │   ├── home.css                 # Trang chủ
│       │   ├── products.css             # Danh sách sản phẩm
│       │   ├── forms.css                # Biểu mẫu
│       │   ├── admin.css                # Giao diện Admin
│       │   └── responsive.css            # Responsive
│       │
│       ├── js/
│       │   ├── main.js                  # Chức năng chung
│       │   ├── products.js              # Tìm kiếm / lọc sản phẩm
│       │   ├── cart.js                  # Giỏ hàng
│       │   ├── checkout.js              # Đặt hàng
│       │   ├── auth.js                  # Đăng nhập / đăng ký
│       │   ├── pagination.js            # Phân trang giao diện
│       │   ├── editor.js                # Khởi tạo trình soạn thảo
│       │   └── admin.js                 # Chức năng quản trị
│       │
│       └── images/
│           ├── logo/
│           ├── banners/
│           ├── products/
│           └── categories/
│
├── backend/                             # Backend PHP
│   ├── config/
│   │   ├── database.php                 # Kết nối MySQL
│   │   └── app.php                      # Cấu hình ứng dụng
│   │
│   ├── controllers/                     # Xử lý nghiệp vụ
│   │   ├── AuthController.php           # Đăng ký / đăng nhập / đăng xuất
│   │   ├── ProductController.php        # CRUD sản phẩm
│   │   ├── CategoryController.php       # CRUD danh mục
│   │   ├── OrderController.php          # Quản lý đơn hàng
│   │   ├── UserController.php           # Quản lý tài khoản
│   │   ├── PostController.php           # CRUD bài viết
│   │   └── BannerController.php         # CRUD banner
│   │
│   ├── models/                          # Làm việc với dữ liệu
│   │   ├── User.php
│   │   ├── Product.php
│   │   ├── ProductImage.php
│   │   ├── Category.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── Post.php
│   │   └── Banner.php
│   │
│   ├── middleware/                      # Xác thực và phân quyền
│   │   ├── AuthMiddleware.php           # Kiểm tra đăng nhập
│   │   ├── AdminMiddleware.php          # Chỉ cho phép Admin
│   │   └── OwnershipMiddleware.php       # Kiểm tra quyền sở hữu dữ liệu
│   │
│   ├── policies/                        # Quy tắc phân quyền
│   │   ├── ProductPolicy.php
│   │   ├── CategoryPolicy.php
│   │   ├── OrderPolicy.php
│   │   └── UserPolicy.php
│   │
│   ├── api/                             # API cho Frontend
│   │   ├── auth.php                     # Đăng nhập / đăng xuất
│   │   ├── products.php                 # CRUD sản phẩm
│   │   ├── categories.php               # CRUD danh mục
│   │   ├── orders.php                   # Đặt hàng / quản lý đơn
│   │   ├── users.php                    # Quản lý tài khoản
│   │   ├── posts.php                    # CRUD bài viết
│   │   └── banners.php                  # CRUD banner
│   │
│   ├── services/
│   │   ├── UploadService.php            # Upload nhiều ảnh
│   │   ├── PaginationService.php        # Phân trang phía Backend
│   │   └── EditorService.php             # Xử lý nội dung editor
│   │
│   └── utils/
│       ├── Validator.php                # Kiểm tra dữ liệu
│       └── Response.php                 # Chuẩn hóa phản hồi API
│
├── database/                            # Cơ sở dữ liệu
│   ├── schema.sql                       # Tạo cấu trúc bảng
│   └── seed.sql                         # Dữ liệu mẫu
│
├── uploads/                             # Tệp được tải lên
│   ├── products/
│   ├── banners/
│   └── posts/
│
├── docs/                                # Tài liệu dự án
│   ├── requirements.md                  # Yêu cầu chức năng
│   ├── database-design.md               # Thiết kế database
│   ├── authorization.md                 # Đặc tả phân quyền
│   └── installation.md                  # Hướng dẫn cài đặt
│
├── .gitignore
└── README.md
