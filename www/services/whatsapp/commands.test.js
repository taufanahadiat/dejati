const {test}=require('node:test');const assert=require('node:assert/strict');
const {parseCommand,formatCommand}=require('./commands');
const {StockBot,formatReport}=require('./stock-bot');
const data={asOf:'2026-09-27T10:00:00+07:00',items:[{name:'Ayam Bakar',current_quantity:-2,unit:'pcs'},{name:'Ayam Kampung',current_quantity:3,unit:'pcs'},{name:'Beef',current_quantity:25,unit:'pcs'}],sales:{revenue:125000,transactions:2,topProducts:[{name:'Kopi',quantity:3}]}};
test('routes all commands and rejects unrelated or historical requests',()=>{
 for(const [text,kind] of [['@62888 bantuan','help'],['stok ayam berapa?','search'],['penjualan hari ini','sales'],['produk terlaris hari ini','bestsellers'],['ringkasan hari ini','summary'],['/stok','stock'],['cek stock habis','shortages']])assert.equal(parseCommand(text).kind,kind);
 assert.equal(parseCommand('stok ayam berapa?').query,'ayam');
 for(const text of ['jangan kirim penjualan','penjualan kemarin','halo','stok sudah diupdate','penjualan 2026-09-26'])assert.equal(parseCommand(text),null);
});
test('search restricts results, supports multiword names and handles missing items',()=>{
 const body=formatCommand(parseCommand('stok ayam berapa?'),data,formatReport);
 assert.match(body,/Ayam Bakar/);assert.match(body,/Ayam Kampung/);assert.doesNotMatch(body,/Beef/);
 const one=formatCommand(parseCommand('cek stok ayam bakar'),data,formatReport);assert.doesNotMatch(one,/Kampung/);
 assert.match(formatCommand(parseCommand('stok durian'),data,formatReport),/tidak ditemukan/);
});
test('sales, bestsellers, summary and no-sales output',()=>{
 assert.match(formatCommand({kind:'sales'},data,formatReport),/Rp 125\.000/);
 assert.match(formatCommand({kind:'bestsellers'},data,formatReport),/Kopi: 3 terjual/);
 const summary=formatCommand({kind:'summary'},data,formatReport);
 assert.match(summary,/Stok Habis/);assert.match(summary,/Stok Minim/);assert.doesNotMatch(summary,/Beef/);
 assert.match(formatCommand({kind:'bestsellers'},{...data,sales:{revenue:0,transactions:0,topProducts:[]}},formatReport),/Belum ada/);
});
test('help does not require database; sales in groups still requires account mention',async()=>{
 const sent=[];let reads=0;
 const bot=new StockBot({getReport:async()=>{reads++;return data},send:async(_,chat,body)=>sent.push(body),save:async()=>{},log:()=>{}});
 const msg={from:'123@lid',id:{id:'help'},type:'chat',body:'bantuan',timestamp:Math.floor(Date.now()/1000)};
 await bot.handle(msg,{});assert.equal(reads,0);assert.match(sent[0],/Penjualan hari ini/);
 await bot.handle({...msg,from:'123@g.us',id:{id:'sales'},body:'penjualan hari ini'},{});assert.equal(reads,0);
 await bot.handle({...msg,from:'123@g.us',id:{id:'sales'},body:'penjualan hari ini',mentionedIds:['999@lid']},{info:{wid:'999@lid'}});assert.equal(reads,1);assert.equal(sent.length,2);
});
test('sales aliases and explicit division selection',()=>{
 for(const [body,divisions] of [['pendapatan cafe hari ini',['cafe']],['omset car wash',['carwash']],['omzet detailing hari ini',['detailing']],['penjualan kafe dan carwash',['cafe','carwash']],['rincian pendapatan hari ini',['cafe','carwash','detailing']]])assert.deepEqual(parseCommand(body),{kind:'sales',divisions});
 assert.deepEqual(parseCommand('pendapatan hari ini'),{kind:'sales'});
 assert.equal(parseCommand('pendapatan cafe kemarin'),null);
});
test('division report shows only requested businesses, including zero sales',()=>{
 const row={revenue:900,gross:1000,discount:100,adjustment:0,transactions:1,quantity:2};
 const scoped={...data,sales:{...data.sales,divisions:{cafe:row,carwash:{revenue:0,gross:0,discount:0,adjustment:0,transactions:0,quantity:0}}}};
 const cafe=formatCommand(parseCommand('omset cafe'),scoped,formatReport);
 assert.match(cafe,/Omzet: Rp 900/);assert.match(cafe,/Diskon: Rp 100/);assert.match(cafe,/Produk terjual: 2/);assert.doesNotMatch(cafe,/\*Carwash\*|\*Detailing\*/);
 const carwash=formatCommand(parseCommand('pendapatan carwash'),scoped,formatReport);
 assert.match(carwash,/Omzet: Rp 0/);assert.match(carwash,/Layanan terjual: 0/);assert.doesNotMatch(carwash,/\*Cafe\*/);
});
