import assert from 'node:assert/strict';
import {chromium} from '../.local-test/node_modules/playwright/index.mjs';
const browser = await chromium.launch({headless:true,...(process.env.CHROME_PATH ? {executablePath:process.env.CHROME_PATH} : {})});
const context = await browser.newContext({viewport:{width:390,height:844}}), page = await context.newPage();
const base = process.env.TEST_URL || 'http://localhost:8000';
const origin = 'https://phone-test.trycloudflare.com';
let checks=0, bridges=0; const errors=[];
const check=(value,message)=>{assert.ok(value,message);checks++;};
page.on('pageerror',error=>errors.push(error.message));
// Only the external SMS/SDK boundary is simulated; real Auth and page code run unchanged.
await context.route(origin+'/**',async route=>{
  const response=await route.fetch({url:route.request().url().replace(origin,base)});
  await route.fulfill({response});
});
await context.route('**/backend/api/auth-config.php',route=>route.fulfill({json:{success:true,data:{mode:'firebase',providers:{google:true,phone:true,email_password:false},firebase:{}}}}));
await context.route('**/backend/api/me.php',route=>route.fulfill({status:401,json:{success:false,message:'Login required'}}));
await context.route('**/backend/api/firebase-session.php',route=>{
  assert.equal(route.request().postDataJSON().id_token,'phone-test-token');bridges++;
  return route.fulfill({json:{success:true,data:{id:123,name:'Khách OTP',email:null,role:'user',phone:'0912345678',phone_verified:true,auth_provider:'firebase'}}});
});
await context.route('**/frontend/assets/js/firebase-auth.js',route=>route.fulfill({contentType:'application/javascript',body:`
const auth={currentUser:null};
export async function firebaseClient(){ return {
 auth, signOut:async()=>{auth.currentUser=null;},
 RecaptchaVerifier:class {constructor(a,id){if(a!==auth||!document.getElementById(id))throw Error('Wrong reCAPTCHA setup');window.__captchaCreated=(window.__captchaCreated||0)+1;} clear(){window.__captchaCleared=(window.__captchaCleared||0)+1;}},
 signInWithPhoneNumber:async(a,phone,verifier)=>{
  window.__sentPhone=phone; window.__smsCalls=(window.__smsCalls||0)+1;
  await new Promise(resolve=>setTimeout(resolve,100));
  if(phone==='+84999999999')throw Object.assign(Error('quota'),{code:'auth/quota-exceeded'});
  return {confirm:async code=>{if(code!=='123456')throw Object.assign(Error('bad code'),{code:'auth/invalid-verification-code'});return {user:{getIdToken:async()=> 'phone-test-token'}};}};
 }
};}` }));
try {
  await page.goto(origin+'/frontend/pages/register.html?redirect=users/profile.html');
  await page.locator('#phone').fill('wrong');
  check(await page.locator('#btn-submit').isDisabled(),'Invalid phone disabled');
  await page.locator('#phone').fill('+84 912 345 678');
  await page.waitForFunction(()=>!document.querySelector('#btn-submit').disabled);
  check(await page.locator('input[type=password]').count()===0,'No password fields');
  await page.locator('#btn-submit').click();
  await page.locator('#otp-step').waitFor();
  check(await page.evaluate(()=>window.__sentPhone)==='+84912345678','Firebase receives E.164 phone');
  check(await page.evaluate(()=>window.__smsCalls)===1,'Single SMS request');
  check(await page.evaluate(()=>window.__captchaCreated===1&&window.__captchaCleared===1),'reCAPTCHA cleared after send');
  check(await page.locator('#otp-resend').isDisabled(),'Resend cooldown');
  check(await page.locator('#otp-confirm').isDisabled(),'OTP requires six digits');
  await page.locator('#otp-code').fill('111111');
  await page.locator('#otp-confirm').click();
  await page.locator('#auth-status').filter({hasText:'không đúng'}).waitFor();
  check(bridges===0,'Wrong OTP never creates PHP session');
  await page.locator('#otp-code').fill('123456');
  await page.locator('#otp-confirm').click();
  await page.waitForURL('**/users/profile.html');
  check(bridges===1,'Confirmed Firebase token bridged to PHP');
  await page.goto(origin+'/frontend/pages/login.html');
  await page.locator('#phone').fill('0999999999');
  await page.waitForFunction(()=>!document.querySelector('#btn-submit').disabled);
  await page.locator('#btn-submit').click();
  await page.locator('#auth-status').filter({hasText:'hạn mức SMS'}).waitFor();
  check(await page.locator('#otp-step').isHidden(),'No OTP screen after send failure');
  check(await page.locator('#btn-submit').isEnabled(),'Can retry after send failure');
  await page.locator('#phone').fill('0912345678');
  await page.locator('#btn-submit').click();
  await page.locator('#otp-step').waitFor();
  await page.locator('#otp-back').click();
  check(await page.locator('#phone-fields').isVisible(),'Can return to phone input');
  check(await page.locator('#btn-submit').isDisabled(),'Returning does not bypass resend cooldown');
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile width');
  await page.goto(base+'/frontend/pages/login.html');
  await page.locator('#phone').fill('0912345678');
  await page.waitForFunction(()=>!document.querySelector('#btn-submit').disabled);
  await page.locator('#btn-submit').click();
  await page.locator('#auth-status').filter({hasText:'HTTPS'}).waitFor();
  check(await page.evaluate(()=>!window.__smsCalls),'Localhost blocked before SMS request');
  check(errors.length===0,'No browser errors: '+errors.join('; '));
  console.log('PASS: '+checks+' phone browser assertions (SMS simulated; no real SMS sent).');
} finally {await browser.close();}
