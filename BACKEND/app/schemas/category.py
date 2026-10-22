from datetime import datetime
from typing import Optional
from pydantic import BaseModel, Field

class CategoryBase(BaseModel):
    name: str = Field(..., min_length=2, max_length=150, description="Tên danh mục")
    description: Optional[str] = Field(None, description="Mô tả danh mục")
    image_url: Optional[str] = Field(None, max_length=500, description="Đường dẫn ảnh danh mục")

class CategoryCreate(CategoryBase):
    slug: Optional[str] = Field(None, max_length=150, description="Slug đường dẫn (nếu để trống sẽ tự tạo)")

class CategoryUpdate(BaseModel):
    name: Optional[str] = Field(None, min_length=2, max_length=150)
    slug: Optional[str] = Field(None, max_length=150)
    description: Optional[str] = None
    image_url: Optional[str] = None

class CategoryResponse(CategoryBase):
    id: int
    slug: str
    created_at: datetime
    product_count: Optional[int] = 0

    class Config:
        from_attributes = True
