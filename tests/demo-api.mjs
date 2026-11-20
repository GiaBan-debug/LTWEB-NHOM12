import assert from 'node:assert/strict';
import { request } from '../.local-test/node_modules/playwright/index.mjs';

const baseURL = process.env.TEST_URL || 'http://localhost:8013';
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; }
async function client() {
  const context = await request.newContext({baseURL});
  const token = (await (await context.get('/backend/api/csrf.php')).json()).data.csrf_token;
  return {
    context,
    async call(file, method='GET', body, expected=200) {
      const response = await context.fetch('/backend/api/' + file, {method, headers:method === 'GET' ? {} : {'X-CSRF-Token':token}, ...(body === undefined ? {} : {data:body})});
      assert.equal(response.status(), expected, file + ': ' + await response.text());
      checks++;
      return response.json();
    }
  };
}
const guest = await client(), a = await client(), b = await client(), admin = await client();
try {
  await guest.call('health.php');
  await guest.call('me.php','GET',undefined,401);
  await guest.call('cart.php','GET',undefined,401);
  const noCsrf = await guest.context.post('/backend/api/login.php', {data:{phone:'0916222222'}});
  check(noCsrf.status() === 403, 'CSRF required');
  await guest.call('login.php','POST',{phone:['invalid']},422);
  await guest.call('register.php','POST',[],422);
  await guest.call('login.php','POST',{phone:'0916333333'},401);
  const email = 'test-' + Date.now() + '@example.test';
  await guest.call('register.php','POST',{name:'Khách kiểm thử',email,password:'123456'},201);
  await guest.call('register.php','POST',{name:'Khách kiểm thử',email,password:'123456'},409);
  await a.call('login.php','POST',{phone:'0916222222'});
  await b.call('login.php','POST',{phone:'0916111111'});
  await admin.call('login.php','POST',{phone:'0916111111'});
  await a.call('admin.php','GET',undefined,403);
  const profile = await a.call('me.php','PUT',{name:'Khách thử nghiệm',phone:'0912345678',address:'123 Lê Lợi, TP.HCM'});
  check(profile.data.name === 'Khách thử nghiệm' && profile.data.phone === '0912345678' && !profile.data.password, 'Profile response safe');
  await a.call('me.php','PUT',{name:'Nguyễn Văn A',phone:'0912345678',address:'123 Lê Lợi, TP.HCM'});

  // Legacy mode must not accept the Firebase bridge or client-supplied identities.
  await guest.call('firebase-auth.php','POST',{firebase_uid:'firebase_demo_user1'},409);
  const cats = await guest.call('categories.php');
  check(cats.data.length >= 4, 'Seed categories');
  const products = await guest.call('products.php?sort_by=price&sort_dir=ASC&limit=3');
  check(products.data.items.length === 3 && Number(products.data.items[0].price) <= Number(products.data.items[1].price), 'Server sorting and pagination');
  const filtered = await guest.call('products.php?search=iPhone&min_price=100&category_id=1');
  check(filtered.data.items.length && filtered.data.items.every(p => p.name.includes('iPhone') && Number(p.category_id) === 1), 'Search and filters');
  await guest.call('products.php?min_price=100&max_price=1','GET',undefined,422);
  await guest.call('products.php?id=999999','GET',undefined,404);
  const seedProduct = (await guest.call('products.php?id=1')).data;
  check(seedProduct.thumbnail === 'products/iphone-15-pro-max.jpg', 'Seed thumbnail');
  const product = {name:'API integration ' + Date.now(),category_id:1,price:150000,stock:2,description:'Test product',thumbnail:'products/iphone-15-pro-max.jpg',status:'active'};
  const created = await admin.call('admin.php?resource=products','POST',product);
  const id = created.data.id;
  await admin.call('admin.php?resource=products&id=' + id,'PUT',{...product,thumbnail:'../../.env'},422);
  await a.call('cart.php','POST',{product_id:id,quantity:3},409);
  await a.call('cart.php','POST',{product_id:id,quantity:2});
  await b.call('cart.php','POST',{product_id:id,quantity:2});
  let cart = await a.call('cart.php');
  check(cart.data.subtotal === 300000 && cart.data.shipping_fee === 30000 && cart.data.total === 330000 && cart.data.total_items === 2, 'Server cart total including shipping');
  await a.call('cart.php','PUT',{product_id:id,quantity:1});
  await a.call('cart.php','PUT',{product_id:id,quantity:2});
  const shipping = {receiver_name:'Người kiểm thử',phone:'0912 345 678',address:'123 Đường kiểm thử, TP.HCM',note:'Test',checkout_key:'test-key-a-' + Date.now()};
  const order = await a.call('orders.php','POST',{...shipping,total_amount:1},201);
  check(order.data.total_amount === 330000, 'Client cannot set order price or shipping');
  const orderId = order.data.order_id;
  check((await a.call('cart.php')).data.is_empty, 'Cart cleared after checkout');
  const retry = await a.call('orders.php','POST',shipping,201);
  check(retry.data.order_id === orderId, 'Checkout idempotent');
  await b.call('orders.php?id=' + orderId,'GET',undefined,404);
  const shippingB = {...shipping,checkout_key:'test-key-b-' + Date.now()};
  await b.call('orders.php','POST',shippingB,409);
  check((await b.call('cart.php')).data.total_items === 2, 'Failed checkout preserves cart');
  await admin.call('admin.php?resource=orders&id=' + orderId,'PUT',{status:'completed'},409);
  await admin.call('admin.php?resource=orders&id=' + orderId,'PUT',{status:'cancelled'});
  await admin.call('admin.php?resource=orders&id=' + orderId,'PUT',{status:'cancelled'});
  check(Number((await admin.call('admin.php?resource=products&id=' + id)).data.stock) === 2, 'Cancel restocks once');
  const orderB = await b.call('orders.php','POST',shippingB,201);
  for (const status of ['processing','shipped','completed']) await admin.call('admin.php?resource=orders&id=' + orderB.data.order_id,'PUT',{status});
  await admin.call('admin.php?resource=orders&id=' + orderB.data.order_id,'PUT',{status:'cancelled'},409);
  const snapshotBefore = (await b.call('orders.php?id=' + orderB.data.order_id)).data.items[0];
  await admin.call('admin.php?resource=products&id=' + id,'PUT',{...product,name:'Changed later',price:900000,stock:2});
  const snapshot = (await b.call('orders.php?id=' + orderB.data.order_id)).data.items[0];
  check(snapshot.name === snapshotBefore.name && Number(snapshot.price) === 150000, 'Order snapshot survives product edits');
  await a.call('cart.php','POST',{product_id:id,quantity:1});
  await admin.call('admin.php?resource=products&id=' + id,'DELETE');
  check(!(await a.call('cart.php')).data.can_checkout, 'Inactive product blocks checkout');
  await a.call('orders.php','POST',{...shipping,checkout_key:'test-key-c-' + Date.now()},409);
  await a.call('cart.php?product_id=' + id,'DELETE');
  await guest.call('products.php?id=' + id,'GET',undefined,404);
  await admin.call('admin.php?resource=dashboard');
  await a.call('logout.php','POST');
  await a.call('me.php','GET',undefined,401);
  for (const path of ['/.env','/database/seed.sql','/backend/config/database.php','/.local-test/mysql-data/my.ini']) {
    const r = await guest.context.get(path); check(r.status() === 404, 'Private path blocked: ' + path);
  }
  console.log('PASS: ' + checks + ' API assertions (isolated test database).');
} finally {
  await Promise.all([guest,a,b,admin].map(c => c.context.dispose()));
}
