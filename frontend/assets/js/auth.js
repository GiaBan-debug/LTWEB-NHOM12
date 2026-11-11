/* Xác thực: đăng ký, đăng nhập, đăng xuất, phiên (cookie PHP session). */
(() => {
  "use strict";

  const USER_CACHE_KEY = "shop_user"; // không lưu mật khẩu
  const L = {
    login: "\u0110\u0103ng nh\u1eadp",
    register: "\u0110\u0103ng k\u00fd",
    hello: "Xin ch\u00e0o, ",
    logout: "\u0110\u0103ng xu\u1ea5t",
    error: "C\u00f3 l\u1ed7i x\u1ea3y ra, vui l\u00f2ng th\u1eed l\u1ea1i.",
  };

  function pathParts() {
    const p = location.pathname.replace(/\\/g, "/");
    const lower = p.toLowerCase();
    const marker = "/frontend/";
    const i = lower.lastIndexOf(marker);
    let extra = 0;
    if (i !== -1) {
      extra = p.slice(i + marker.length).split("/").filter(Boolean).length - 1;
      if (extra < 0) extra = 0;
    } else if (/\/pages\//i.test(p)) {
      extra = p.toLowerCase().includes("/pages/users/") ? 2 : 1;
    }
    const toFrontend = extra === 0 ? "" : "../".repeat(extra);
    const pagesPrefix = extra === 0 ? "pages/" : extra === 1 ? "" : "../".repeat(extra - 1);
    return {
      homeHref: toFrontend + "index.html",
      pagesPrefix,
      apiBase: toFrontend + "../backend/api/",
      adminHref: toFrontend + "../backend/admin/index.php",
    };
  }

  const { homeHref, pagesPrefix, apiBase, adminHref } = pathParts();
  const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function apiUrl(file) {
    return apiBase + file;
  }

  function escapeHtml(s) {
    return String(s ?? "").replace(/[&<>"']/g, (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c],
    );
  }

  function isAdmin(user) {
    return String(user?.role || "").toLowerCase() === "admin";
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
        (data && data.message) || L.error,
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
      "login.html\">" +
      L.login +
      "</a> | " +
      '<a href="' +
      pagesPrefix +
      'register.html">' +
      L.register +
      "</a>"
    );
  }

  function userHtml(user) {
    const adminLink = isAdmin(user)
      ? '<a class="auth-admin" href="' + adminHref + '">Admin</a>'
      : "";
    return (
      '<span class="auth-user">' +
      L.hello +
      escapeHtml(user.name) +
      "</span>" +
      adminLink +
      '<button type="button" class="btn-logout" id="btn-logout">' +
      L.logout +
      "</button>"
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
    adminHref,
    escapeHtml,
    emailRe,
    isAdmin,
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
      return request("POST", "register.php", { name, email, password });
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
      return pagesPrefix + file;
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
