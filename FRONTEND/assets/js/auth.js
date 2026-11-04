/**
 * auth.js — Xử lý đăng ký, đăng nhập, đăng xuất và trạng thái phiên
 * Thiên Ân — feature/auth
 *
 * API endpoints (PHP Session — do Việt Anh cung cấp):
 *   POST /BACKEND/api/auth.php?action=register
 *   POST /BACKEND/api/auth.php?action=login
 *   POST /BACKEND/api/auth.php?action=logout
 *   GET  /BACKEND/api/auth.php?action=me
 */

const API_BASE = '/BACKEND/api/auth.php';

// ============================================================
// TIỆN ÍCH CHUNG
// ============================================================

/**
 * Gọi API với fetch, trả về { ok, status, data }
 */
async function apiCall(url, options = {}) {
    try {
        const res = await fetch(url, {
            credentials: 'include', // gửi kèm session cookie
            headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
            ...options,
        });
        let data = {};
        try { data = await res.json(); } catch (_) { /* body rỗng */ }
        return { ok: res.ok, status: res.status, data };
    } catch (err) {
        return { ok: false, status: 0, data: { message: 'Không thể kết nối đến máy chủ.' } };
    }
}

/**
 * Lưu thông tin user vào sessionStorage (KHÔNG lưu mật khẩu)
 */
function saveUser(user) {
    sessionStorage.setItem('auth_user', JSON.stringify(user));
}

/**
 * Đọc thông tin user từ sessionStorage
 */
function getUser() {
    try {
        return JSON.parse(sessionStorage.getItem('auth_user')) || null;
    } catch (_) {
        return null;
    }
}

/**
 * Xoá thông tin user khỏi sessionStorage
 */
function clearUser() {
    sessionStorage.removeItem('auth_user');
}

/**
 * Kiểm tra đã đăng nhập chưa
 */
function isLoggedIn() {
    return getUser() !== null;
}

// ============================================================
// ALERT THÔNG BÁO
// ============================================================

function showAlert(message, type = 'error') {
    const box = document.getElementById('alert-box');
    if (!box) return;
    box.textContent = message;
    box.className = `auth-alert alert-${type} show`;
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function hideAlert() {
    const box = document.getElementById('alert-box');
    if (!box) return;
    box.className = 'auth-alert';
}

// ============================================================
// LỖI TỪNG FIELD
// ============================================================

function showFieldError(fieldId, msgId) {
    const input = document.getElementById(fieldId);
    const msg   = document.getElementById(msgId);
    if (input) input.classList.add('input-error');
    if (msg)   msg.classList.add('show');
}

function clearFieldError(fieldId, msgId) {
    const input = document.getElementById(fieldId);
    const msg   = document.getElementById(msgId);
    if (input) input.classList.remove('input-error');
    if (msg)   msg.classList.remove('show');
}

function clearAllErrors() {
    document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    document.querySelectorAll('.error-msg.show').forEach(el => el.classList.remove('show'));
    hideAlert();
}

// ============================================================
// TOGGLE ẨN / HIỆN MẬT KHẨU
// ============================================================

function initTogglePassword() {
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const input    = document.getElementById(targetId);
            const icon     = btn.querySelector('i');
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    });
}

// ============================================================
// PASSWORD STRENGTH (register)
// ============================================================

function checkStrength(password) {
    let score = 0;
    if (password.length >= 6)            score++;
    if (password.length >= 10)           score++;
    if (/[A-Z]/.test(password))          score++;
    if (/[0-9]/.test(password))          score++;
    if (/[^A-Za-z0-9]/.test(password))  score++;

    const levels = [
        { label: '',           color: '#e2e8f0', width: '0%'   },
        { label: 'Yếu',       color: '#ef4444', width: '25%'  },
        { label: 'Trung bình', color: '#f97316', width: '55%'  },
        { label: 'Khá',        color: '#eab308', width: '75%'  },
        { label: 'Mạnh',       color: '#22c55e', width: '100%' },
    ];
    return levels[Math.min(score, 4)];
}

function initPasswordStrength() {
    const pwInput      = document.getElementById('password');
    const strengthWrap = document.getElementById('strength-wrap');
    const fill         = document.getElementById('strength-fill');
    const text         = document.getElementById('strength-text');
    if (!pwInput || !strengthWrap) return;

    pwInput.addEventListener('input', () => {
        const val = pwInput.value;
        if (!val) { strengthWrap.style.display = 'none'; return; }
        strengthWrap.style.display = 'block';
        const level = checkStrength(val);
        fill.style.width      = level.width;
        fill.style.background = level.color;
        text.textContent      = level.label ? `Độ mạnh: ${level.label}` : '';
        text.style.color      = level.color;
    });
}

// ============================================================
// VALIDATE
// ============================================================

function validateLoginForm(email, password) {
    let valid = true;
    clearAllErrors();
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email || !emailRe.test(email)) { showFieldError('email', 'email-error'); valid = false; }
    if (!password)                       { showFieldError('password', 'password-error'); valid = false; }
    return valid;
}

