function normalize(text) {
  return text.toLowerCase().replace(/@\d+/g, ' ').replace(/[_*~?!.,/]/g, ' ').replace(/\s+/g, ' ').trim();
}
function parseCommand(body) {
  if (typeof body !== 'string' || body.length > 250) return null;
  const text = normalize(body);
  if (/\b(jangan|tidak|gak|nggak|ga|bukan|kemarin|besok|minggu|bulan)\b/.test(text) || /\d{1,4}[-/]\d/.test(body)) return null;
  if (/^(?:(?:tolong|bot|minta|tampilkan) )?(bantuan|help|menu|daftar perintah)$/.test(text)) return { kind: 'help' };
  if (/\b(terlaris|best seller|bestseller)\b/.test(text)) return { kind: 'bestsellers' };
  if (/\b(ringkasan|rangkuman|summary)\b/.test(text)) return { kind: 'summary' };
  if (/\b(penjualan|pendapatan|omzet|omset)\b/.test(text)) {
    const divisions = [];
    if (/\b(cafe|café|kafe)\b/.test(text)) divisions.push('cafe');
    if (/\b(carwash|car wash|cuci mobil|cuci motor)\b/.test(text)) divisions.push('carwash');
    if (/\bdetailing\b/.test(text)) divisions.push('detailing');
    if (!divisions.length && /\b(detail|rincian|rinci|detailkan|per layanan|per usaha)\b/.test(text)) divisions.push('cafe', 'carwash', 'detailing');
    return divisions.length ? { kind: 'sales', divisions } : { kind: 'sales' };
  }
  if (!/\b(stok|stock)\b/.test(text)) return null;
  if (/\b(habis|kosong|nol|minus|minim|menipis)\b/.test(text)) return { kind: 'shortages' };
  if (/\b(sudah|diupdate|diperbarui)\b/.test(text)) return null;
  const query = text.replace(/\bhari ini\b/g, ' ').replace(/\b(stok|stock|update|cek|check|lihat|minta|kirim|laporan|info|berapa|tampilkan|tolong|bot|dong|ya|kak|bang|pak|bu|sisa|untuk|item|produk|semua|terbaru|sekarang|ada|jumlah)\b/g, ' ').replace(/\s+/g, ' ').trim();
  return query ? { kind: 'search', query } : { kind: 'stock' };
}
const helpText = ["*Bantuan Bot De'Jati*", '',
  '• Update stok hari ini — semua stok',
  '• Stok ayam berapa? — cari item stok',
  '• Stok habis — Stok Habis dan Stok Minim',
  '• Penjualan hari ini — omzet dan jumlah transaksi lunas',
  '• Pendapatan cafe / omset carwash / omzet detailing hari ini — rincian per usaha',
  '• Rincian penjualan hari ini — rincian ketiga usaha',
  '• Produk terlaris hari ini — 10 produk cafe teratas berdasarkan jumlah terjual',
  '• Ringkasan hari ini — penjualan dan stok yang perlu diperhatikan',
  '• Bantuan — daftar perintah', '',
  'Di grup, mention akun De’Jati dengan fitur @. Chat pribadi tidak perlu mention.',
  'Laporan hari ini menggunakan WIB. Batas stok minim mengikuti pengaturan tiap item.'
].join('\n');
function formatCommand(command, data, formatStock) {
  if (command.kind === 'help') return helpText;
  if (command.kind === 'stock') return formatStock(data);
  if (command.kind === 'shortages') return formatStock(data, true);
  if (command.kind === 'search') {
    const terms = normalize(command.query).split(' ');
    const items = data.items.filter(item => terms.every(term => normalize(item.name).includes(term)));
    if (!items.length) return 'Item stok “' + command.query + '” tidak ditemukan. Coba nama lain, misalnya “stok ayam berapa?”.';
    return formatStock({ ...data, items });
  }
  const sales = data.sales;
  if (!sales || !Number.isFinite(Number(sales.revenue)) || !Number.isFinite(Number(sales.transactions))) throw Error('Invalid sales report');
  const date = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', dateStyle: 'short', timeStyle: 'short' }).format(new Date(data.asOf)) + ' WIB';
  const salesLines = ['Omzet: Rp ' + Number(sales.revenue).toLocaleString('id-ID'), 'Transaksi lunas: ' + Number(sales.transactions).toLocaleString('id-ID'),
    'Mencakup cafe, carwash, dan detailing. Berdasarkan tanggal transaksi hari ini; OPEN BILL dan CANCEL tidak dihitung.'];
  if (command.kind === 'sales' && command.divisions?.length) {
    const labels = { cafe: 'Cafe', carwash: 'Carwash', detailing: 'Detailing' };
    const money = value => 'Rp ' + Number(value).toLocaleString('id-ID');
    const sections = command.divisions.map(key => {
      const row = sales.divisions?.[key];
      if (!labels[key] || !row || ['gross','discount','adjustment','revenue','transactions','quantity'].some(field => !Number.isFinite(Number(row[field])))) throw Error('Invalid division report');
      return ['*' + labels[key] + '*', 'Omzet: ' + money(row.revenue),
        'Penjualan bruto: ' + money(row.gross), 'Diskon: ' + money(row.discount),
        ...(Number(row.adjustment) ? ['Penyesuaian: ' + money(row.adjustment)] : []),
        'Transaksi lunas: ' + Number(row.transactions).toLocaleString('id-ID'),
        (key === 'cafe' ? 'Produk terjual: ' : 'Layanan terjual: ') + Number(row.quantity).toLocaleString('id-ID')].join('\n');
    });
    return ["*Rincian Penjualan Hari Ini De'Jati*", date, '', sections.join('\n\n'), '',
      'Hanya transaksi PAID. Diskon dan penyesuaian pesanan campuran dibagi proporsional sesuai laporan penjualan.',
      ...(command.divisions.length > 1 ? ['Satu transaksi campuran dapat dihitung pada beberapa usaha.'] : [])].join('\n');
  }
  if (command.kind === 'sales') return ["*Penjualan Hari Ini De'Jati*", date, '', ...salesLines].join('\n');
  if (command.kind === 'summary') return ["*Ringkasan Hari Ini De'Jati*", date, '', ...salesLines, '', ...formatStock(data, true).split('\n').slice(3)].join('\n');
  if (!Array.isArray(sales.topProducts)) throw Error('Invalid bestseller report');
  const lines = sales.topProducts.map((item, i) => `${i + 1}. ${item.name}: ${Number(item.quantity).toLocaleString('id-ID')} terjual`);
  return ["*Produk Cafe Terlaris Hari Ini*", date, 'Berdasarkan jumlah terjual dari transaksi lunas.', '', ...(lines.length ? lines : ['Belum ada produk cafe terjual dari transaksi lunas hari ini.'])].join('\n');
}
module.exports = { parseCommand, formatCommand, helpText };
