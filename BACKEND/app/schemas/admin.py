from typing import Optional, List
from pydantic import BaseModel, Field
from app.schemas.order import OrderResponse

class DashboardStats(BaseModel):
    total_users: int = 0
    total_products: int = 0
    total_categories: int = 0
    total_orders: int = 0
    total_revenue: float = 0.0
    pending_orders: int = 0
    low_stock_products: int = 0
    recent_orders: List[OrderResponse] = []

class UserRoleUpdate(BaseModel):
    role: str = Field(..., description="Quyền người dùng: 'admin' hoặc 'customer'")
    is_active: Optional[bool] = Field(None, description="Trạng thái kích hoạt tài khoản")
