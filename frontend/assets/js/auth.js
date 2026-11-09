/* Xác thực: đăng ký, đăng nhập, đăng xuất, phiên (cookie PHP session). */
(() => {
  "use strict";

  const USER_CACHE_KEY = "shop_user"; // không lưu mật khẩu
  const inPages = /\/pages(?:\/|$)/i.test(location.pathname.replace(/\\/g, "/"));
  const pagesPrefix = inPages ? "" : "pages/";
  const homeHref = inPages ? "../index.html" : "index.html";
  const apiBase = inPages ? "../../backend/api/" : "../backend/api/";

  const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function apiUrl(file) {
    return apiBase + file;
  }

  function escapeHtml(s) {
    return String(s ?? "").replace(/[&<>"']/g, (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c],
    );
  }

  function readCache() {
    try {
      const raw = sessionStorage.getItem(USER_CACHE_KEY);
      if (!raw) return null;
      const user = JSON.parse(raw);
      if (!user || typeof user !== "object" || !user.name) return null;
      return user;
    } catch {
      return null;
    }
  }

  function writeCache(user) {
    if (!user) {
      sessionStorage.removeItem(USER_CACHE_KEY);
      return;
    }
    sessionStorage.setItem(
      USER_CACHE_KEY,
      JSON.stringify({
        id: user.id,
        name: user.name,
        email: user.email,
        role: user.role,
      }),
    );
  }

  async function request(method, file, body) {
    const res = await fetch(apiUrl(file), {
      method,
      credentials: "include",
      headers: {
        Accept: "application/json",
        ...(body ? { "Content-Type": "application/json" } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    let data = null;
    try {
      data = await res.json();
    } catch {
      /* không phải JSON */
    }
    if (!res.ok || !data || data.success === false) {
      const err = new Error(
        (data && data.message) || "Có lỗi xảy ra, vui lòng thử lại.",
      );
      err.status = res.status;
      err.payload = data;
      throw err;
    }
    return data;
  }

  async function fetchSessionUser() {
    try {
      const res = await request("GET", "me.php");
      writeCache(res.data);
      return res.data;
    } catch (e) {
      if (e.status === 401 || e.status === 403) {
        writeCache(null);
        return null;
      }
      return readCache();
    }
  }

  function guestHtml() {
    return (
      '<a href="' +
      pagesPrefix +
      'login.html">Đăng nhập</a> | ' +
      '<a href="' +
      pagesPrefix +
      'register.html">Đăng ký</a>'
    );
  }

  function userHtml(user) {
    return (
      '<span class="auth-user">Xin chào, ' +
      escapeHtml(user.name) +
      "</span>" +
      '<button type="button" class="btn-logout" id="btn-logout">Đăng xuất</button>'
    );
  }

  function renderHeader(user) {
    const el = document.getElementById("auth-area");
    if (!el) return;
    el.innerHTML = user ? userHtml(user) : guestHtml();
    const btn = document.getElementById("btn-logout");
    if (btn) {
      btn.addEventListener("click", async () => {
        btn.disabled = true;
        try {
          await Auth.logout();
          location.href = homeHref;
        } catch (e) {
          btn.disabled = false;
          alert(e.message);
        }
      });
    }
  }

  const Auth = {
    homeHref,
    pagesPrefix,
    apiUrl,
    escapeHtml,
    emailRe,
    getCachedUser: readCache,

    async currentUser() {
      return fetchSessionUser();
    },

    async mountHeader() {
      renderHeader(readCache());
      const user = await fetchSessionUser();
      renderHeader(user);
      return user;
    },

    async register({ name, email, password }) {
      const res = await request("POST", "register.php", {
        name,
        email,
        password,
      });
      return res;
    },

    async login({ email, password }) {
      const res = await request("POST", "login.php", { email, password });
      writeCache(res.data);
      return res.data;
    },

    async logout() {
      try {
        await request("POST", "logout.php");
      } finally {
        writeCache(null);
      }
    },

    safeRedirect(raw) {
      if (!raw) return homeHref;
      const value = String(raw).trim().replace(/^pages\//, "");
      const file = value.split("?")[0];
      if (!value || /[:\\]|\/\//.test(value) || value.includes("..")) {
        return homeHref;
      }
      if (!/^[a-z0-9._-]+\.html$/i.test(file)) return homeHref;
      return (inPages ? "" : "pages/") + file;
    },

    setStatus(el, message, type) {
      if (!el) return;
      el.hidden = !message;
      el.textContent = message || "";
      el.className = "auth-status" + (type ? " auth-status--" + type : "");
    },
  };

  window.Auth = Auth;
})();
