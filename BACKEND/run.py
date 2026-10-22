import uvicorn
import os
import sys

# Đảm bảo đường dẫn thư mục hiện tại nằm trong PYTHONPATH
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

if __name__ == "__main__":
    from app.config import settings
    print(f"==================================================")
    print(f" Starting {settings.APP_NAME}")
    print(f" Swagger API Docs: http://localhost:{settings.PORT}/docs")
    print(f" ReDoc API Docs:   http://localhost:{settings.PORT}/redoc")
    print(f"==================================================")
    uvicorn.run(
        "app.main:app",
        host=settings.HOST,
        port=settings.PORT,
        reload=settings.DEBUG
    )
