const { createHash } = require('node:crypto');
const hash = value => createHash('sha256').update(value).digest('hex');
const clean = text => String(text || '').replace(/@\d+/g, '').replace(/[*_~]/g, '').replace(/[?!]+$/g, '').trim().toLowerCase();
const qty = value => Number(value).toLocaleString('id-ID', { maximumFractionDigits: 3 });
function parseAmount(text, mode = 'set') {
  const match = clean(text).match(/^(?:(?:jadi|menjadi|set|ubah jadi)\s+|(?<operation>tambah(?:kan)?|kurang(?:i)?)\s+)?(?<number>-?\d+(?:[.,]\d+)*)(?:\s*(?<unit>pcs|gr|gram))?$/);
  if (!match) return null;
  const op = match.groups.operation;
  if (op) mode = op.startsWith('tambah') ? 'add' : 'subtract';
  else if (/^(jadi|menjadi|set|ubah jadi)\b/.test(clean(text))) mode = 'set';
  let number = match.groups.number;
  // Indonesian notation: 5.000 = five thousand; 0,5 = half.
  if (/^-?\d{1,3}(?:\.\d{3})+(?:,\d{1,3})?$/.test(number)) number = number.replace(/\./g, '');
  number = number.replace(',', '.');
  if (!/^-?\d+(?:\.\d{1,3})?$/.test(number) || Math.abs(Number(number)) > 999999999 || (mode !== 'set' && Number(number) < 0)) return null;
  return { mode, amount: String(Number(number)), unit: match.groups.unit === 'gram' ? 'gr' : match.groups.unit };
}
function parseEdit(text) {
  const match = clean(text).match(/^(?:tolong\s+)?(update|ubah|rubah|ganti|set|tambah(?:kan)?|kurang(?:i)?)\s+(?:stok|stock)\s+(.+)$/);
  if (!match || /^(hari ini|saat ini|sekarang|terbaru|semua)$/.test(match[2])) return null;
  let mode = match[1].startsWith('tambah') ? 'add' : match[1].startsWith('kurang') ? 'subtract' : 'set';
  let name = match[2];
  const tail = name.match(/^(.*?)\s+(?:(?:jadi|menjadi|ke|=)\s*)?(-?\d+(?:[.,]\d+)*(?:\s*(?:pcs|gr|gram))?)$/);
  let amount;
  if (tail) { name = tail[1]; amount = parseAmount(tail[2], mode); if (!amount) return { invalid: true }; }
  return { query: name, mode, ...amount };
}
function confirmation(result, group = false) {
  return [result.status === 'changed' ? 'Stok berubah sejak konfirmasi sebelumnya. Mohon konfirmasi ulang.' : '*Konfirmasi Perubahan Stok*',
    'Item: ' + result.name, 'Stok awal saat ini: ' + qty(result.old) + ' ' + result.unit,
    'Stok akhir yang akan disimpan: ' + qty(result.new) + ' ' + result.unit,
    'Perubahan: ' + (result.delta > 0 ? '+' : '') + qty(result.delta) + ' ' + result.unit, '',
    'Balas Ya untuk menyimpan, atau BATAL.',
    'Berlaku 10 menit.' + (group ? ' Hanya pengirim permintaan ini yang dapat mengonfirmasi.' : '')].join('\n');
}
class StockDialog {
  constructor({ api, getReport, save, now = Date.now }) {
    Object.assign(this, { api, getReport, save, now }); this.sessions = {};
  }
  restore(value) { if (value && typeof value === 'object' && !Array.isArray(value)) this.sessions = value; }
  key(ctx) { return ctx.chat + ':' + ctx.sender; }
  active(ctx) { return this.sessions[this.key(ctx)]?.expires > this.now(); }
  async store(ctx, session) {
    for (const [key, value] of Object.entries(this.sessions)) if (value.expires <= this.now()) delete this.sessions[key];
    if (session) this.sessions[this.key(ctx)] = { ...session, expires: this.now() + 600000 };
    else delete this.sessions[this.key(ctx)];
    await this.save(this.sessions);
  }
  async cancel(ctx) {
    const old = this.sessions[this.key(ctx)];
    const result = old?.token ? await this.api({ ...ctx, action: 'cancel', token: old.token }) : null;
    await this.store(ctx, null);
    return result;
  }
  async select(ctx, query, options = {}) {
    const data = await this.getReport();
    const exact = data.items.filter(item => clean(item.name) === clean(query));
    const terms = clean(query).split(/\s+/);
    const matches = exact.length ? exact : data.items.filter(item => terms.every(term => clean(item.name).includes(term)));
    if (matches.length !== 1) {
      await this.store(ctx, { stage: 'select', ...options });
      return matches.length ? 'Ada beberapa item yang cocok. Balas nama lengkap:\n' + matches.map(item => '• ' + item.name).join('\n') : 'Item stok tidak ditemukan. Balas nama item yang ingin diubah, atau BATAL.';
    }
    const item = matches[0];
    if (options.amount !== undefined) return this.prepare(ctx, item, options);
    await this.store(ctx, { stage: 'amount', item, mode: options.mode || 'set' });
    return `Stok ${item.name} saat ini: ${qty(item.current_quantity)} ${item.unit}.\nMau update jadi berapa, tambah berapa, atau kurang berapa?\n` +
      (options.mode === 'add' ? 'Balas angka jumlah yang ditambahkan' : options.mode === 'subtract' ? 'Balas angka jumlah yang dikurangi' : 'Balas angka untuk jumlah akhir') + `, misalnya 10. Bisa juga “tambah 2” / “kurang 2”. Satuan: ${item.unit}.\nBelum ada perubahan. Balas BATAL untuk membatalkan.`;
  }
  async prepare(ctx, item, input) {
    const token = hash(ctx.chat + ':' + ctx.sender + ':' + ctx.messageId);
    const result = await this.api({ ...ctx, action: 'prepare', token, itemId: item.id, ...input });
    if (result.status !== 'pending') return 'Permintaan ini sudah diproses. Kirim permintaan stok baru.';
    await this.store(ctx, { stage: 'confirm', token, item, mode: input.mode });
    return confirmation(result, ctx.chat.endsWith('@g.us'));
  }
  async handle(ctx, body, command) {
    const text = clean(body), edit = parseEdit(body);
    const key = this.key(ctx);
    let session = this.sessions[key];
    const yes = /^(ya|iya|iyah|yap|yup|betul|benar|bener|setuju|oke|ok|lanjut|simpan)(?:\s+(?:benar|betul|setuju|lanjut|simpan))?$/.test(text);
    if (session && !this.active(ctx)) {
      const previous = await this.cancel(ctx); session = null;
      if (previous?.status === 'applied') return `Perubahan sebelumnya sudah tersimpan: ${previous.name}, ${qty(previous.old)} → ${qty(previous.new)} ${previous.unit}.`;
      if (yes || parseAmount(text)) return 'Permintaan sudah kedaluwarsa. Kirim ulang item stok yang ingin diubah.';
    }
    if (/^(batal|batalkan|cancel|tidak|nggak|jangan)$/.test(text) && session) {
      const previous = await this.cancel(ctx);
      if (previous?.status === 'applied') return `Perubahan sudah tersimpan sebelumnya: ${previous.name}, ${qty(previous.old)} → ${qty(previous.new)} ${previous.unit}. Kirim permintaan baru untuk mengubah lagi.`;
      return 'Perubahan stok dibatalkan. Tidak ada stok yang diubah.';
    }
    if (edit) {
      if (edit.invalid) return 'Jumlah tidak valid. Contoh: “ubah stok Ayam Bakar jadi 10” atau “tambah stok Ayam Bakar 2”.';
      await this.cancel(ctx);
      return this.select(ctx, edit.query, edit);
    }
    if (command?.kind === 'stock') {
      await this.cancel(ctx);
      await this.store(ctx, { stage: 'select', mode: 'set' });
      return null; // Normal stock report will include the follow-up prompt.
    }
    if (!session) return null;
    if (session.stage === 'confirm' && yes) {
      const result = await this.api({ ...ctx, action: 'confirm', token: session.token, confirmationId: hash(ctx.messageId) });
      if (result.status === 'changed') { await this.store(ctx, session); return confirmation(result, ctx.chat.endsWith('@g.us')); }
      await this.store(ctx, null);
      if (result.status === 'applied') return `Stok ${result.name} berhasil diperbarui.\nStok awal: ${qty(result.old)} ${result.unit}\nPerubahan: ${result.delta > 0 ? '+' : ''}${qty(result.delta)} ${result.unit}\nStok terupdate: ${qty(result.new)} ${result.unit}`;
      return 'Permintaan tidak berlaku lagi atau item berubah. Kirim ulang permintaan perubahan stok.';
    }
    const amount = parseAmount(text, session.mode);
    if (amount && session.item) return this.prepare(ctx, session.item, amount);
    if (command && !['search'].includes(command.kind)) { await this.cancel(ctx); return null; }
    if (session.stage === 'select') return this.select(ctx, command?.kind === 'search' ? command.query : text, session);
    if (session.stage === 'amount') return 'Balas jumlah akhir (contoh 10), “tambah 2”, “kurang 2”, atau BATAL.';
    return 'Belum disimpan. Balas Ya untuk konfirmasi, atau BATAL.';
  }
}
module.exports = { StockDialog, parseAmount, parseEdit, confirmation };
