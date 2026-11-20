/* Firebase identity -> verified PHP session; MySQL remains the source of role/profile. */
(() => {
  "use strict";
  const root = new URL("../../../", document.currentScript.src);
  const url = (path) => new URL(path, root).href;
  const USER_CACHE_KEY = "shop_user";
  const homeHref = url("frontend/index.html");
  const pagesPrefix = url("frontend/pages/");
  const adminHref = url("backend/admin/index.php");
  const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const escapeHtml = (value) =>
    String(value ?? "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
  let configPromise,
    userPromise,
    loggingOut = false;
  const request = (method, file, body) => Shop.api(file, { method, body });
  function config() {
    configPromise ??= request("GET", "auth-config.php")
      .then((r) => r.data)
      .catch((error) => {
        configPromise = null;
        throw error;
      });
    return configPromise;
  }
  async function client() {
    const c = await config();
    const module = await import(url("frontend/assets/js/firebase-auth.js"));
    return module.firebaseClient(c.firebase);
  }
  function readableError(error) {
    const messages = {
      "auth/invalid-credential": "Email hoặc mật khẩu không đúng.",
      "auth/wrong-password": "Email hoặc mật khẩu không đúng.",
      "auth/user-not-found": "Email hoặc mật khẩu không đúng.",
      "auth/email-already-in-use":
        "Email đã được đăng ký. Hãy đăng nhập hoặc đặt lại mật khẩu.",
      "auth/invalid-email": "Email không hợp lệ.",
      "auth/weak-password": "Mật khẩu chưa đủ mạnh. Hãy dùng ít nhất 6 ký tự.",
      "auth/operation-not-allowed":
        "Phương thức đăng nhập chưa bật trong Firebase Authentication → Sign-in method.",
      "auth/too-many-requests":
        "Đã thử quá nhiều lần. Vui lòng chờ rồi thử lại.",
      "auth/network-request-failed":
        "Không kết nối được Firebase. Hãy kiểm tra mạng rồi thử lại.",
      "auth/user-disabled": "Tài khoản Firebase đã bị vô hiệu hóa.",
      "auth/unauthorized-domain":
        "Tên miền này chưa được cho phép trong Firebase Authentication.",
      "auth/invalid-api-key": "Cấu hình Firebase Web App không hợp lệ.",
      "auth/popup-blocked":
        "Trình duyệt đang chặn popup. Hãy cho phép popup rồi thử lại.",
      "auth/popup-closed-by-user": "Bạn đã đóng cửa sổ đăng nhập Google.",
      "auth/cancelled-popup-request":
        "Một cửa sổ đăng nhập khác đang mở. Hãy hoàn tất cửa sổ đó.",
    };
    return new Error(
      messages[error.code] ||
        error.message ||
        "Không thực hiện được yêu cầu xác thực.",
    );
  }
  function readCache() {
    try {
      return JSON.parse(sessionStorage.getItem(USER_CACHE_KEY) || "null");
    } catch {
      return null;
    }
  }
  function writeCache(user) {
    try {
      if (!user) sessionStorage.removeItem(USER_CACHE_KEY);
      else
        sessionStorage.setItem(
          USER_CACHE_KEY,
          JSON.stringify({
            id: user.id,
            name: user.name,
            email: user.email,
            role: user.role,
            email_verified: user.email_verified,
            auth_provider: user.auth_provider,
          }),
        );
    } catch {
      /* UI cache is optional; backend session is authoritative. */
    }
  }
  async function bridge(user, force = false) {
    if (loggingOut) throw new Error("Đang đăng xuất.");
    const idToken = await user.getIdToken(force);
    if (loggingOut) throw new Error("Đang đăng xuất.");
    const result = await request("POST", "firebase-session.php", {
      id_token: idToken,
    });
    writeCache(result.data);
    return result.data;
  }
  async function sessionUser() {
    try {
      const result = await request("GET", "me.php");
      writeCache(result.data);
      return result.data;
    } catch (error) {
      if (error.status !== 401 && error.status !== 403) throw error;
      writeCache(null);
      if ((await config()).mode !== "firebase") return null;
      const fb = await client();
      if (!fb.auth.currentUser) return null;
      try {
        return await bridge(fb.auth.currentUser);
      } catch (bridgeError) {
        // Invalid/colliding identities must not repeatedly recreate sessions on page load.
        if ([401, 409].includes(bridgeError.status)) await fb.signOut(fb.auth);
        throw bridgeError;
      }
    }
  }
  function currentUser() {
    if (loggingOut) return Promise.resolve(null);
    userPromise ??= sessionUser().finally(() => {
      userPromise = null;
    });
    return userPromise;
  }
  function renderHeader(user) {
    const area = document.querySelector("#auth-area");
    if (!area) return;
    if (!user) {
      area.innerHTML = `<a href="${pagesPrefix}login.html">Đăng nhập</a><span aria-hidden="true"> | </span><a href="${pagesPrefix}register.html">Đăng ký</a>`;
      return;
    }
    area.innerHTML = `<a class="auth-user" href="${url("frontend/pages/users/profile.html")}">Xin chào, ${escapeHtml(user.name)}</a>${user.auth_provider === "firebase" && !user.email_verified ? `<a href="${pagesPrefix}verify-email.html">Xác minh email</a>` : ""}${user.role === "admin" ? `<a class="auth-admin" href="${adminHref}">Quản trị</a>` : ""}<button type="button" class="btn-logout" id="btn-logout">Đăng xuất</button>`;
    area
      .querySelector("#btn-logout")
      .addEventListener("click", async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
          await Auth.logout();
          location.href = homeHref;
        } catch (error) {
          Shop.toast(error.message, true);
          button.disabled = false;
        }
      });
  }
  const Auth = {
    homeHref,
    pagesPrefix,
    adminHref,
    apiUrl: (file) => url("backend/api/" + file),
    escapeHtml,
    emailRe,
    config,
    currentUser,
    async setupAuthPage(button, statusEl) {
      button.disabled = true;
      try {
        const c = await config();
        button.hidden = c.mode !== "firebase";
        const emailEnabled = c.mode === "legacy" || c.providers.email_password;
        const fields = document.querySelector("#email-password-fields");
        if (fields) fields.hidden = !emailEnabled;
        const forgot = document.querySelector("#forgot-password-link");
        if (forgot) forgot.hidden = !emailEnabled;
        if (!emailEnabled && !document.querySelector('#phone-fields'))
          document.querySelector(".auth-lead").textContent =
            "Tiếp tục bằng tài khoản Google. Lần đầu đăng nhập sẽ tự tạo tài khoản mua hàng.";
        if (c.mode === "firebase") await client();
        button.disabled = false;
      } catch (error) {
        Auth.setStatus(
          statusEl,
          "Không tải được Firebase. Kiểm tra kết nối mạng và tải lại trang.",
          "error",
        );
      }
    },
    isAdmin: (user) => user?.role === "admin",
    getCachedUser: readCache,
    async mountHeader() {
      try {
        const user = await currentUser();
        renderHeader(user);
        return user;
      } catch (error) {
        renderHeader(null);
        Shop.toast(error.message, true);
        return null;
      }
    },
    async register({ name, email, password }) {
      if ((await config()).mode !== "firebase")
        return request("POST", "register.php", { name, email, password });
      let fb, created;
      try {
        fb = await client();
        created = (
          await fb.createUserWithEmailAndPassword(fb.auth, email, password)
        ).user;
        await fb.updateProfile(created, { displayName: name });
        let emailSent = true;
        try {
          await fb.sendEmailVerification(created);
        } catch {
          emailSent = false;
        }
        return { success: true, emailSent };
      } catch (error) {
        throw readableError(error);
      } finally {
        if (fb && created) await fb.signOut(fb.auth);
      }
    },
    async login({ phone, email, password }) {
      loggingOut = false;
      if ((await config()).mode !== "firebase") {
        const result = await request("POST", "login.php", { phone });
        writeCache(result.data);
        return result.data;
      }
      const fb = await client();
      try {
        const credential = await fb.signInWithEmailAndPassword(
          fb.auth,
          email,
          password,
        );
        return await bridge(credential.user);
      } catch (error) {
        await fb.signOut(fb.auth);
        throw readableError(error);
      }
    },
    async logout() {
      loggingOut = true;
      if (userPromise) {
        try {
          await userPromise;
        } catch {
          /* continue clearing both sessions */
        }
      }
      let backendError, firebaseError;
      try {
        await request("POST", "logout.php");
      } catch (error) {
        backendError = error;
      }
      try {
        if ((await config()).mode === "firebase") {
          const fb = await client();
          await fb.signOut(fb.auth);
        }
      } catch (error) {
        firebaseError = error;
      }
      writeCache(null);
      if (backendError || firebaseError)
        throw new Error("Chưa đăng xuất đầy đủ. Kiểm tra mạng và thử lại.");
    },
    async googleLogin() {
      loggingOut = false;
      if ((await config()).mode !== "firebase")
        throw new Error("Đăng nhập Google chỉ có trong chế độ Firebase.");
      const fb = await client();
      const provider = new fb.GoogleAuthProvider();
      provider.setCustomParameters({ prompt: "select_account" });
      try {
        const credential = await fb.signInWithPopup(fb.auth, provider);
        return await bridge(credential.user);
      } catch (error) {
        await fb.signOut(fb.auth);
        throw readableError(error);
      }
    },
    async resetPassword(email) {
      if ((await config()).mode !== "firebase")
        throw new Error("Khôi phục mật khẩu chỉ có trong chế độ Firebase.");
      if (!(await config()).providers.email_password)
        throw new Error(
          "Website dùng đăng nhập Google. Hãy quản lý mật khẩu tại tài khoản Google của bạn.",
        );
      try {
        const fb = await client();
        await fb.sendPasswordResetEmail(fb.auth, email);
      } catch (error) {
        throw readableError(error);
      }
    },
    async sendVerification() {
      try {
        const fb = await client();
        if (!fb.auth.currentUser)
          throw new Error("Hãy đăng nhập lại để gửi email xác minh.");
        await fb.sendEmailVerification(fb.auth.currentUser);
      } catch (error) {
        throw readableError(error);
      }
    },
    async refreshVerification() {
      const fb = await client();
      if (!fb.auth.currentUser) throw new Error("Hãy đăng nhập lại.");
      await fb.reload(fb.auth.currentUser);
      return bridge(fb.auth.currentUser, true);
    },
    safeRedirect(raw) {
      if (!raw) return homeHref;
      const value = String(raw)
        .trim()
        .replace(/^pages\//, "");
      const file = value.split("?")[0];
      if (
        /[:\\]|\/\//.test(value) ||
        value.includes("..") ||
        !/^(users\/|admin\/)?[a-z0-9_-]+\.html$/i.test(file)
      )
        return homeHref;
      return pagesPrefix + value;
    },
    setStatus(el, message, type) {
      el.hidden = !message;
      el.textContent = message || "";
      el.className = "auth-status" + (type ? " auth-status--" + type : "");
    },
  };
  window.Auth = Auth;
})();
