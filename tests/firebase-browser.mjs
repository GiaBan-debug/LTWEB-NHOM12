import assert from 'node:assert/strict';
import { chromium } from '../.local-test/node_modules/playwright/index.mjs';
const browser=await chromium.launch({headless:true,...(process.env.CHROME_PATH?{executablePath:process.env.CHROME_PATH}:{})});
const context=await browser.newContext({viewport:{width:1280,height:900}});
const page=await context.newPage();
page.setDefaultTimeout(30000);
const errors=[];
page.on('pageerror',error=>errors.push(error.message));
let checks=0;
const base=process.env.TEST_URL || 'http://localhost:8014';
try {
  await page.goto(base+'/frontend/pages/login.html');
  const google=page.locator('#btn-google');
  await google.waitFor();
  await page.waitForFunction(()=>!document.querySelector('#btn-google').disabled);
  assert.equal(await page.locator('#phone-fields').isVisible(),true); checks++;
  assert.equal(await page.locator('#forgot-password-link').count(),0); checks++;
  const popupPromise=page.waitForEvent('popup');
  await google.click();
  const popup=await popupPromise;
  await popup.waitForURL(url=>url.hostname === 'accounts.google.com');
  console.log('GOOGLE_POPUP_HOST='+new URL(popup.url()).hostname);
  checks++;
  // Do not enter a real Google account or consent on the user's behalf.
  await popup.close();
  await page.locator('#auth-status').filter({hasText:'đóng cửa sổ'}).waitFor(); checks++;
  await page.goto(base+'/frontend/pages/register.html');
  await page.waitForFunction(()=>!document.querySelector('#btn-google').disabled);
  assert.equal(await page.locator('#phone-fields').isVisible(),true); checks++;
  assert.equal((await page.locator('#btn-google').textContent()).trim(),'Tiếp tục bằng Google'); checks++;
  assert.deepEqual(errors,[]); checks++;
  console.log('PASS: '+checks+' Firebase browser checks (real SDK/popup; no Google account signed in).');
} finally { await browser.close(); }
