const {test}=require('node:test');const assert=require('node:assert/strict');
const {StockDialog,parseEdit,parseAmount}=require('./stock-dialog');
function setup(){let now=Date.now();const calls=[];const items=[{id:1,name:'Ayam Bakar',current_quantity:5,unit:'pcs'},{id:2,name:'Ayam Kampung',current_quantity:3,unit:'pcs'}];const d=new StockDialog({now:()=>now,getReport:async()=>({items}),save:async()=>{},api:async input=>{calls.push(input);return {status:input.action==='confirm'?'applied':input.action==='cancel'?'cancelled':'pending',token:input.token,name:'Ayam Bakar',unit:'pcs',old:5,new:7,delta:2}}});const ctx={chat:'123@lid',sender:'123@lid',messageId:'m1'};return {d,calls,ctx,advance:()=>{now+=600001}};}
test('parses set/add/subtract with missing quantity and Indonesian numbers',()=>{
 assert.equal(parseEdit('Update Stok Ayam Bakar').mode,'set');
 assert.equal(parseEdit('tambah stok ayam bakar 2').amount,'2');
 assert.equal(parseEdit('kurang stok ayam bakar').mode,'subtract');
 assert.equal(parseEdit('update stok hari ini'),null);
 assert.equal(parseAmount('5.000 gr').amount,'5000');assert.equal(parseAmount('0,5').amount,'0.5');
 assert.equal(parseAmount('tambah -2'),null);assert.equal(parseAmount('iya tapi 3'),null);
});
test('report -> item -> amount -> confirmation -> mutation only after same user says yes',async()=>{
 const {d,calls,ctx}=setup();
 assert.equal(await d.handle(ctx,'update stok hari ini',{kind:'stock'}),null);
 assert.match(await d.handle({...ctx,messageId:'m2'},'ayam kampung',null),/saat ini: 3 pcs/);
 assert.equal(calls.length,0);
 assert.match(await d.handle({...ctx,messageId:'m3'},'tambah 2',null),/Stok awal saat ini: 5/);
 assert.equal(calls.at(-1).action,'prepare');
 assert.equal(await d.handle({...ctx,sender:'999@lid'},'YA',null),null);
 assert.equal(calls.filter(c=>c.action==='confirm').length,0);
 assert.match(await d.handle({...ctx,messageId:'m4'},'Betul',null),/berhasil diperbarui/);
 assert.equal(calls.filter(c=>c.action==='confirm').length,1);
 assert.equal(await d.handle({...ctx,messageId:'m5'},'YA',null),null);
});
test('ambiguous names require selection and preserve relative amount',async()=>{
 const {d,calls,ctx}=setup();
 assert.match(await d.handle(ctx,'tambah stok ayam 2',null),/beberapa item/);assert.equal(calls.length,0);
 await d.handle({...ctx,messageId:'m2'},'Ayam Bakar',null);
 assert.equal(calls.at(-1).mode,'add');assert.equal(calls.at(-1).amount,'2');assert.equal(calls.at(-1).itemId,1);
});
test('cancel, expiry, negative confirmation and group isolation',async()=>{
 const {d,calls,ctx,advance}=setup();await d.handle(ctx,'ubah stok ayam bakar jadi 7',null);
 await d.handle({...ctx,messageId:'m2'},'ya tapi jangan simpan',null);
 assert.equal(calls.filter(c=>c.action==='confirm').length,0);
 assert.equal(await d.handle({...ctx,chat:'987@g.us'},'YA',null),null);
 assert.match(await d.handle(ctx,'BATAL',null),/dibatalkan/);
 await d.handle({...ctx,messageId:'m3'},'kurang stok ayam bakar 2',null);advance();
 assert.match(await d.handle({...ctx,messageId:'m4'},'YA',null),/kedaluwarsa/);
 assert.equal(calls.filter(c=>c.action==='confirm').length,0);
});
test('stock changed response requires another explicit confirmation and survives restart',async()=>{
 const {d,ctx}=setup();await d.handle(ctx,'ubah stok ayam bakar jadi 7',null);
 const restored=setup().d;restored.restore(JSON.parse(JSON.stringify(d.sessions)));
 let count=0;restored.api=async()=>({status:++count===1?'changed':'applied',name:'Ayam Bakar',old:4,new:7,delta:3,unit:'pcs'});
 assert.match(await restored.handle({...ctx,messageId:'m2'},'YA',null),/konfirmasi ulang/);
 assert.ok(restored.active(ctx));
 assert.match(await restored.handle({...ctx,messageId:'m3'},'BENAR',null),/berhasil diperbarui/);
});
test('cancel after an uncertain successful commit reports the saved result honestly',async()=>{
 const {d,ctx}=setup();await d.handle(ctx,'ubah stok ayam bakar 7',null);
 d.api=async()=>({status:'applied',name:'Ayam Bakar',old:5,new:7,unit:'pcs'});
 assert.match(await d.handle(ctx,'BATAL',null),/sudah tersimpan/);
});
