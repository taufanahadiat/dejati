const fs = require('node:fs/promises');
const { parseCommand, formatCommand } = require('./commands');
const { StockDialog, parseEdit } = require('./stock-dialog');
function isStockRequest(body) {
  if (typeof body !== 'string' || body.length > 250) return false;
  const text = body.toLowerCase().replace(/[_*~]/g, ' ').trim();
  if (/\b(jangan|tidak|gak|nggak|ga|bukan|kemarin|besok|minggu|bulan)\b/.test(text)) return false;
  return /\b(stok|stock)\b/.test(text) &&
    (/\b(update|cek|check|lihat|minta|kirim|laporan|info|berapa|tampilkan|habis|kosong|nol|minus|minim|menipis)\b/.test(text) || /\bhari ini\b/.test(text) || /^\/?(stok|stock)[?! .]*$/.test(text));
}
function isShortageRequest(body) {
  return /\b(habis|kosong|nol|minus|minim|menipis)\b/i.test(body);
}
function widString(id) {
  return typeof id === 'string' ? id : id?._serialized || (id?.user && id?.server ? id.user + '@' + id.server : null);
}
async function mentionsBot(message, client) {
  const mentions = (message.mentionedIds || []).map(widString).filter(Boolean);
  if (!mentions.length) return false;
  const primary = widString(client.info?.wid);
  if (primary && mentions.includes(primary)) return true;
  // WhatsApp mentions may use the account's LID instead of its phone-number ID.
  const selfIds = await client.pupPage.evaluate(() => {
    const prefs = window.require('WAWebUserPrefsMeUser');
    return [prefs.getMaybeMePnUser(), prefs.getMaybeMeLidUser()].filter(Boolean)
      .map(id => id._serialized || id.user + '@' + id.server);
  });
  return mentions.some(id => selfIds.includes(id));
}
function formatReport(data, shortagesOnly = false) {
  if (!Array.isArray(data.items) || !Number.isFinite(Date.parse(data.asOf))) throw Error('Invalid stock report');
  const date = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', dateStyle: 'short', timeStyle: 'short' }).format(new Date(data.asOf));
  const lines = [shortagesOnly ? "*Stok Habis & Minim De'Jati*" : "*Update Stok De'Jati*", date + ' WIB', ''];
  for (const item of data.items) {
    if (!item.name || !item.unit || !Number.isFinite(Number(item.current_quantity))) throw Error('Invalid stock item');
  }
  const itemLine = item => '• ' + item.name + ': ' + Number(item.current_quantity).toLocaleString('id-ID', { maximumFractionDigits: 3 }) + ' ' + item.unit;
  if (shortagesOnly) {
    const empty = data.items.filter(item => Number(item.current_quantity) <= 0);
    const low = data.items.filter(item => Number(item.current_quantity) > 0 && Number(item.current_quantity) <= Number(item.minimum_quantity ?? (item.unit === 'gr' ? 50 : 5)));
    lines.push('*Stok Habis*', ...(empty.length ? empty.map(itemLine) : ['Tidak ada.']), '',
      '*Stok Minim*', ...(low.length ? low.map(itemLine) : ['Tidak ada.']));
    return lines.join('\n');
  }
  for (const item of data.items) {
    lines.push(itemLine(item));
  }
  if (!data.items.length) lines.push('Belum ada item stok aktif.');
  return lines.join('\n');
}
class StockBot {
  constructor({ getReport, send, save, dialog, now = Date.now, log = console.log }) {
    Object.assign(this, { getReport, send, save, dialog, now, log });
    this.seen = {};
    this.queue = Promise.resolve();
    this.startedAt = now();
    this.lastReply = null;
  }
  restore(seen) { if (seen && typeof seen === 'object' && !Array.isArray(seen)) this.seen = seen; }
  handle(message, client) {
    const task = this.queue.then(() => this.process(message, client));
    this.queue = task.catch(() => { this.log('Stock bot: request failed; no automatic resend.'); });
    return this.queue;
  }
  async process(message, client) {
    const chat = message.from;
    const command = parseCommand(message.body);
    const sender = chat?.endsWith('@g.us') ? message.author : chat;
    const ctx = { chat, sender, messageId: message.id?._serialized || (chat + ':' + sender + ':' + message.id?.id) };
    const id = message.id?._serialized || (message.id?.id ? chat + ':' + message.id.id : null);
    if (!id || message.fromMe || message.isStatus || message.type !== 'chat' ||
        !/^[0-9-]+@(c\.us|lid|g\.us)$/.test(chat || '') || (!command && !parseEdit(message.body) && !this.dialog?.sessions[this.dialog.key(ctx)])) return;
    if (!/^[0-9]+@(c\.us|lid)$/.test(sender || '')) return;
    const timestamp = Number(message.timestamp) * 1000;
    if (!Number.isFinite(timestamp) || timestamp < this.startedAt - 30000 || this.now() - timestamp > 300000) return;
    if (this.seen[id]) return;
    if (chat.endsWith('@g.us') && !this.dialog?.active(ctx) && !await mentionsBot(message, client)) return;
    let body;
    try { body = await this.dialog?.handle(ctx, message.body, command); }
    catch (error) { body = error.userMessage || 'Layanan perubahan stok belum dapat dipastikan. Balas YA lagi jika sedang mengonfirmasi; perubahan tidak akan digandakan.'; }
    if (!body) {
      if (!command) return;
      const data = command.kind === 'help' ? null : await this.getReport(command.kind);
      body = formatCommand(command, data, formatReport);
      if (this.dialog && command.kind === 'stock') body += '\n\nMau update stok apa? Balas nama item, atau BATAL.';
    }
    // Persist before dispatch. An ambiguous network failure must never duplicate a reply.
    const cutoff = this.now() - 7 * 86400000;
    this.seen = Object.fromEntries(Object.entries(this.seen).filter(([, at]) => at >= cutoff).slice(-4999));
    this.seen[id] = this.now();
    await this.save(this.seen);
    await this.send(client, chat, body);
    this.lastReply = new Date(this.now()).toISOString();
    this.log('Stock bot: stock report sent.');
  }
}
async function sendReport(client, chatId, body) {
  // Verify the actual outgoing model: some WhatsApp versions return no model
  // from sendMessage even after the message has been delivered.
  return client.pupPage.evaluate(async (chatId, body) => {
    const collections = window.require('WAWebCollections');
    const chat = collections.Chat.get(chatId);
    if (!chat || window.require('WAWebSocketModel').Socket.state !== 'CONNECTED') throw Error('Chat unavailable');
    const before = new Set(collections.Msg.getModelsArray());
    await window.WWebJS.sendMessage(chat, body, { mentionedJidList: [], parseVCards: false, waitUntilMsgSent: true });
    const msg = collections.Msg.getModelsArray().find(m => !before.has(m) && m.id?.fromMe && m.to?._serialized === chatId && m.body === body);
    if (!msg || msg.ack < 1) throw Error('Delivery unconfirmed; do not resend automatically');
    return { ack: msg.ack };
  }, chatId, body);
}
async function createStockBot() {
  const token = (await fs.readFile('/run/secrets/stock-token', 'utf8')).trim();
  const bot = new StockBot({
    getReport: async (kind = 'stock') => {
      const response = await fetch('http://lampp_web/api/whatsapp-stock?report=' + encodeURIComponent(kind), {
        headers: { 'X-Stock-Token': token }, signal: AbortSignal.timeout(15000)
      });
      if (!response.ok) throw Error('Stock service unavailable');
      return response.json();
    },
    send: sendReport,
    save: async seen => {
      await fs.writeFile('/data/stock-bot-seen.json.tmp', JSON.stringify(seen), { mode: 0o600 });
      await fs.rename('/data/stock-bot-seen.json.tmp', '/data/stock-bot-seen.json');
    }
  });
  bot.dialog = new StockDialog({
    getReport: () => bot.getReport('stock'),
    api: async input => {
      const response = await fetch('http://lampp_web/api/whatsapp-stock-change', {
        method: 'POST', headers: { 'X-Stock-Token': token, 'Content-Type': 'application/json' },
        body: JSON.stringify(input), signal: AbortSignal.timeout(20000)
      });
      const data = await response.json();
      if (!response.ok) { const error = new Error('Stock change failed'); error.userMessage = data.error; throw error; }
      return data;
    },
    save: async sessions => {
      await fs.writeFile('/data/stock-dialogs.json.tmp', JSON.stringify(sessions), { mode: 0o600 });
      await fs.rename('/data/stock-dialogs.json.tmp', '/data/stock-dialogs.json');
    }
  });
  try { bot.dialog.restore(JSON.parse(await fs.readFile('/data/stock-dialogs.json', 'utf8'))); } catch {}
  try { bot.restore(JSON.parse(await fs.readFile('/data/stock-bot-seen.json', 'utf8'))); } catch {}
  return bot;
}
module.exports = { StockBot, isStockRequest, isShortageRequest, mentionsBot, formatReport, createStockBot, sendReport };
