import assert from 'node:assert/strict';
import { chromium } from '../.local-test/node_modules/playwright/index.mjs';
const browser = await chromium.launch({headless:true, ...(process.env.CHROME_PATH ? {executablePath:process.env.CHROME_PATH} : {})});
const page = await browser.newPage();
const base = process.env.TEST_URL || 'http://localhost:8000';
const errors = [], mutations = [];
page.on('pageerror', error => errors.push(error.message));
page.on('request', request => {
  if (request.method() === 'POST') mutations.push(request.url());
});
// Exercise the unfinished phone preview even when the Firebase SDK is unavailable.
await page.route('https://www.gstatic.com/**', route => route.abort());
await page.route('**/backend/api/auth-config.php', route => route.fulfill({json:{success:true,data:{mode:'firebase',providers:{google:true,email_password:false},firebase:{}}}}));
await page.route('**/backend/api/me.php', route => route.fulfill({json:{success:true,data:null}}));
try {
  await page.goto(base + '/frontend/pages/register.html');
  await page.locator('#auth-status').filter({hasText:'Firebase'}).waitFor();
  assert.equal(await page.locator('#phone-fields').isVisible(), true);
  assert.equal(await page.locator('#btn-google').isVisible(), true);
  assert.equal(await page.locator('#btn-submit').isDisabled(), true);
  await page.locator('#phone').fill('0912 345 678');
  assert.equal(await page.locator('input[type="password"]').count(), 0);
  assert.equal(await page.locator('#btn-submit').isEnabled(), true);
  await page.locator('#phone').fill('wrong');
  assert.equal(await page.locator('#btn-submit').isDisabled(), true);
  await page.locator('#phone').fill('+84 912 345 678');
  await page.locator('#btn-submit').click();
  assert.equal(await page.locator('#otp-step').isVisible(), true);
  assert.equal(await page.locator('#otp-phone').textContent(), '0912345678');
  assert.equal(await page.locator('#otp-step .btn-auth').isDisabled(), true);
  await page.locator('#otp-back').click();
  assert.equal(await page.locator('#btn-submit').isEnabled(), true);
  await page.goto(base + '/frontend/pages/login.html');
  await page.locator('#auth-status').filter({hasText:'Firebase'}).waitFor();
  await page.locator('#phone').fill('0912345678');
  assert.equal(await page.locator('input[type="password"]').count(), 0);
  await page.locator('#btn-submit').click();
  await page.locator('#auth-status').filter({hasText:'chưa khả dụng'}).waitFor();
  assert.deepEqual(mutations, [], 'Preview must not submit credentials or send SMS');
  assert.deepEqual(errors, []);
  console.log('PASS: phone form validation, OTP preview/back, unavailable login; no credentials sent.');
} finally { await browser.close(); }
