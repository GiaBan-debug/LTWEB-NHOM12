import assert from 'node:assert/strict';
import {request} from '../.local-test/node_modules/playwright/index.mjs';
const baseURL=process.env.TEST_URL||'http://localhost:8013';
let checks=0;
async function client(phone){
 const context=await request.newContext({baseURL});
 if(phone){const csrf=(await(await context.get('/backend/api/csrf.php')).json()).data.csrf_token;assert.equal((await context.post('/backend/api/login.php',{data:{phone},headers:{'X-CSRF-Token':csrf}})).status(),200);}
 return context;
}
const guest=await client(),user=await client('0916222222'),admin=await client('0916111111');
const path='/backend/api/admin.php?resource=statistics';
async function get(context,query,status=200){const r=await context.get(path+query);assert.equal(r.status(),status,await r.text());checks++;return(await r.json()).data;}
try{
 await get(guest,'&date_from=2027-01-15&date_to=2027-01-16',401);
 await get(user,'&date_from=2027-01-15&date_to=2027-01-16',403);
 for(const q of ['','&date_from=2027-02-30&date_to=2027-03-01','&date_from=2027-01-17&date_to=2027-01-15','&date_from[]=x&date_to=2027-01-16','&date_from=garbage&date_to=2027-01-16'])await get(admin,q,422);
 const report=await get(admin,'&date_from=2027-01-15&date_to=2027-01-16');
 assert.deepEqual(report,{orders:4,completed:2,cancelled:1,revenue:400000,date_from:'2027-01-15',date_to:'2027-01-16'});checks++;
 const day=await get(admin,'&date_from=2027-01-16&date_to=2027-01-16');assert.equal(day.revenue,250000);assert.equal(day.orders,2);checks++;
 const empty=await get(admin,'&date_from=1901-01-01&date_to=1901-01-01');assert.equal(empty.orders,0);assert.equal(empty.revenue,0);checks++;
 console.log('PASS: '+checks+' statistics API assertions (date boundaries, totals, permissions, invalid dates).');
}finally{await Promise.all([guest,user,admin].map(c=>c.dispose()));}
