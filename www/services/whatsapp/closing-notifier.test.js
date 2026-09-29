const {test}=require('node:test');const assert=require('node:assert/strict');
const {ClosingNotifier,closingMessages}=require('./closing-notifier');
const payload={date:'2026-09-27',closedAt:'2026-09-27 21:00:00',transactions:4,total_penjualan:100000,cafe:60000,carwash:30000,detailing:10000,cash:20000,qris:70000,card:10000,stock:{asOf:'2026-09-27T21:00:00+07:00',items:[{name:'Habis',unit:'pcs',current_quantity:0,minimum_quantity:5},{name:'Minim',unit:'gr',current_quantity:40,minimum_quantity:50},{name:'Cukup',unit:'pcs',current_quantity:20,minimum_quantity:5}]}};
function setup(){const sent=[];const row={closing_date:payload.date,group_id:'123@g.us',payload,summary_state:'pending',stock_state:'pending'};const notifier=new ClosingNotifier({getClient:()=>({}),api:async input=>{if(!input)return {jobs:[{...row}]};const state=input.part+'_state';if(input.action==='claim'){if(row[state]!=='pending')return {claimed:false};row[state]='sending';row[input.part+'_body']=input.body;return {claimed:true};}if(input.action==='sent')row[state]='sent';return {ok:true};},send:async(_,chat,body)=>sent.push(body),findSent:async()=>false,log:()=>{}});return {notifier,row,sent};}
test('two messages contain required closing figures and separate shortage sections',()=>{const m=closingMessages(payload);for(const label of ['Total pendapatan: Rp 100.000','Transaksi lunas: 4','Cafe: Rp 60.000','Carwash: Rp 30.000','Detailing: Rp 10.000','QRIS: Rp 70.000','Cash: Rp 20.000','Kartu: Rp 10.000'])assert.ok(m.summary.includes(label));assert.match(m.stock,/\*Stok Habis\*/);assert.match(m.stock,/\*Stok Minim\*/);assert.match(m.stock,/Minim: 40 gr/);assert.doesNotMatch(m.stock,/Cukup|>0–5/);});
test('sends summary before stocks once across repeated polls',async()=>{const {notifier,row,sent}=setup();await notifier.tick();await notifier.tick();assert.equal(sent.length,2);assert.match(sent[0],/Closing Harian/);assert.match(sent[1],/Stok Habis/);assert.equal(row.stock_state,'sent');});
test('uncertain first delivery never sends second or duplicates first',async()=>{const {notifier,row,sent}=setup();let tries=0;notifier.send=async()=>{tries++;throw Error('unknown')};await notifier.tick();await notifier.tick();assert.equal(tries,1);assert.equal(row.summary_state,'sending');assert.equal(row.stock_state,'pending');assert.equal(sent.length,0);});
test('recovers acknowledged first message after restart without resending',async()=>{const {notifier,row,sent}=setup();row.summary_state='sending';row.summary_body=closingMessages(payload).summary;notifier.findSent=async(_,chat,body)=>body===row.summary_body;await notifier.tick();assert.equal(sent.length,1);assert.match(sent[0],/Stok Habis/);assert.equal(row.summary_state,'sent');assert.equal(row.stock_state,'sent');});
test('offline leaves notifications pending',async()=>{const {notifier,row,sent}=setup();notifier.getClient=()=>null;await notifier.tick();assert.equal(sent.length,0);assert.equal(row.summary_state,'pending');});
test('a pending job still sends its own messages even if similar text already exists',async()=>{const {notifier,sent}=setup();notifier.findSent=async()=>true;await notifier.tick();assert.equal(sent.length,2);});
test('includes cancellations and itemized expenses only when present',()=>{
 const enriched={...payload,cancelled:[{id:7,total_amount:20000,cancel_reason:'Salah pesanan'}],expenses:[{keterangan:'Beli es',total:5000},{keterangan:'Transport',total:10000}]};
 const body=closingMessages(enriched).summary;
 for(const text of ['Transaksi Dibatalkan','Jumlah: 1 transaksi','#7: Rp 20.000 — Salah pesanan','Beli es: Rp 5.000','Total pengeluaran: Rp 15.000','Pendapatan setelah pengeluaran: Rp 85.000'])assert.ok(body.includes(text),text);
 const plain=closingMessages(payload).summary;assert.doesNotMatch(plain,/Transaksi Dibatalkan|\*Pengeluaran\*/);
});
test('restart and changing payload never resend a completed closing',async()=>{
 const {notifier,row,sent}=setup();await notifier.tick();row.payload={...payload,total_penjualan:999999,expenses:[{keterangan:'Baru',total:1000}]};
 const restarted=new ClosingNotifier({api:notifier.api,getClient:notifier.getClient,send:notifier.send,findSent:notifier.findSent,log:()=>{}});
 await restarted.tick();await restarted.tick();assert.equal(sent.length,2);
});
test('Olsera details and combined net totals precede post-sync stock report',()=>{
 const p={...payload,olsera:{total:200000,cafe:100000,carwash:60000,detailing:40000,fetchedAt:'2026-09-27 21:01:00',items:[{product_name:'Kopi',variant:'ICE',quantity:2,total_sales:100000}]},combined:{total:300000,cafe:160000,carwash:90000,detailing:50000},expenses:[{keterangan:'Es',total:5000}]};
 const m=closingMessages(p);for(const text of ['Penjualan Olsera POS','Kopi / ICE × 2: Rp 100.000','Total gabungan: Rp 300.000','Cafe: Rp 160.000','Pendapatan setelah pengeluaran: Rp 295.000','Metode Pembayaran Dejati POS'])assert.ok(m.summary.includes(text),text);
 assert.match(m.stock,/Setelah Closing/);
});
