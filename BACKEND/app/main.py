import logging
from contextlib import asynccontextmanager
from fastapi import FastAPI, Request, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from fastapi.exceptions import RequestValidationError

from app.config import settings
from app.database import engine, Base
from app.models import *  # Import toàn bộ models để Base.metadata nhận diện
from app.routers import (
    auth_router,
    categories_router,
    products_router,
    cart_router,
    orders_router,
    admin_router
)

# Cấu hình logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s"
)
logger = logging.getLogger("ecommerce_api")

@asynccontextmanager
async def lifespan(app: FastAPI):
    # Khởi tạo bảng trong CSDL khi server khởi động
    try:
        logger.info("Đang kiểm tra và khởi tạo bảng trong cơ sở dữ liệu...")
        Base.metadata.create_all(bind=engine)
        logger.info("Cơ sở dữ liệu đã sẵn sàng!")
    except Exception as e:
        logger.error(f"Lỗi khi khởi tạo database: {e}")
    yield
    logger.info("Đang tắt ứng dụng...")

app = FastAPI(
    title=settings.APP_NAME,
    description="""
    Hệ thống REST API hoàn chỉnh cho sàn thương mại điện tử (LTWEB - Nhóm 12).
    - Hỗ trợ đầy đủ Authentication (JWT Bearer) & Role-based Authorization (Admin / Customer).
    - Quản lý Sản phẩm, Danh mục, Giỏ hàng, Đặt hàng & Bảng điều khiển Quản trị viên.
    """,
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
    lifespan=lifespan
)

# Cấu hình CORS cho phép Frontend kết nối
origins = ["*"]
if isinstance(settings.CORS_ORIGINS, list):
    origins = settings.CORS_ORIGINS
elif isinstance(settings.CORS_ORIGINS, str) and settings.CORS_ORIGINS != "*":
    import json
    try:
        origins = json.loads(settings.CORS_ORIGINS)
    except Exception:
        origins = [settings.CORS_ORIGINS]

app.add_middleware(
    CORSMiddleware,
    allow_origins=origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Xử lý lỗi validation dữ liệu đầu vào trả về tiếng Việt dễ hiểu
@app.exception_handler(RequestValidationError)
async def validation_exception_handler(request: Request, exc: RequestValidationError):
    errors = []
    for error in exc.errors():
        field = " -> ".join(str(loc) for loc in error.get("loc", []))
        msg = error.get("msg", "Dữ liệu không hợp lệ")
        errors.append({"field": field, "message": msg})

    return JSONResponse(
        status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
        content={
            "detail": "Dữ liệu gửi lên không đúng định dạng",
            "errors": errors
        }
    )

# Root endpoint
@app.get("/", tags=["Hệ thống"])
def root():
    return {
        "app": settings.APP_NAME,
        "version": "1.0.0",
        "status": "online",
        "docs_url": "/docs",
        "redoc_url": "/redoc"
    }

# Health check endpoint
@app.get("/api/health", tags=["Hệ thống"])
def health_check():
    return {
        "status": "healthy",
        "database": "connected"
    }

# Đăng ký các router API
app.include_router(auth_router)
app.include_router(categories_router)
app.include_router(products_router)
app.include_router(cart_router)
app.include_router(orders_router)
app.include_router(admin_router)
