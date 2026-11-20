import assert from 'node:assert/strict';
import {request} from '../.local-test/node_modules/playwright/index.mjs';
const baseURL = process.env.TEST_URL || 'http://localhost:8013';
let checks = 0;
const check = (value, message) => { assert.ok(value, message); checks++; };
async function client(phone) {
  const context = await request.newContext({baseURL});
  const token = (await (await context.get('/backend/api/csrf.php')).json()).data.csrf_token;
  const call = async (path, method='GET', data, status=200) => {
    const response = await context.fetch('/backend/api/'+path,{method,headers:{'X-CSRF-Token':token},...(data === undefined ? {} : {data})});
    assert.equal(response.status(),status, path+': '+await response.text()); checks++;
    return (await response.json()).data;
  };
  if (phone) await call('login.php','POST',{phone});
  return {context,call};
}
const guest = await client(), user = await client('0916222222'), admin = await client('0916111111');
try {
  const saved = await user.call('me.php','PUT',{name:'Nguyễn Khách Hàng',phone:'+84 912 345 678',address:'123 Nguyễn Trãi, Quận 1, TP.HCM'});
  check(saved.phone === '0912345678','Canonical saved phone');
  const partial = await user.call('me.php','PUT',{name:'Nguyễn Khách Hàng',role:'admin',email:'forged@example.test'});
  check(partial.phone === saved.phone && partial.address === saved.address && partial.role === 'user' && partial.email !== 'forged@example.test','Partial update preserves delivery; ignores role/email');
  for (const data of [{phone:[]},{phone:null},{address:'x'},{name:'x'},{address:'x'.repeat(256)},{phone:'123'}]) await user.call('me.php','PUT',data,422);
  const product = await admin.call('admin.php?resource=products','POST',{name:'Review verification '+Date.now(),category_id:1,price:100000,stock:20,status:'active',description:'Reviews test',thumbnail:''});
  const id = Number(product.id);
  const payload = {product_id:id,rating:5,feedback:'Sản phẩm tốt <img src=x onerror=alert(1)>'};
  await guest.call('reviews.php','POST',payload,401);
  await user.call('reviews.php','POST',payload,403);
  const empty = await guest.call('reviews.php?product_id='+id);
  check(!empty.can_review && empty.my_review === null && empty.rating_count === 0,'Guest and empty reviews');
  await user.call('cart.php','POST',{product_id:id,quantity:1});
  const order = await user.call('orders.php','POST',{use_profile:true,receiver_name:'Forged name',phone:'000',address:'Spoof',checkout_key:'review-profile-'+Date.now()},201);
  const details = await user.call('orders.php?id='+order.order_id);
  check(details.receiver_name === saved.name && details.phone === saved.phone && details.address === saved.address,'Checkout uses server profile, ignores posted shipping overrides');
  await user.call('reviews.php','POST',payload,403);
  for (const status of ['processing','shipped']) {
    await admin.call('admin.php?resource=orders&id='+order.order_id,'PUT',{status});
    await user.call('reviews.php','POST',payload,403);
  }
  await admin.call('admin.php?resource=orders&id='+order.order_id,'PUT',{status:'completed'});
  await admin.call('reviews.php','POST',{...payload,user_id:saved.id,order_id:order.order_id},403);
  for (const rating of [0,6,1.5,true,[],null]) await user.call('reviews.php','POST',{...payload,rating},422);
  await user.call('reviews.php','POST',{...payload,feedback:' '},422);
  await user.call('reviews.php','POST',{...payload,feedback:'x'.repeat(2001)},422);
  const created = await user.call('reviews.php','POST',payload,201);
  check(created.rating_average === 5 && created.rating_count === 1,'Initial rating');
  await user.call('reviews.php','POST',payload,409);
  const updated = await user.call('reviews.php','PUT',{...payload,rating:4});
  check(updated.rating_average === 4 && updated.rating_count === 1,'Edit retains one review');
  const other = await admin.call('reviews.php?product_id='+id);
  check(other.my_review === null && !other.can_review && !('user_id' in other.items[0]) && !('email' in other.items[0]),'Private identity and edit ownership');
  await admin.call('cart.php','POST',{product_id:id,quantity:1});
  const second = await admin.call('orders.php','POST',{receiver_name:'Second Buyer',phone:'0916111111',address:'789 Lê Lợi, TP.HCM',checkout_key:'review-second-'+Date.now()},201);
  for (const status of ['processing','shipped','completed']) await admin.call('admin.php?resource=orders&id='+second.order_id,'PUT',{status});
  const aggregate = await admin.call('reviews.php','POST',{...payload,rating:2,feedback:'Khách thứ hai'},201);
  check(aggregate.rating_count === 2 && aggregate.rating_average === 3,'Aggregate of two verified buyers');
  const detail = await guest.call('products.php?id='+id);
  check(Number(detail.rating_count) === 2 && Number(detail.rating_average) === 3,'Product detail aggregate');
  const list = await guest.call('products.php?search='+encodeURIComponent(detail.name));
  check(Number(list.items[0].rating_average) === 3,'List aggregate');
  const noCsrf = await user.context.post('/backend/api/reviews.php',{data:payload});
  check(noCsrf.status() === 403,'Reviews require CSRF');
  console.log(`PASS: ${checks} reviews/profile API assertions. Product ${id}; order ${order.order_id}.`);
} finally { await Promise.all([guest,user,admin].map(c=>c.context.dispose())); }
