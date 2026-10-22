import os
from typing import List, Union
from pydantic_settings import BaseSettings
from pydantic import field_validator
from dotenv import load_dotenv

# Load file .env nếu có
load_dotenv()

class Settings(BaseSettings):
    APP_NAME: str = "Ecommerce API - LTWEB NHOM 12"
    APP_ENV: str = "development"
    DEBUG: bool = True
    PORT: int = 8000
    HOST: str = "0.0.0.0"

    # MySQL Database Config
    DB_HOST: str = "127.0.0.1"
    DB_PORT: int = 3306
    DB_USER: str = "root"
    DB_PASSWORD: str = ""
    DB_NAME: str = "ltweb_nhom12"

    # Database URL
    DATABASE_URL: str = ""

    # JWT Config
    SECRET_KEY: str = "nhom12_super_secret_jwt_key_ecommerce_2026_xyz"
    ALGORITHM: str = "HS256"
    ACCESS_TOKEN_EXPIRE_MINUTES: int = 1440  # 1 ngày

    # CORS Config
    CORS_ORIGINS: Union[List[str], str] = ["*"]

    @field_validator("DATABASE_URL", mode="before")
    def assemble_db_connection(cls, v, values):
        if isinstance(v, str) and v.strip():
            return v.strip()
        # Nếu chưa cung cấp DATABASE_URL thì tự sinh từ thông tin MySQL
        user = os.getenv("DB_USER", "root")
        pwd = os.getenv("DB_PASSWORD", "")
        host = os.getenv("DB_HOST", "127.0.0.1")
        port = os.getenv("DB_PORT", "3306")
        db = os.getenv("DB_NAME", "ltweb_nhom12")
        auth = f"{user}:{pwd}" if pwd else user
        return f"mysql+pymysql://{auth}@{host}:{port}/{db}?charset=utf8mb4"

    class Config:
        env_file = ".env"
        extra = "allow"

settings = Settings()
