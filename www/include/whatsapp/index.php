<?php
if (empty($_SESSION['loggedin']) || ($_SESSION['level'] ?? '') !== 'Administrator') {
    http_response_code(403);
    exit('Akses ditolak.');
}
if (empty($_SESSION['whatsapp_csrf'])) $_SESSION['whatsapp_csrf'] = bin2hex(random_bytes(32));
$breadcrumb = [['label' => 'WhatsApp']];
?>
<section class="content pt-3"><div class="container-fluid">
  <div class="card card-success card-outline" style="max-width:760px">
    <div class="card-header"><h3 class="card-title"><i class="fab fa-whatsapp mr-2"></i>WhatsApp Server</h3></div>
    <div class="card-body">
      <p>Tautkan akun WhatsApp Anda ke server Dejati melalui kode QR.</p>
      <p><strong>Bot stok aktif:</strong> kirim “update stock hari ini”, “cek stok”, atau “/stok” untuk mendapatkan daftar stok terbaru. Perintah lain: “stok ayam berapa?”, “penjualan hari ini”, “produk terlaris hari ini”, “ringkasan hari ini”, dan “bantuan”. Di grup, wajib mention akun De’Jati melalui fitur @. Kirim “stok habis” untuk daftar stok nol/minus dan stok minim sesuai batas per item secara terpisah.</p>
      <div id="wa-status" class="alert alert-secondary" role="status" aria-live="polite">Memeriksa koneksi…</div>
      <p id="wa-monitor" class="text-muted small" aria-live="polite"></p>
      <p id="wa-account" class="font-weight-bold" hidden></p>
      <div id="wa-qr-wrap" class="text-center mb-3" hidden>
        <img id="wa-qr" alt="Kode QR untuk menautkan WhatsApp" width="320" height="320" style="max-width:100%;height:auto;background:white">
        <p class="text-muted mt-2">QR diperbarui otomatis. Jangan bagikan kode ini.</p>
      </div>
      <ol class="pl-4">
        <li>Klik <strong>Tautkan WhatsApp</strong> dan tunggu QR muncul.</li>
        <li>Buka WhatsApp di ponsel → <strong>Perangkat tertaut</strong> → <strong>Tautkan perangkat</strong>.</li>
        <li>Pindai QR di halaman ini. Status akan berubah menjadi <strong>Terhubung</strong>.</li>
      </ol>
      <p class="text-muted">Sesi tersimpan di server sehingga tetap tertaut setelah server dimulai ulang.</p>
      <button id="wa-connect" type="button" class="btn btn-success" disabled>Tautkan WhatsApp</button>
      <button id="wa-disconnect" type="button" class="btn btn-outline-danger ml-2" hidden>Lepaskan akun</button>
      <p id="wa-error" class="text-danger mt-3 mb-0" role="alert"></p>
    </div>
  </div>
</div></section>
<script>
(() => {
  const csrf = <?= json_encode($_SESSION['whatsapp_csrf']) ?>;
  const status = document.getElementById('wa-status');
  const account = document.getElementById('wa-account');
  const qr = document.getElementById('wa-qr');
  const qrWrap = document.getElementById('wa-qr-wrap');
  const connect = document.getElementById('wa-connect');
  const disconnect = document.getElementById('wa-disconnect');
  const error = document.getElementById('wa-error');
  let busy = false;
  const labels = {
    disconnected: 'Belum terhubung.', starting: 'Menyiapkan WhatsApp. Mohon tunggu…',
    qr: 'Pindai QR dengan WhatsApp di ponsel Anda.', authenticated: 'Akun berhasil ditautkan. Menyelesaikan koneksi…',
    connected: 'Terhubung', disconnecting: 'Melepaskan akun…', error: 'Koneksi terputus. Server mencoba menghubungkan kembali…'
  };
  function render(data) {
    const monitoring = data.monitoring;
    const formatDate = value => new Date(value).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' }) + ' WIB';
    const results = { connected: 'Terhubung', reconnecting: 'Memulihkan koneksi', needs_qr: 'Perlu pindai QR ulang', disabled: 'Dinonaktifkan', changed: 'Koneksi berubah', pending: 'Menunggu pemeriksaan' };
    document.getElementById('wa-monitor').textContent = monitoring && monitoring.checkedAt
      ? 'Pemeriksaan terakhir: ' + formatDate(monitoring.checkedAt) + ' — ' + (results[monitoring.result] || monitoring.result) +
        '. Pemeriksaan harian berikutnya: ' + formatDate(monitoring.nextDailyCheckAt) + '.'
      : 'Pemantauan otomatis aktif. Menunggu pemeriksaan pertama.';
    status.textContent = labels[data.status] || 'Memeriksa koneksi…';
    status.className = 'alert ' + (data.status === 'connected' ? 'alert-success' : 'alert-secondary');
    qrWrap.hidden = data.status !== 'qr' || !data.qr;
    if (!qrWrap.hidden) qr.src = data.qr; else qr.removeAttribute('src');
    account.hidden = !data.account;
    account.textContent = data.account ? [data.account.name, data.account.number ? '+' + data.account.number : ''].filter(Boolean).join(' · ') : '';
    connect.disabled = !['disconnected', 'error'].includes(data.status);
    disconnect.hidden = ['disconnected', 'disconnecting'].includes(data.status);
    disconnect.disabled = false;
  }
  async function request(action) {
    if (busy) return;
    busy = true;
    connect.disabled = disconnect.disabled = true;
    try {
      const options = { cache: 'no-store', credentials: 'same-origin', signal: AbortSignal.timeout(30000) };
      if (action) { options.method = 'POST'; options.body = new URLSearchParams({ action, csrf }); }
      const response = await fetch('api/whatsapp', options);
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Gagal menghubungi layanan WhatsApp.');
      error.textContent = '';
      render(data);
    } catch (err) {
      error.textContent = err.message;
      qrWrap.hidden = true;
      qr.removeAttribute('src');
      account.hidden = true;
      status.textContent = 'Status koneksi belum dapat diperiksa.';
      status.className = 'alert alert-warning';
      connect.disabled = disconnect.disabled = false;
    } finally { busy = false; }
  }
  connect.addEventListener('click', () => request('connect'));
  disconnect.addEventListener('click', () => {
    if (confirm('Lepaskan akun WhatsApp dari server? Anda perlu memindai QR lagi untuk menautkan ulang.')) request('disconnect');
  });
  async function poll() {
    await request();
    setTimeout(poll, 3000);
  }
  poll();
})();
</script>
