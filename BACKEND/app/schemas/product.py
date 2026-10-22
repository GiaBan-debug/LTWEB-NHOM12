from datetime import datetime
from typing import Optional, List
from pydantic import BaseModel, Field

class ProductBase(BaseModel):
    name: str = Field(..., min_length=2, max_length=255, description="Tên sản phẩm")
    category_id: Optional[int] = Field(None, description="ID danh mục liên kết")
    description: Optional[str] = Field(None, description="Mô tả chi tiết sản phẩm")
    price: float = Field(..., gt=0, description="Giá bán hiện tại (VNĐ)")
    original_price: Optional[float] = Field(None, ge=0, description="Giá gốc trước khi giảm (VNĐ)")
    stock: int = Field(0, ge=0, description="Số lượng tồn kho")
    image_url: Optional[str] = Field(None, max_length=500, description="Đường dẫn ảnh sản phẩm")
    is_active: bool = Field(True, description="Trạng thái hiển thị")

class ProductCreate(ProductBase):
    slug: Optional[str] = Field(None, max_length=255, description="Slug sản phẩm (tự tạo nếu để trống)")

class ProductUpdate(BaseModel):
    name: Optional[str] = Field(None, min_length=2, max_length=255)
    category_id: Optional[int] = None
    slug: Optional[str] = Field(None, max_length=255)
    description: Optional[str] = None
    price: Optional[float] = Field(None, gt=0)
    original_price: Optional[float] = Field(None, ge=0)
    stock: Optional[int] = Field(None, ge=0)
    image_url: Optional[str] = None
    is_active: Optional[bool] = None

class ProductResponse(ProductBase):
    id: int
    slug: str
    category_name: Optional[str] = None
    created_at: datetime
    updated_at: datetime

    class Config:
        from_attributes = True

class ProductListResponse(BaseModel):
    total: int
    page: int
    limit: int
    total_pages: int
    items: List[ProductResponse]
