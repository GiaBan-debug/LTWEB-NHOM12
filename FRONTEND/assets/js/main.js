/**
 * main.js — Cập nhật header theo trạng thái đăng nhập
 * Thiên Ân — feature/auth
 *
 * Nhúng file này vào cuối <body> của index.html và các trang cần hiển thị
 * trạng thái đăng nhập trên header.
 *
 * YÊU CẦU BẮT BUỘC: auth.js phải được nhúng TRƯỚC main.js
 *   <script src="assets/js/auth.js"></script>   ← TRƯỚC
 *   <script src="assets/js/main.js"></script>    ← SAU
 *
 * Cách dùng trong HTML (header của index.html):
 *
 *   <!-- Khu vực đăng nhập/đăng xuất trên nav -->
 *
 *   <!-- Chưa đăng nhập: hiện 2 link này -->
 *   <div data-auth="guest">
 *     <a href="pages/users/login.html">Đăng nhập</a> |
 *     <a href="pages/users/register.html">Đăng ký</a>
 *   </div>
 *
 *   <!-- Đã đăng nhập: hiện tên + nút đăng xuất -->
 *   <div data-auth="user" style="display:none;">
 *     Xin chào, <span data-auth="username"></span>
 *     <button id="btn-logout">Đăng xuất</button>
 *   </div>
 *
 *   <!-- Chỉ hiện với admin -->
 *   <a href="pages/admin/index.html" data-auth="admin" style="display:none;">Quản trị</a>
 */

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Đồng bộ session với server (xác minh token/session còn hợp lệ không)
    await syncSession();

    // 2. Cập nhật giao diện header theo kết quả
    updateAuthUI();

    // 3. Gắn sự kiện nút đăng xuất (nếu có trên trang)
    const btnLogout = document.getElementById('btn-logout');
    if (btnLogout) {
        btnLogout.addEventListener('click', async () => {
            btnLogout.disabled = true;
            btnLogout.textContent = 'Đang đăng xuất...';
            await logout();
        });
    }
});
