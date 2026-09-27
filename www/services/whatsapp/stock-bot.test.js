const { test } = require('node:test');
const assert = require('node:assert/strict');
const { StockBot, isStockRequest, formatReport } = require('./stock-bot');
const report = { asOf: '2026-09-27T10:46:00+07:00', items: [
  { name: 'Ayam', current_quantity: '-44.000', unit: 'pcs' },
  { name: 'Dimsum', current_quantity: '0.000', unit: 'pcs' },
  { name: 'Beans', current_quantity: '5000.000', unit: 'gr' }
] };
test('recognizes requests and ignores unrelated/negative/historical messages', () => {
  for (const text of ['update stock hari ini', 'tolong kirim stok hari ini', 'cek stock', '/stok', 'stok hari ini?', 'bot, minta laporan stok']) assert.ok(isStockRequest(text), text);
  for (const text of ['stok sudah diupdate', 'jangan kirim update stock hari ini', 'stok kemarin', 'update jadwal hari ini', "*Update Stok De'Jati*\n" + 'a'.repeat(300)]) assert.equal(isStockRequest(text), false, text);
});
test('report contains quantities without zero/minus tags or balance movements', () => {
  const body = formatReport(report);
  assert.ok(body.includes('Ayam: -44 pcs'));
  assert.ok(body.includes('Dimsum: 0 pcs'));
  assert.ok(body.includes('Beans: 5.000 gr'));
  assert.doesNotMatch(body, /\[MINUS\]|\[NOL\]|perubahan|saldo/i);
});
function setup() {
  const now = Date.now(), sent = [], saved = [];
  const bot = new StockBot({ now: () => now, getReport: async () => report,
    send: async (_, chat, body) => sent.push({ chat, body }), save: async state => saved.push({ ...state }), log: () => {} });
  const msg = { id: { id: 'request-1' }, from: '123@lid', fromMe: false, type: 'chat', body: 'update stock hari ini', timestamp: Math.floor(now / 1000) };
  return { bot, msg, sent, saved };
}
test('replies in the requesting private chat and group, once per message', async () => {
  const { bot, msg, sent, saved } = setup();
  await Promise.all([bot.handle(msg, {}), bot.handle(msg, {})]);
  await bot.handle({ ...msg, from: '123-456@g.us', mentionedIds: ['999@c.us'] }, { info: { wid: { _serialized: '999@c.us' } } });
  assert.deepEqual(sent.map(x => x.chat), ['123@lid', '123-456@g.us']);
  assert.equal(saved.length, 2);
  const second = setup(); second.bot.restore(saved.at(-1));
  await second.bot.handle(msg, {});
  assert.equal(second.sent.length, 0);
});
test('ignores own messages, statuses and historical sync', async () => {
  const { bot, msg, sent } = setup();
  await bot.handle({ ...msg, fromMe: true }, {});
  await bot.handle({ ...msg, from: 'status@broadcast' }, {});
  await bot.handle({ ...msg, timestamp: msg.timestamp - 3600 }, {});
  assert.equal(sent.length, 0);
});
test('data failure sends no fabricated report; uncertain delivery is not retried', async () => {
  const { bot, msg, sent } = setup();
  bot.getReport = async () => { throw Error('offline'); };
  await bot.handle(msg, {}); assert.equal(sent.length, 0);
  bot.getReport = async () => report;
  let attempts = 0;
  bot.send = async () => { attempts++; throw Error('uncertain'); };
  await bot.handle(msg, {}); await bot.handle(msg, {});
  assert.equal(attempts, 1);
});
test('group requires an actual mention of this account; private chats do not', async () => {
  const { bot, msg, sent } = setup();
  const client = { info: { wid: { _serialized: '999@c.us' } }, pupPage: { evaluate: async () => ['999@c.us', '777@lid'] } };
  const group = { ...msg, from: '123-456@g.us' };
  await bot.handle({ ...group, body: '@Dejati update stock hari ini' }, client);
  await bot.handle({ ...group, mentionedIds: ['888@c.us'] }, client);
  assert.equal(sent.length, 0);
  await bot.handle({ ...group, mentionedIds: [{ _serialized: '777@lid' }] }, client);
  assert.equal(sent.length, 1);
  await bot.handle(msg, client);
  assert.equal(sent.length, 2);
});
test('stock habis separates zero/negative from low stock including threshold boundaries', async () => {
  for (const text of ['stok habis', 'minta stock yang habis', '@999 stock minim', 'cek stok kosong']) assert.ok(isStockRequest(text), text);
  const data = { ...report, items: [...report.items,
    { name: 'Sedikit', current_quantity: '0.5', unit: 'gr' },
    { name: 'Batas', current_quantity: '5', unit: 'pcs' },
    { name: 'Cukup', current_quantity: '5.001', unit: 'pcs' }
  ] };
  const body = formatReport(data, true);
  const [empty, low] = body.split('*Stok Minim');
  assert.match(empty, /Ayam: -44 pcs/); assert.match(empty, /Dimsum: 0 pcs/);
  assert.doesNotMatch(empty, /Sedikit|Batas/);
  assert.match(low, /Sedikit: 0,5 gr/); assert.match(low, /Batas: 5 pcs/);
  assert.doesNotMatch(low, /Ayam|Dimsum|Cukup|Beans/);
  assert.doesNotMatch(body, /\[MINUS\]|\[NOL\]|perubahan saldo/i);
  const { bot, msg, sent } = setup();
  bot.getReport = async () => data;
  await bot.handle({ ...msg, body: 'stok habis' }, {});
  assert.equal(sent[0].body, body);
});
test('empty shortage sections are explicit and normal reports still include all items', () => {
  const body = formatReport({ ...report, items: [report.items[2]] }, true);
  assert.equal((body.match(/Tidak ada\./g) || []).length, 2);
  assert.ok(formatReport(report).includes('Beans: 5.000 gr'));
});
test('per-item thresholds override defaults; grams default to 50 and zero disables low stock',()=>{
 const items=[
  {name:'Gram default',current_quantity:50,unit:'gr'},
  {name:'Gram above',current_quantity:50.001,unit:'gr'},
  {name:'Custom high',current_quantity:80,unit:'pcs',minimum_quantity:100},
  {name:'Custom low',current_quantity:3,unit:'pcs',minimum_quantity:2},
  {name:'Disabled',current_quantity:1,unit:'pcs',minimum_quantity:0},
  {name:'Empty disabled',current_quantity:0,unit:'pcs',minimum_quantity:0},
  {name:'Fraction',current_quantity:0.5,unit:'gr',minimum_quantity:0.5}
 ];
 const body=formatReport({...report,items},true);
 assert.match(body,/\*Stok Minim\*/);assert.doesNotMatch(body,/>0|5 satuan/);
 for(const name of ['Gram default','Custom high','Empty disabled','Fraction'])assert.ok(body.includes(name),name);
 for(const name of ['Gram above','Custom low','Disabled'])assert.ok(!body.includes('• '+name+':'),name);
});
