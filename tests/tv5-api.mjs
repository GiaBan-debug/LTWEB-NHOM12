import assert from 'node:assert/strict';
import { request } from '../.local-test/node_modules/playwright/index.mjs';
const baseURL = process.env.TEST_URL || 'http://localhost:8013';
let count = 0;
function check(value,message) { assert.ok(value,message); count++; }
async function client(url = baseURL) {
  const context = await request.newContext({baseURL:url});
  const token = (await (await context.get('/backend/api/csrf.php')).json()).data.csrf_token;
  return {context,async call(file,method='GET',data,expected=200) {
    const response = await context.fetch('/backend/api/'+file,{method,headers:method === 'GET' ? {} : {'X-CSRF-Token':token},...(data === undefined ? {} : {data})});
    assert.equal(response.status(),expected,file+': '+await response.text()); count++; return response.json();
  }};
}
const a = await client(), b = await client(), admin = await client(), firebase = await client('http://localhost:8014');
try {
  for (const [c,email] of [[a,'user1@ltweb.com'],[b,'user2@ltweb.com'],[admin,'admin@ltweb.com']]) await c.call('login.php','POST',{email,password:'123456'});
  const profile = await a.call('me.php','PUT',{name:'Khách API TV5',phone:'+84 912-345-678',address:'123 Nguyễn Văn Cừ, TP.HCM'});
  check(profile.data.phone === '0912345678' && profile.data.address.includes('TP.HCM'),'Normalized profile and address');
  await a.call('me.php','PUT',{name:'Khách API TV5',phone:'wrong'},422);
  await a.call('me.php','PUT',{name:'Khách API TV5',role:'admin',email:'admin@ltweb.com'});
  check((await a.call('me.php')).data.role === 'user','Profile cannot change role');
  const p = {name:'TV5 cancel test '+Date.now(),category_id:1,price:250000,stock:4,description:'test',thumbnail:'products/cap-usb-c.jpg',status:'active'};
  const id = (await admin.call('admin.php?resource=products','POST',p)).data.id;
  await a.call('cart.php','POST',{product_id:id,quantity:2});
  const cart = (await a.call('cart.php')).data;
  check(cart.subtotal === 500000 && cart.shipping_fee === 30000 && cart.total === 530000,'Fee below threshold');
  const shipping={receiver_name:'Khách kiểm thử',phone:'0912.345.678',address:'123 Nguyễn Văn Cừ, TP.HCM',checkout_key:'tv5-'+Date.now()+'-first'};
  const order = (await a.call('orders.php','POST',{...shipping,shipping_fee:0,total_amount:1},201)).data;
  check(order.total_amount === 530000,'Cannot forge delivery fee');
  const detail=(await a.call('orders.php?id='+order.order_id)).data;
  check(detail.phone === '0912345678' && Number(detail.shipping_fee) === 30000,'Phone and fee snapshot');
  await b.call('orders.php','PUT',{id:order.order_id,action:'cancel'},404);
  await a.call('orders.php','PUT',{id:order.order_id,action:'cancel'});
  await a.call('orders.php','PUT',{id:order.order_id,action:'cancel'});
  await admin.call('admin.php?resource=orders&id='+order.order_id,'PUT',{status:'cancelled'});
  check(Number((await admin.call('admin.php?resource=products&id='+id)).data.stock) === 4,'Customer/admin repeated cancel restocks once');
  await a.call('cart.php','POST',{product_id:id,quantity:1});
  const next = (await a.call('orders.php','POST',{...shipping,phone:'+84 912 345 678',checkout_key:'tv5-'+Date.now()+'-next'},201)).data;
  await admin.call('admin.php?resource=orders&id='+next.order_id,'PUT',{status:'processing'});
  await a.call('orders.php','PUT',{id:next.order_id,action:'cancel'},409);
  await admin.call('admin.php?resource=orders&id='+next.order_id,'PUT',{status:'cancelled'});
  await admin.call('admin.php?resource=products&id='+id,'PUT',{...p,price:2000000});
  await a.call('cart.php','POST',{product_id:id,quantity:1});
  const free=(await a.call('cart.php')).data;
  check(free.shipping_fee === 0 && free.total === 2000000,'Free delivery at threshold');
  await a.call('cart.php?product_id='+id,'DELETE');
  check(Number((await a.call('orders.php?id='+order.order_id)).data.shipping_fee) === 30000,'Historical shipping fee unchanged');
  await admin.call('admin.php?resource=products&id='+id,'DELETE');
  const config=(await firebase.call('auth-config.php')).data;
  check(config.mode === 'firebase' && config.firebase.projectId === 'bta-store' && config.providers.google,'Real project Google config');
  await firebase.call('login.php','POST',{email:'admin@ltweb.com',password:'123456'},410);
  await firebase.call('register.php','POST',{name:'No fallback',email:'no@example.test',password:'123456'},410);
  await firebase.call('firebase-session.php','POST',{uid:'admin',email:'admin@ltweb.com',role:'admin'},422);
  await firebase.call('firebase-session.php','POST',{id_token:'bad'},401);
  const encode = value => Buffer.from(JSON.stringify(value)).toString('base64url');
  const forged=encode({alg:'none',kid:'test'})+'.'+encode({sub:'admin',aud:'bta-store',email:'admin@ltweb.com',role:'admin',exp:Math.floor(Date.now()/1000)+3600})+'.';
  await firebase.call('firebase-session.php','POST',{id_token:forged},401);
  await firebase.call('me.php','GET',undefined,401);
  await a.call('me.php','PUT',{name:'Nguyễn Văn A',phone:'',address:''});
  for(const path of ['/vendor/autoload.php','/var/firebase-public-keys.json','/composer.lock','/backend/config/cacert.pem']) { const r=await firebase.context.get(path); check(r.status()===404,'Private path blocked '+path); }
  console.log('PASS: '+count+' TV5 API assertions.');
} finally { await Promise.all([a,b,admin,firebase].map(c=>c.context.dispose())); }
