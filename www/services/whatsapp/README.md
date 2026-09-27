# WhatsApp server Dejati

Administrator membuka `main?id=whatsapp`, menekan **Tautkan WhatsApp**, lalu memindai QR melalui WhatsApp > Perangkat tertaut > Tautkan perangkat. Halaman menampilkan nomor/nama ketika koneksi siap. QR hanya tersedia untuk Administrator; endpoint perubahan memerlukan token CSRF.

## Menjalankan

Dari direktori `www`:

```sh
docker compose up -d --build whatsapp web
```

Layanan Node/Chromium berjalan sebagai pengguna non-root tanpa port host, pada jaringan khusus bersama web. PHP meneruskan permintaan yang sudah diautentikasi ke layanan ini. Jangan mempublikasikan port 3000. Direktori layanan ditolak oleh Apache.

Sesi perangkat berada dalam Docker volume `whatsapp_data`, di luar document root dan Git. Jangan hapus volume jika ingin mempertahankan akun tertaut. Layanan menyambung ulang otomatis ketika koneksi terputus, dan memulihkan sesi setelah restart. **Lepaskan akun** mengakhiri sesi; pemindaian QR diperlukan untuk menautkan ulang. Akun juga dapat dilepas dari menu Perangkat tertaut di ponsel.

```sh
docker compose ps whatsapp
docker compose logs --tail=50 whatsapp
node --test services/whatsapp/manager.test.js
```

Implementasi menggunakan whatsapp-web.js (integrasi WhatsApp Web komunitas, bukan WhatsApp Business Cloud API). Referensi: https://wwebjs.dev/guide/creating-your-bot/authentication . Penautan akhir harus dilakukan pemilik akun di ponsel. Bot membalas permintaan stok yang dikenali secara otomatis.

Dependency audit: versi 1.34.7 membawa advisory transitif `extract-zip` melalui pengunduh Puppeteer. Image ini menonaktifkan pengunduhan Puppeteer dan memakai Chromium dari Debian; alur ekstraksi arsip tersebut tidak dipakai. Tinjau kembali ketika memperbarui dependensi.

## Pemantauan otomatis

Layanan memeriksa koneksi WhatsApp yang sebenarnya dengan `getState()` setiap 60 detik (batas waktu 15 detik). Koneksi yang terputus dipulihkan dengan sesi tersimpan. Jika pemulihan/inisialisasi macet selama lima menit, layanan restart melalui kebijakan Docker `unless-stopped`. Pemantauan tidak membatalkan pelepasan akun yang disengaja dan tidak menghapus sesi tersimpan.

Hasil pemeriksaan terakhir dan maksimum 30 catatan harian disimpan secara atomik di `/data/monitor.json` dalam volume persisten. Satu catatan harian dibuat setiap 24 jam, dihitung dari catatan sebelumnya; setelah downtime, pemeriksaan yang sudah jatuh tempo dijalankan pada siklus berikutnya. Halaman WhatsApp menampilkan hasil terakhir dan jadwal berikutnya dalam WIB. Endpoint internal `POST /check` dapat menjalankan pemeriksaan segera tanpa mengirim pesan.

Sesi yang dicabut dari ponsel/WhatsApp memerlukan pemindaian QR ulang; pemantauan menampilkan `needs_qr`. Pemantauan koneksi sendiri tidak mengirim pesan; bot stok membalas permintaan yang dikenali.

Jalankan seluruh tes koneksi dan pemantauan: `node --test services/whatsapp/*.test.js`.

Volume sesi hanya boleh digunakan oleh satu instance layanan WhatsApp. Saat container diganti, layanan membersihkan berkas kunci proses Chromium lama (`Singleton*` dan `DevToolsActivePort`), tanpa menghapus data autentikasi, agar sesi dapat dibuka dengan hostname container baru.


## Bot stok

Pesan teks masuk di chat pribadi dan grup seperti `update stock hari ini`, `cek stok`, `minta laporan stok`, atau `/stok` dibalas di chat yang sama. Semua peserta chat/grup yang dapat mengirim pesan ke akun ini dapat meminta stok, sesuai pengaturan yang diminta pemilik. Laporan diambil saat permintaan diterima dari saldo item aktif Stock Management. Format hanya tanggal/WIB dan daftar nama, jumlah, satuan; angka negatif/nol tetap dipertahankan tanpa label tambahan. Tidak ada bagian perubahan saldo.

