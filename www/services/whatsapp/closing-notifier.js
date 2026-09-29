const fs = require('node:fs/promises');
const { formatReport, sendReport } = require('./stock-bot');
function closingMessages(payload) {
  const money = value => 'Rp ' + Number(value).toLocaleString('id-ID');
  const date = payload.date.split('-').reverse().join('/');
  const lines = ["*Closing Harian De'Jati*", 'Tanggal: ' + date,
    'Closing tersimpan: ' + payload.closedAt + ' WIB', '',
    (payload.olsera ? '*Penjualan Dejati POS*' : '*Penjualan Hari Ini*'), 'Total pendapatan: ' + money(payload.total_penjualan),
    'Transaksi lunas: ' + Number(payload.transactions).toLocaleString('id-ID'), '',
    '*Penjualan per Usaha (bruto)*', 'Cafe: ' + money(payload.cafe),
    'Carwash: ' + money(payload.carwash), 'Detailing: ' + money(payload.detailing), '',
    (payload.olsera ? '*Metode Pembayaran Dejati POS*' : '*Metode Pembayaran*'), 'QRIS: ' + money(payload.qris), 'Cash: ' + money(payload.cash), 'Kartu: ' + money(payload.card)
  ];
  const compact = value => String(value || '').replace(/\s+/g, ' ').trim();
  if (payload.olsera) {
    const o=payload.olsera;
    lines.push('', '*Penjualan Olsera POS*', 'Diambil: '+o.fetchedAt+' WIB',
      'Cafe: '+money(o.cafe), 'Carwash: '+money(o.carwash), 'Detailing: '+money(o.detailing), 'Total Olsera: '+money(o.total),
      '', '*Rincian Produk Olsera*', ...(o.items.length ? o.items.map(r=>'• '+compact(r.product_name)+(r.variant?' / '+compact(r.variant):'')+' × '+Number(r.quantity).toLocaleString('id-ID')+': '+money(r.total_sales)) : ['Tidak ada penjualan.']),
      '', '*Gabungan Dejati POS + Olsera POS*', 'Cafe: '+money(payload.combined.cafe),
      'Carwash: '+money(payload.combined.carwash), 'Detailing: '+money(payload.combined.detailing),
      'Total gabungan: '+money(payload.combined.total),
      'Olsera memakai nilai penjualan produk dari Excel; metode pembayaran Olsera tidak tersedia pada laporan ini.');
  }
  const cancelled = payload.cancelled || [];
  if (cancelled.length) {
    lines.push('', '*Transaksi Dibatalkan*', 'Jumlah: ' + cancelled.length + ' transaksi',
      'Total nilai transaksi batal: ' + money(cancelled.reduce((total, row) => total + Number(row.total_amount), 0)),
      ...cancelled.map(row => '• #' + row.id + ': ' + money(row.total_amount) + (row.cancel_reason ? ' — ' + compact(row.cancel_reason) : '')),
      'Transaksi batal tidak dihitung sebagai pendapatan.');
  }
  const expenses = payload.expenses || [];
  if (expenses.length) {
    const total = expenses.reduce((sum, row) => sum + Number(row.total), 0);
    lines.push('', '*Pengeluaran*', ...expenses.map(row => '• ' + compact(row.keterangan) + ': ' + money(row.total)),
      'Total pengeluaran: ' + money(total),
      'Pendapatan setelah pengeluaran: ' + money(Number(payload.combined?.total ?? payload.total_penjualan) - total));
  }
  return { summary: lines.join('\n'), stock: formatReport(payload.stock, true).replace("*Stok Habis & Minim De'Jati*", "*Stok Habis & Minim Setelah Closing De'Jati*") };
}
class ClosingNotifier {
  constructor({ api, getClient, send, findSent, now = Date.now, log = console.log }) {
    Object.assign(this, { api, getClient, send, findSent, now, log });
    this.busy = false;
  }
  async tick() {
    if (this.busy) return;
    const client = this.getClient();
    if (!client) return;
    this.busy = true;
    try {
      const { jobs } = await this.api();
      for (const job of jobs) {
        try { await this.process(client, job); }
        catch { this.log('Closing notification: delivery pending or uncertain; will verify before any resend.'); }
      }
    } catch { this.log('Closing notification: service unavailable; retrying next cycle.'); }
    finally { this.busy = false; }
  }
  async process(client, job) {
    const messages = closingMessages(job.payload);
    for (const part of ['summary', 'stock']) {
      if (job[part + '_state'] === 'sent') continue;
      const ctx = { date: job.closing_date, part };
      const body = job[part + '_body'] || messages[part];
      // Check the group and previous outgoing messages before attempting delivery.
      const found = await this.findSent(client, job.group_id, body);
      if (job[part + '_state'] === 'sending') {
        if (!found) {
          await this.api({ ...ctx, action: 'error', error: 'Delivery uncertain; no automatic duplicate sent. Check WhatsApp history.' });
          return;
        }
      } else {
        const claim = await this.api({ ...ctx, action: 'claim', body });
        if (!claim.claimed) return;
        {
          let timeout;
          try {
            await Promise.race([this.send(client, job.group_id, body), new Promise((_, reject) => { timeout = setTimeout(() => reject(Error('Send timeout')), 45000); })]);
          } catch {
            await this.api({ ...ctx, action: 'error', error: 'Send result uncertain; waiting for WhatsApp receipt.' });
            return;
          } finally { clearTimeout(timeout); }
        }
      }
      await this.api({ ...ctx, action: 'sent' });
      job[part + '_state'] = 'sent';
      this.log('Closing notification delivered: ' + job.closing_date + ' ' + part);
    }
  }
}
async function createClosingNotifier(manager) {
  const token = (await fs.readFile('/run/secrets/stock-token', 'utf8')).trim();
  return new ClosingNotifier({
    api: async input => {
      const response = await fetch('http://lampp_web/api/whatsapp-closing', {
        method: input ? 'POST' : 'GET', headers: { 'X-Stock-Token': token, 'Content-Type': 'application/json' },
        ...(input ? { body: JSON.stringify(input) } : {}), signal: AbortSignal.timeout(15000)
      });
      if (!response.ok) throw Error('Closing queue unavailable');
      return response.json();
    },
    getClient: () => manager.snapshot().status === 'connected' ? manager.client : null,
    send: sendReport,
    findSent: (client, chatId, body) => client.pupPage.evaluate((chatId, body) => {
      const collections = window.require('WAWebCollections');
      const chat = collections.Chat.get(chatId);
      if (!chat || chat.id.server !== 'g.us' || window.require('WAWebSocketModel').Socket.state !== 'CONNECTED') throw Error('Target group unavailable');
      return collections.Msg.getModelsArray().some(msg => msg.id?.fromMe && msg.to?._serialized === chatId && msg.body === body && msg.ack >= 1);
    }, chatId, body)
  });
}
module.exports = { ClosingNotifier, closingMessages, createClosingNotifier };