function validateRegisterForm(name, email, password, confirm) {
    let valid = true;
    clearAllErrors();
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!name || name.trim().length < 2)  { showFieldError('name', 'name-error'); valid = false; }
    if (!email || !emailRe.test(email))   { showFieldError('email', 'email-error'); valid = false; }
    if (!password || password.length < 6) { showFieldError('password', 'password-error'); valid = false; }
    if (!confirm || confirm !== password) { showFieldError('password_confirmation', 'confirm-error'); valid = false; }
    return valid;
}

// ============================================================
// FORM ĐĂNG NHẬP
// ============================================================

function initLoginForm() {
    const form = document.getElementById('login-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email    = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        if (!validateLoginForm(email, password)) return;

        const btn = document.getElementById('btn-login');
        btn.classList.add('loading');
        btn.disabled = true;

        const result = await apiCall(`${API_BASE}?action=login`, {
            method: 'POST',
            body: JSON.stringify({ email, password }),
        });

        btn.classList.remove('loading');
        btn.disabled = false;

        if (result.ok && result.data.user) {
            saveUser(result.data.user);
            showAlert('Đăng nhập thành công! Đang chuyển hướng...', 'success');
            const redirect = new URLSearchParams(window.location.search).get('redirect') || '../../index.html';
            setTimeout(() => { window.location.href = redirect; }, 800);
        } else {
            showAlert(result.data.message || 'Email hoặc mật khẩu không chính xác.', 'error');
        }
    });

    // Xoá lỗi khi gõ lại
    document.getElementById('email')?.addEventListener('input', () => clearFieldError('email', 'email-error'));
    document.getElementById('password')?.addEventListener('input', () => clearFieldError('password', 'password-error'));
}

// ============================================================
// FORM ĐĂNG KÝ
// ============================================================

function initRegisterForm() {
    const form = document.getElementById('register-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name     = document.getElementById('name').value.trim();
        const email    = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirm  = document.getElementById('password_confirmation').value;
        if (!validateRegisterForm(name, email, password, confirm)) return;

        const btn = document.getElementById('btn-register');
        btn.classList.add('loading');
        btn.disabled = true;

        const result = await apiCall(`${API_BASE}?action=register`, {
            method: 'POST',
            body: JSON.stringify({ name, email, password, password_confirmation: confirm }),
        });

        btn.classList.remove('loading');
        btn.disabled = false;

        if (result.ok) {
            showAlert('Đăng ký thành công! Đang chuyển đến trang đăng nhập...', 'success');
            setTimeout(() => { window.location.href = 'login.html'; }, 1200);
        } else {
            showAlert(result.data.message || 'Đăng ký thất bại. Vui lòng thử lại.', 'error');
        }
    });

    // Xoá lỗi khi gõ lại
    document.getElementById('name')?.addEventListener('input', () => clearFieldError('name', 'name-error'));
    document.getElementById('email')?.addEventListener('input', () => clearFieldError('email', 'email-error'));
    document.getElementById('password')?.addEventListener('input', () => clearFieldError('password', 'password-error'));
    document.getElementById('password_confirmation')?.addEventListener('input', () => clearFieldError('password_confirmation', 'confirm-error'));
}

// ============================================================
// ĐĂNG XUẤT
// ============================================================

/**
 * Gọi hàm này từ bất kỳ trang nào có nút logout
 */
async function logout() {
    await apiCall(`${API_BASE}?action=logout`, { method: 'POST' });
    clearUser();
    window.location.href = '/FRONTEND/index.html';
}

// ============================================================
// CẬP NHẬT HEADER THEO TRẠNG THÁI ĐĂNG NHẬP
// Dùng cho main.js hoặc bất kỳ trang nào nhúng auth.js
// ============================================================

/**
 * Xác minh session với server, cập nhật lại sessionStorage
 * Gọi khi load trang để đồng bộ trạng thái
 */
async function syncSession() {
    const result = await apiCall(`${API_BASE}?action=me`, { method: 'GET' });
    if (result.ok && result.data.user) {
        saveUser(result.data.user);
    } else {
        clearUser();
    }
}

/**
 * Cập nhật giao diện header dựa trên trạng thái đăng nhập
 * Tìm các element có data-auth để ẩn/hiện
 *
 * Dùng trong HTML:
 *   data-auth="guest"  — chỉ hiện khi chưa đăng nhập
 *   data-auth="user"   — chỉ hiện khi đã đăng nhập
 *   data-auth="admin"  — chỉ hiện khi role = 'admin'
 *   data-auth="username" — hiện tên user
 */
function updateAuthUI() {
    const user = getUser();

    document.querySelectorAll('[data-auth]').forEach(el => {
        const rule = el.getAttribute('data-auth');
        switch (rule) {
            case 'guest':
                el.style.display = user ? 'none' : '';
                break;
            case 'user':
                el.style.display = user ? '' : 'none';
                break;
            case 'admin':
                el.style.display = (user && user.role === 'admin') ? '' : 'none';
                break;
            case 'username':
                if (user) el.textContent = user.name || user.email;
                el.style.display = user ? '' : 'none';
                break;
        }
    });
}

// ============================================================
// KHỞI CHẠY KHI DOM SẴN SÀNG
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
    initTogglePassword();
    initPasswordStrength();
    initLoginForm();
    initRegisterForm();
    updateAuthUI();
});
