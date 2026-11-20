/* Firebase phone forms await SMS integration; legacy login uses explicit test identities. */
(() => {
  'use strict';
  const form = document.querySelector('#login-form, #register-form');
  if (!form) return;
  const registering = form.id === 'register-form';
  const status = document.querySelector('#auth-status');
  const google = document.querySelector('#btn-google');
  const submit = document.querySelector('#btn-submit');
  const fields = document.querySelector('#phone-fields');
  const otp = document.querySelector('#otp-step');
  const phone = form.elements.namedItem('phone');
  const redirect = new URLSearchParams(location.search).get('redirect');
  const normalizedPhone = () => {
    let value = phone.value.trim().replace(/[\s.\-()]/g, '');
    if (/^\+84\d{9}$/.test(value)) value = '0' + value.slice(3);
    return /^0\d{9}$/.test(value) ? value : null;
  };
  function fieldError(name, message) {
    const input = form.elements.namedItem(name);
    input.setAttribute('aria-invalid', String(Boolean(message)));
    input.closest('.auth-field').classList.toggle('is-invalid', Boolean(message));
    form.querySelector(`[data-error-for="${name}"]`).textContent = message;
  }
  function validate(showAll = false) {
    const errors = {
      phone: normalizedPhone() ? '' : 'Nhập số điện thoại 10 chữ số hoặc đầu +84.',
    };
    for (const [name, message] of Object.entries(errors)) {
      const input = form.elements.namedItem(name);
      fieldError(name, showAll || input.value ? message : '');
    }
    const valid = Object.values(errors).every(message => !message);
    submit.disabled = !valid;
    return valid;
  }
  form.addEventListener('input', () => validate());
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!validate(true)) return;
    if (!registering) {
      submit.disabled = true;
      try {
        if ((await Auth.config()).mode !== 'legacy') {
          Auth.setStatus(status, 'Đăng nhập bằng số điện thoại chưa khả dụng. Bạn có thể tiếp tục bằng Google.', 'error');
          return;
        }
        await Auth.login({phone: normalizedPhone()});
        location.href = Auth.safeRedirect(redirect);
      } catch (error) {
        Auth.setStatus(status, error.message, 'error');
      } finally { validate(); }
      return;
    }
    document.querySelector('#otp-phone').textContent = normalizedPhone();
    fields.hidden = true;
    fields.disabled = true;
    otp.hidden = false;
    document.querySelector('#otp-code').focus();
    Auth.setStatus(status, 'Chưa gửi OTP. Xác thực SMS chưa được kết nối; tài khoản chưa được tạo.', '');
  });
  document.querySelector('#otp-back')?.addEventListener('click', () => {
    otp.hidden = true;
    document.querySelector('#otp-code').value = '';
    fields.hidden = false;
    fields.disabled = false;
    Auth.setStatus(status, '');
    validate();
    phone.focus();
  });
  google.addEventListener('click', async () => {
    google.disabled = true;
    try {
      await Auth.googleLogin();
      location.href = Auth.safeRedirect(redirect);
    } catch (error) {
      Auth.setStatus(status, error.message, 'error');
    } finally { google.disabled = false; }
  });
  validate();
  // Initialize Google independently: a network failure must not disable the preview.
  Auth.setupAuthPage(google, status);
  Auth.config().then(config => {
    if (config.mode === 'legacy' && !registering) {
      document.querySelector('.auth-lead').textContent = 'Chế độ thử: 0916111111 (admin), 0916222222 (user). Không cần mật khẩu hoặc OTP.';
    }
  }).catch(() => {});
  Auth.currentUser().then(user => {
    if (user) location.replace(Auth.safeRedirect(redirect));
  }).catch(error => Auth.setStatus(status, error.message, 'error'));
})();
