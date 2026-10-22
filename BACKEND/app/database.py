import logging
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker, declarative_base
import pymysql
from app.config import settings

logger = logging.getLogger(__name__)

# Kiểm tra nếu dùng SQLite hay MySQL
database_url = settings.DATABASE_URL

def ensure_mysql_database_exists():
    """Tự động tạo database MySQL nếu chưa tồn tại"""
    if "mysql" in database_url:
        try:
            conn = pymysql.connect(
                host=settings.DB_HOST,
                port=int(settings.DB_PORT),
                user=settings.DB_USER,
                password=settings.DB_PASSWORD,
                charset="utf8mb4"
            )
            with conn.cursor() as cursor:
                cursor.execute(
                    f"CREATE DATABASE IF NOT EXISTS `{settings.DB_NAME}` "
                    f"CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
                )
            conn.commit()
            conn.close()
            logger.info(f"Database MySQL `{settings.DB_NAME}` đã sẵn sàng.")
        except Exception as e:
            logger.warning(f"Chưa thể tự động tạo DB MySQL: {e}. Vui lòng đảm bảo MySQL server đang bật.")

# Cố gắng đảm bảo database tồn tại trước khi tạo engine
ensure_mysql_database_exists()

# Cấu hình engine kết nối
connect_args = {}
if database_url.startswith("sqlite"):
    connect_args = {"check_same_thread": False}

engine = create_engine(
    database_url,
    pool_pre_ping=True,
    pool_recycle=3600,
    connect_args=connect_args
)

SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()

def get_db():
    """Dependency cung cấp session kết nối database cho mỗi request"""
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
