import os
import sys

# Đảm bảo in tiếng Việt / emoji trên Windows console không bị lỗi mã hóa cp1252
if sys.platform.startswith("win"):
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
    except Exception:
        pass

# Thêm đường dẫn hiện tại vào sys.path
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

from app.database import engine, SessionLocal, Base, ensure_mysql_database_exists
from app.models import User, Category, Product, CartItem, Order, OrderItem
from app.utils.security import hash_password

def seed():
    print("==================================================")
    print("🚀 Bắt đầu khởi tạo cơ sở dữ liệu và dữ liệu mẫu...")
    print("==================================================")

    # 1. Đảm bảo database MySQL tồn tại
    ensure_mysql_database_exists()

    # 2. Khởi tạo tất cả bảng
    print("📦 Đang tạo các bảng trong Database...")
    try:
        Base.metadata.create_all(bind=engine)
        print("✅ Đã tạo cấu trúc bảng thành công!")
    except Exception as e:
        print(f"❌ Lỗi kết nối CSDL: {e}")
        print("💡 Gợi ý: Hãy bật MySQL trong XAMPP / WampServer hoặc kiểm tra file .env")
        return

    db = SessionLocal()
    try:
        # 3. Tạo tài khoản mẫu
        print("👤 Đang kiểm tra tài khoản người dùng...")
        admin = db.query(User).filter(User.username == "admin").first()
        if not admin:
            admin = User(
                username="admin",
                email="admin@shopdemo.com",
                hashed_password=hash_password("admin123"),
                full_name="Quản Trị Viên Hệ Thống",
                phone="0123456789",
                address="123 Đường Công Nghệ, Quận 1, TP. Hồ Chí Minh",
                role="admin",
                is_active=True
            )
            db.add(admin)
            print("  ➕ Đã thêm tài khoản Admin: admin / admin123")

        customer = db.query(User).filter(User.username == "customer").first()
        if not customer:
            customer = User(
                username="customer",
                email="customer@gmail.com",
                hashed_password=hash_password("customer123"),
                full_name="Nguyễn Văn Khách Hàng",
                phone="0987654321",
                address="456 Đường Mua Sắm, Quận 7, TP. Hồ Chí Minh",
                role="customer",
                is_active=True
            )
            db.add(customer)
            print("  ➕ Đã thêm tài khoản Khách hàng: customer / customer123")

        db.commit()

        # 4. Tạo danh mục mẫu
        print("📁 Đang nạp danh mục sản phẩm...")
        category_data = [
            {
                "name": "PC Gaming",
                "slug": "pc-gaming",
                "description": "Máy tính chơi game cấu hình mạnh mẽ, tối ưu FPS cao",
                "image_url": "assets/images/pc1.jpeg"
            },
            {
                "name": "PC Văn phòng",
                "slug": "pc-van-phong",
                "description": "Máy tính để bàn phục vụ công việc học tập và văn phòng mượt mà",
                "image_url": "assets/images/pc2.jpg"
            },
            {
                "name": "Laptop Gaming",
                "slug": "laptop-gaming",
                "description": "Laptop chơi game hiệu năng cao, tản nhiệt tốt, di động",
                "image_url": "assets/images/pc3.png"
            },
            {
                "name": "Linh kiện máy tính",
                "slug": "linh-kien-may-tinh",
                "description": "CPU, VGA, RAM, Ổ cứng SSD, Mainboard chính hãng",
                "image_url": "assets/images/pc4.png"
            }
        ]

        categories_map = {}
        for cat in category_data:
            c = db.query(Category).filter(Category.slug == cat["slug"]).first()
            if not c:
                c = Category(**cat)
                db.add(c)
                db.commit()
                db.refresh(c)
                print(f"  ➕ Đã tạo danh mục: {c.name}")
            categories_map[c.slug] = c.id

        # 5. Tạo sản phẩm mẫu theo đúng trang demo
        print("💻 Đang nạp sản phẩm mẫu...")
        product_data = [
            {
                "name": "PC TTG GAMING i5 12400F - 16GB DDR4 - RTX 5060 8GB (All NEW - Bảo hành 36 tháng)",
                "slug": "pc-ttg-gaming-i5-12400f-rtx-5060",
                "category_id": categories_map.get("pc-gaming"),
                "description": "Cấu hình chuẩn thi đấu: CPU Core i5-12400F, RAM 16GB RGB 3200MHz, VGA RTX 5060 8GB, SSD 512GB NVMe, Nguồn 650W 80 Plus.",
                "price": 25280000.0,
                "original_price": 27990000.0,
                "stock": 20,
                "image_url": "assets/images/pc1.jpeg",
                "is_active": True
            },
            {
                "name": "PC TTG GAMING CORE ULTRA 5 250K PLUS - RTX 5060 Ti 8GB",
                "slug": "pc-ttg-gaming-core-ultra-5-250k-plus-rtx-5060-ti",
                "category_id": categories_map.get("pc-gaming"),
                "description": "Thế hệ CPU Core Ultra 5 250K mạnh mẽ kết hợp cùng RTX 5060 Ti chiến mượt mọi tựa game AAA ở độ phân giải 2K.",
                "price": 37980000.0,
                "original_price": 39990000.0,
                "stock": 15,
                "image_url": "assets/images/pc2.jpg",
                "is_active": True
            },
            {
                "name": "PC TTG AMD GAMING Ryzen 7 5700X - RTX 3050 8GB (All NEW - Bảo hành 36 tháng)",
                "slug": "pc-ttg-amd-gaming-ryzen-7-5700x-rtx-3050",
                "category_id": categories_map.get("pc-gaming"),
                "description": "Chiến binh đồ họa & Gaming với 8 nhân 16 luồng Ryzen 7 5700X, RAM 16GB, VGA GeForce RTX 3050.",
                "price": 18280000.0,
                "original_price": 19990000.0,
                "stock": 25,
                "image_url": "assets/images/pc3.png",
                "is_active": True
            },
            {
                "name": "PC TTG GAMING CORE ULTRA 5 250K PLUS - RTX 5070 12GB OC",
                "slug": "pc-ttg-gaming-core-ultra-5-250k-plus-rtx-5070",
                "category_id": categories_map.get("pc-gaming"),
                "description": "Đỉnh cao hiệu năng Gaming 4K và đồ họa 3D render cực nhanh với card đồ họa RTX 5070 12GB VRAM.",
                "price": 48980000.0,
                "original_price": 53990000.0,
                "stock": 10,
                "image_url": "assets/images/pc4.png",
                "is_active": True
            },
            {
                "name": "PC TTG AMD Ryzen 5 5500 - RTX 5050 8GB OC (Cấu hình gốc)",
                "slug": "pc-ttg-amd-ryzen-5-5500-rtx-5050",
                "category_id": categories_map.get("pc-gaming"),
                "description": "Cấu hình quốc dân mức giá mềm, xử lý đồ họa mượt mà, chơi tốt GTA V, Valorant, CS2, LOL.",
                "price": 19680000.0,
                "original_price": 24990000.0,
                "stock": 30,
                "image_url": "assets/images/pc5.png",
                "is_active": True
            }
        ]

        for prod in product_data:
            p = db.query(Product).filter(Product.slug == prod["slug"]).first()
            if not p:
                p = Product(**prod)
                db.add(p)
                print(f"  ➕ Đã tạo sản phẩm: {prod['name']}")

        db.commit()

        print("==================================================")
        print("🎉 Khởi tạo dữ liệu mẫu thành công!")
        print("👉 Tài khoản Admin:      admin / admin123")
        print("👉 Tài khoản Khách hàng: customer / customer123")
        print("==================================================")
    except Exception as e:
        db.rollback()
        print(f"❌ Lỗi khi chèn dữ liệu mẫu: {e}")
    finally:
        db.close()

if __name__ == "__main__":
    seed()
