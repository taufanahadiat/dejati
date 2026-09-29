# Sinkronisasi penjualan Olsera

Closing hari ini (`Asia/Jakarta`) dari web maupun `closing-save` Android mengantrekan tanggal di `olsera_sync_jobs`, di dalam transaksi closing. Worker mengambil Excel **Penjualan berdasarkan SKU** milik `dejaticoffeegarden.myolsera.com`, memilih tanggal closing secara eksplisit, memverifikasi tanggal nama file, mengarsipkan XLSX, lalu mengirim baris ke API internal. Proses ini tetap berjalan ketika WhatsApp offline.

Impor dan mutasi stok berada dalam satu transaksi database. Pesan closing hanya dapat diklaim setelah job `succeeded`. Payload WhatsApp berisi rincian produk Olsera, total per usaha, total gabungan, dan snapshot stok sesudah impor. Penjualan Dejati dibagi per usaha mengikuti alokasi diskon/penyesuaian laporan yang sudah ada. Excel SKU tidak memuat metode pembayaran Olsera atau pajak/service di tingkat nota; angka Olsera mengikuti kolom `total sales amount`.

## Pemasangan

1. Terapkan `migrations/20260928_olsera_sales.sql` dengan koneksi database aplikasi.
2. Buat `config/olsera.credentials.json` (mode 0600, pemilik UID 1000) berisi `email` dan `password`. File ini diabaikan Git, dikecualikan dari image web, dan ditolak Apache. Jangan menaruh kredensial pada source/command line.
3. Jalankan `docker compose build olsera whatsapp` lalu `docker compose up -d --no-deps olsera whatsapp`.
4. Worker menggunakan token internal WhatsApp yang sudah ada. Tidak ada port publik untuk worker. Profil browser dan Excel disimpan pada volume `olsera_data` (`/data/profile`, `/data/exports/YYYY-MM-DD/<sha256>.xlsx`).

## Ketahanan dan stok

- Job terkunci saat klaim; lease 10 menit mencegah dua worker memproses tanggal yang sama. Browser dibatasi 4 menit. Job macet dapat diklaim ulang.
- Kegagalan diulang setelah 5 menit, bertambah sampai 60 menit. Status/error terlihat di **Report → Laporan Penjualan**. Administrator dapat menekan **Coba lagi** untuk kegagalan.
- Pencocokan nama memakai normalisasi spasi/huruf dan HTML entities, serta `nama - varian` untuk carwash/detailing. Produk yang ambigu/tidak ditemukan menghentikan seluruh impor; tidak ada pengurangan sebagian.
- Cafe memakai `stock_product_bindings.quantity_per_sale` yang sama dengan trigger order; termasuk resep gabungan dan gram beans. Produk tanpa binding tetap tercatat tanpa mutasi stok. Carwash/detailing mengikuti perilaku order saat ini (tanpa binding stok cafe).
- Aturan pemakaian dibekukan per baris impor pertama. Ekspor ulang bersifat kumulatif: hanya selisih terhadap kuantitas yang sudah diterapkan yang mengubah stok. Baris hilang mengembalikan pemakaian sebelumnya. Mutasi dapat ditelusuri dari `movement_key` berawalan `olsera:`.
- `olsera_imports` menyimpan setiap file/hash dan baris mentah; `olsera_sales` menyimpan snapshot terbaru; `olsera_stock_usage` menyimpan konsumsi yang telah diterapkan.
- Retur yang memiliki nilai uang ditahan untuk pemeriksaan kuantitas retur, karena Excel SKU tidak menyediakan kolom kuantitas retur terpisah. File salah format, mata uang selain IDR, atau tanggal/toko tidak sesuai juga ditolak.
- Closing ulang hari yang sama mengantrekan ekspor terbaru setelah job sebelumnya selesai. Tidak menggandakan stok atau mengirim ulang pesan WhatsApp yang sudah terkirim (aturan satu laporan per tanggal tetap berlaku).
- Retry setelah tengah malam tetap mengambil tanggal job, bukan tanggal baru. Closing historis yang baru dibuat tidak otomatis mengimpor Olsera.

## Pemeriksaan

```
node --test services/olsera/worker.test.js
(cd services/olsera && python3 -m unittest test_parser.py)
docker exec lampp_web php /var/www/html/tests/olsera_sync_regression.php
docker exec lampp_web php /var/www/html/tests/whatsapp_closing_regression.php
docker exec lampp_web php /var/www/html/tests/reclosing_regression.php
node --test services/whatsapp/closing-notifier.test.js
```

Tes database memakai tabel temporer per koneksi dan tidak mengubah stok atau mengirim pesan sungguhan. Jangan menghapus arsip atau tabel mutasi untuk mencoba ulang; gunakan mekanisme job agar deduplikasi tetap terjaga.
