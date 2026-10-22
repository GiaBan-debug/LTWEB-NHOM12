from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from app.database import get_db
from app.models.user import User
from app.schemas.user import UserCreate, UserLogin, UserResponse, UserUpdate, ChangePassword, Token
from app.utils.security import hash_password, verify_password, create_access_token
from app.dependencies import get_current_user

router = APIRouter(prefix="/api/auth", tags=["Authentication & Tài khoản"])

@router.post("/register", response_model=Token, status_code=status.HTTP_201_CREATED, summary="Đăng ký tài khoản mới")
def register(user_in: UserCreate, db: Session = Depends(get_db)):
    # 1. Kiểm tra username đã tồn tại chưa
    if db.query(User).filter(User.username == user_in.username).first():
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Tên đăng nhập này đã được sử dụng. Vui lòng chọn tên khác."
        )

    # 2. Kiểm tra email đã tồn tại chưa
    if db.query(User).filter(User.email == user_in.email).first():
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Email này đã được đăng ký tài khoản. Vui lòng sử dụng email khác."
        )

    # 3. Tạo tài khoản mới (Nếu là tài khoản đầu tiên trong DB, tự gán quyền admin)
    user_count = db.query(User).count()
    assigned_role = "admin" if user_count == 0 else "customer"

    new_user = User(
        username=user_in.username,
        email=user_in.email,
        hashed_password=hash_password(user_in.password),
        full_name=user_in.full_name,
        phone=user_in.phone,
        address=user_in.address,
        role=assigned_role,
        is_active=True
    )

    db.add(new_user)
    db.commit()
    db.refresh(new_user)

    # 4. Sinh JWT token
    access_token = create_access_token(data={"sub": str(new_user.id), "role": new_user.role})

    return {
        "access_token": access_token,
        "token_type": "bearer",
        "user": new_user
    }

@router.post("/login", response_model=Token, summary="Đăng nhập tài khoản")
def login(login_data: UserLogin, db: Session = Depends(get_db)):
    # Tìm kiếm theo username hoặc email
    user = db.query(User).filter(
        (User.username == login_data.username_or_email) | (User.email == login_data.username_or_email)
    ).first()

    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Tài khoản hoặc mật khẩu không chính xác"
        )

    if not verify_password(login_data.password, user.hashed_password):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Tài khoản hoặc mật khẩu không chính xác"
        )

    if not user.is_active:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Tài khoản này đã bị khóa. Vui lòng liên hệ quản trị viên."
        )

    # Sinh JWT token
    access_token = create_access_token(data={"sub": str(user.id), "role": user.role})

    return {
        "access_token": access_token,
        "token_type": "bearer",
        "user": user
    }

@router.get("/me", response_model=UserResponse, summary="Lấy thông tin tài khoản hiện tại")
def get_me(current_user: User = Depends(get_current_user)):
    return current_user

@router.put("/me", response_model=UserResponse, summary="Cập nhật thông tin cá nhân")
def update_profile(
    user_update: UserUpdate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    if user_update.full_name is not None:
        current_user.full_name = user_update.full_name
    if user_update.phone is not None:
        current_user.phone = user_update.phone
    if user_update.address is not None:
        current_user.address = user_update.address

    db.commit()
    db.refresh(current_user)
    return current_user

@router.post("/change-password", summary="Đổi mật khẩu")
def change_password(
    pwd_data: ChangePassword,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    if not verify_password(pwd_data.old_password, current_user.hashed_password):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Mật khẩu hiện tại không đúng"
        )

    current_user.hashed_password = hash_password(pwd_data.new_password)
    db.commit()

    return {"message": "Đổi mật khẩu thành công"}