Pesan keluar/status, sinkronisasi pesan lama, permintaan tanggal lampau, dan pesan yang melarang pengiriman diabaikan. ID permintaan dicatat sebelum pengiriman untuk mencegah balasan ganda setelah restart. Riwayat deduplikasi disimpan maksimal tujuh hari/5.000 permintaan di volume sesi. Jika pengiriman tidak bisa dipastikan, bot tidak mengirim ulang otomatis. Permintaan baru dapat dikirim ulang oleh pengguna.

PHP menyediakan endpoint `api/whatsapp-stock` dengan token pada header `X-Stock-Token`. Token lokal `config/whatsapp-bot.token` tidak masuk Git/image dan ditolak melalui Apache; file yang sama dipasang read-only di container WhatsApp. Saat instalasi baru, buat token acak sebelum menjalankan Compose, misalnya `python3 -c "import secrets; print(secrets.token_hex(32))" > config/whatsapp-bot.token`.


### Mention grup dan stok habis

Di grup, bot hanya merespons jika pesan menyertakan mention WhatsApp akun Dejati (ID nomor telepon atau LID), bukan sekadar teks nama. Chat pribadi tidak memerlukan mention. Permintaan seperti `stok habis`, `cek stock kosong`, atau `stok minim` menghasilkan dua daftar: saldo <=0 dalam **Stok Habis**, lalu saldo >0 sampai <=5 dalam **Stok Minim**, mengikuti batas di halaman Stock Management. Satuan mengikuti item, termasuk gr. Item di atas 5 tidak ditampilkan pada laporan ini. Bagian kosong diberi keterangan `Tidak ada.`. Permintaan stok biasa tetap menampilkan seluruh item aktif.


### Perintah laporan tambahan

- `stok ayam berapa?` / `cek stok ayam bakar`: pencarian nama stok aktif, tidak peka huruf besar/kecil. Semua kata pencarian harus ditemukan pada nama item; beberapa item yang cocok ditampilkan bersama. Jika tidak ada, bot memberi tahu pengguna.
- `penjualan hari ini`: omzet seluruh usaha dan jumlah transaksi PAID. Status kosong menggunakan aturan laporan closing (paid_amount nol = OPEN BILL, selain itu PAID). Omzet menjumlahkan total_amount satu kali per order, bukan uang yang diserahkan pelanggan.
- `produk terlaris hari ini`: maksimal 10 produk cafe, berdasarkan total quantity pada transaksi PAID, termasuk penggabungan dine-in/take-away untuk ID produk yang sama. Bukan peringkat layanan carwash/detailing.
- `ringkasan hari ini`: penjualan dan daftar Stok Habis/Stok Minim.
- `bantuan` / `help` / `menu`: daftar perintah, dapat dijawab tanpa akses database.

Hari ini mengikuti created_at order dalam rentang 00:00 WIB sampai sebelum 00:00 esok hari, sesuai laporan closing aplikasi. Transaksi CANCEL dan OPEN BILL tidak dihitung. Pengguna di grup tetap wajib mention akun bot, termasuk untuk perintah bantuan. Laporan stok tetap tidak menampilkan label [NOL]/[MINUS] atau perubahan saldo.

Tes SQL tanpa perubahan permanen: `docker exec lampp_web php /var/www/html/tests/whatsapp_sales_regression.php` (menggunakan tabel sementara dalam koneksi tes).

### Rincian penjualan per usaha

Kata `penjualan`, `pendapatan`, `omzet`, dan `omset` dikenali. Contoh: `pendapatan cafe hari ini`, `omset carwash`, `penjualan detailing`, atau `omzet cafe dan carwash`. Alias kafe/car wash/cuci mobil/cuci motor juga dikenali. Tanpa menyebut usaha, laporan tetap gabungan. `Rincian penjualan hari ini` menampilkan ketiga usaha secara terpisah.

Rincian berisi omzet neto, bruto, diskon, penyesuaian bila ada, jumlah transaksi lunas yang memuat usaha tersebut, dan kuantitas produk/layanan. Alokasi diskon/penyesuaian transaksi campuran memakai fungsi pembulatan `salesExportAllocate` yang sama dengan ekspor penjualan. Satu transaksi campuran dapat terhitung pada beberapa usaha; jumlah transaksi antar-usaha tidak boleh dijumlahkan sebagai jumlah transaksi unik. Pesanan tanpa detail item tetap masuk total gabungan, tetapi tidak dapat dipetakan ke usaha tertentu.
