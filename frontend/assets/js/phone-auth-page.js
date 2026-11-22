/* Firebase SMS/reCAPTCHA flow; legacy fixture login remains local-only. */
(() => {
  'use strict';
  const form = document.querySelector('#login-form, #register-form');
  if (!form) return;
  const registering = form.id === 'register-form';
  const status = document.querySelector('#auth-status'), google = document.querySelector('#btn-google');
  const submit = document.querySelector('#btn-submit'), fields = document.querySelector('#phone-fields');
  const otp = document.querySelector('#otp-step'), code = document.querySelector('#otp-code');
  const confirm = document.querySelector('#otp-confirm'), resend = document.querySelector('#otp-resend'), back = document.querySelector('#otp-back');
  const phone = form.elements.namedItem('phone');
  const redirect = new URLSearchParams(location.search).get('redirect');
  let busy = false, initialized = false, settings, confirmation, sentPhone = '', nextSendAt = 0;
  const normalizedPhone = () => Shop.normalizePhone(phone.value);
  function refresh() {
    const remaining = Math.max(0, Math.ceil((nextSendAt - Date.now()) / 1000));
    const valid = !!normalizedPhone();
    phone.setAttribute('aria-invalid', String(!valid && !!phone.value));
    phone.closest('.auth-field').classList.toggle('is-invalid', !valid && !!phone.value);
    form.querySelector('[data-error-for="phone"]').textContent = !valid && phone.value ? 'Nhập số điện thoại Việt Nam 10 chữ số hoặc đầu +84.' : '';
    submit.disabled = !initialized || busy || !valid || remaining > 0 || (settings?.mode === 'firebase' && !settings.providers.phone);
    google.disabled = !initialized || busy;
    phone.disabled = busy;
    confirm.disabled = busy || !confirmation || !/^\d{6}$/.test(code.value.trim());
    code.disabled = busy;
    back.disabled = busy;
    resend.disabled = busy || remaining > 0 || !sentPhone;
    resend.textContent = remaining > 0 ? `Gửi lại sau ${remaining}s` : 'Gửi lại OTP';
  }
  async function send() {
    busy = true; refresh();
    Auth.setStatus(status, 'Đang xác minh và yêu cầu mã OTP…');
    try {
      const number = otp.hidden ? normalizedPhone() : sentPhone;
      const result = await Auth.startPhoneLogin(number, 'recaptcha-container');
      confirmation = result; sentPhone = number;
      nextSendAt = Date.now() + 60000;
      document.querySelector('#otp-phone').textContent = number;
      fields.hidden = true; otp.hidden = false; code.value = '';
      Auth.setStatus(status, 'Đã yêu cầu OTP. Nhập mã nhận được; nếu dùng số kiểm thử Firebase, nhập mã đã cấu hình.');
    } catch (error) { Auth.setStatus(status, error.message, 'error'); }
    finally { busy = false; refresh(); if (!otp.hidden) code.focus(); }
  }
  async function verify() {
    if (busy || !confirmation || !/^\d{6}$/.test(code.value.trim())) return;
    busy = true; refresh(); Auth.setStatus(status, 'Đang xác nhận OTP…');
    try {
      await confirmation.confirm(code.value.trim());
      location.href = Auth.safeRedirect(redirect);
    } catch (error) { Auth.setStatus(status, error.message, 'error'); }
    finally { busy = false; refresh(); }
  }
  form.addEventListener('input', refresh);
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!otp.hidden) return verify();
    if (!initialized || busy || !normalizedPhone() || Date.now() < nextSendAt) return;
    if (settings.mode === 'legacy') {
      if (registering) {
        // Legacy registration preview never sends SMS or creates an identity.
        sentPhone = normalizedPhone(); document.querySelector('#otp-phone').textContent = sentPhone;
        fields.hidden = true; otp.hidden = false;
        Auth.setStatus(status, 'Chế độ thử không gửi OTP. Dùng đăng nhập bằng tài khoản thử hoặc chuyển sang Firebase.'); refresh(); return;
      }
      busy = true; refresh();
      try { await Auth.login({phone:normalizedPhone()}); location.href = Auth.safeRedirect(redirect); }
      catch (error) { Auth.setStatus(status, error.message, 'error'); }
      finally { busy = false; refresh(); }
      return;
    }
    await send();
  });
  confirm.addEventListener('click', verify);
  resend.addEventListener('click', () => { if (!busy && Date.now() >= nextSendAt && settings.mode === 'firebase') send(); });
  back.addEventListener('click', () => {
    if (busy) return;
    confirmation = null; code.value = ''; otp.hidden = true; fields.hidden = false;
    Auth.setStatus(status, ''); refresh(); phone.focus();
  });
  google.addEventListener('click', async () => {
    if (busy) return;
    busy = true; refresh();
    try { await Auth.googleLogin(); location.href = Auth.safeRedirect(redirect); }
    catch (error) { Auth.setStatus(status, error.message, 'error'); }
    finally { busy = false; refresh(); }
  });
  const timer = setInterval(refresh, 1000);
  window.addEventListener('pagehide', () => clearInterval(timer), {once:true});
  refresh();
  (async () => {
    try {
      settings = await Auth.config();
      await Auth.setupAuthPage(google, status);
      google.hidden = settings.mode !== 'firebase';
      resend.hidden = settings.mode !== 'firebase';
      if (settings.mode === 'legacy' && !registering) document.querySelector('.auth-lead').textContent = 'Chế độ thử: 0916111111 (admin), 0916222222 (user). Không cần mật khẩu hoặc OTP.';
      const user = await Auth.currentUser();
      if (user) { location.replace(Auth.safeRedirect(redirect)); return; }
      initialized = true; refresh();
    } catch (error) { Auth.setStatus(status, error.message, 'error'); }
  })();
})();
