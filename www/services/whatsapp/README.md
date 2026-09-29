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

Di grup, bot hanya merespons jika pesan menyertakan mention WhatsApp akun Dejati (ID nomor telepon atau LID), bukan sekadar teks nama. Chat pribadi tidak memerlukan mention. Permintaan seperti `stok habis`, `cek stock kosong`, atau `stok minim` menghasilkan dua daftar: saldo <=0 dalam **Stok Habis**, lalu saldo >0 sampai batas minim item dalam **Stok Minim**, mengikuti pengaturan di halaman Stock Management. Satuan mengikuti item, termasuk gr. Item di atas batas minimnya tidak ditampilkan pada laporan ini. Bagian kosong diberi keterangan `Tidak ada.`. Permintaan stok biasa tetap menampilkan seluruh item aktif.


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


Batas minim disimpan di `stock_items.minimum_quantity`, dapat diedit pada Edit Item Stock. Migration `20260927_stock_minimum.sql` mengisi default awal 5 untuk pcs dan 50 untuk gr; pengulangan migration tidak menimpa batas yang sudah dikustomisasi. Item baru memakai default sesuai satuan. Batas 0 menonaktifkan kategori minim untuk item tersebut, tetapi stok nol/minus tetap masuk Stok Habis. Judul bot cukup **Stok Minim**.

### Perubahan stok melalui percakapan

- `update stok hari ini` menampilkan stok dan bertanya item yang ingin diubah; balas nama item, lalu angka jumlah akhir atau `tambah 2` / `kurang 2`.
- `Update Stok Ayam Bakar` / `Rubah Stok Ayam Bakar` memilih item dan meminta jumlah. `tambah stok Ayam Bakar 2` langsung menyiapkan preview penambahan; `kurang stok Ayam Bakar` meminta jumlah pengurangan.
- Angka biasa berarti saldo akhir, kecuali pertanyaan sebelumnya secara khusus meminta jumlah tambah/kurang. Notasi Indonesia didukung: `5.000 gr` berarti 5000 gr; `0,5` berarti setengah satuan.
- Item ambigu tidak dipilih otomatis; pengguna harus memilih nama lengkap.
- Preview berisi stok awal, stok akhir, selisih, dan satuan. Hanya balasan afirmatif utuh seperti YA/IYA/BETUL/BENAR/SETUJU/OK/SIMPAN dari pengirim yang sama di chat yang sama yang mengeksekusi. BATAL/TIDAK membatalkan. Masa berlaku 10 menit.
- Di grup, awali percakapan dengan mention akun bot. Selama percakapan aktif, pengirim yang sama dapat membalas nama/jumlah/konfirmasi tanpa mention lagi. Pengguna lain tidak dapat mengonfirmasi permintaan tersebut.
- Permintaan disimpan di `whatsapp_stock_changes`, percakapan di `/data/stock-dialogs.json`. Perubahan hanya melalui endpoint bertoken `api/whatsapp-stock-change`. Migrasikan `20260927_whatsapp_stock_changes.sql` sebelum menjalankan bot versi ini.
- Saat konfirmasi, server mengunci item dan mengecek saldo serta revisi ledger. Jika stok berubah, preview diperbarui dan wajib dikonfirmasi lagi dengan pesan baru. Nama/satuan item yang berubah membatalkan permintaan.
- Saldo berubah melalui `stock_movements` dengan kunci idempoten `whatsapp:<token>`, mencatat pengirim dan nilai sebelum/sesudah. Konfirmasi berulang tidak menggandakan perubahan; status sukses hanya dikirim setelah commit database.
- Pengujian database menggunakan tabel sementara: `docker exec lampp_web php /var/www/html/tests/whatsapp_stock_change_regression.php`.

### Notifikasi closing ke grup management

Closing tanggal hari ini (WIB) yang berhasil tersimpan dari web atau Android membuat satu antrean `whatsapp_closing_notifications` per tanggal, dalam transaksi database yang sama. Layanan memeriksa antrean setiap 30 detik ketika WhatsApp terhubung. Pemeriksaan juga menangkap closing hari ini yang sudah ada sebelum fitur diaktifkan. Closing tanggal lampau yang baru disinkronkan tidak otomatis dibroadcast.

Grup tujuan tetap: De'Jati Cafe Management (`6285711508770-1598438043@g.us`). Pesan pertama berisi angka closing tersimpan: total pendapatan, jumlah transaksi PAID sampai waktu closing, penjualan bruto cafe/carwash/detailing, QRIS, cash, dan kartu (credit_card ditambah debit/debit_card/card bila ada). Pesan kedua adalah Stok Habis dan Stok Minim dengan batas per item. Snapshot stok dibekukan saat antrean dibuat; saldo nol/negatif dan stok minim dipisahkan. Tidak memerlukan mention karena pemicunya adalah closing tersimpan.

Antrean mencatat status dan isi kedua pesan secara terpisah. Pesan kedua hanya dikirim sesudah pesan pertama terkonfirmasi. Simpan ulang tanggal yang sama tidak mengirim ulang. Setelah hasil kirim tidak pasti, worker mencari pesan keluar yang cocok dan sudah diakui WhatsApp, lalu melanjutkan tanpa duplikasi. Jika bukti pengiriman belum ditemukan, status tetap `sending` dengan `last_error` dan tidak mengirim ulang secara buta; periksa riwayat grup sebelum tindakan manual. Antrean tanggal lain tetap diproses.

Migrasi: `20260927_whatsapp_closing.sql`. Tes SQL: `docker exec lampp_web php /var/www/html/tests/whatsapp_closing_regression.php`.

Ringkasan closing juga memuat **Transaksi Dibatalkan** (jumlah, total nilai, nomor transaksi, dan alasan) jika ada pembatalan pada hari closing sampai waktu closing. Waktu pembatalan menggunakan `canceled_at`, dengan `created_at` sebagai fallback data lama. Nilainya hanya informasi dan tidak dikurangkan lagi dari pendapatan PAID. **Pengeluaran** diambil dari snapshot `detail_pengeluaran` closing: rincian, total, dan pendapatan setelah pengeluaran. Kedua bagian dihilangkan jika kosong.

Batas pengiriman: satu paket per tanggal closing WIB, terdiri dari satu pesan ringkasan dan satu pesan stok. `closing_date` merupakan PRIMARY KEY dan klaim pesan hanya boleh dari status `pending`; `sending`/`sent` tidak diklaim ulang. Simpan ulang closing, sinkronisasi Android ulang, restart, maupun perubahan template tidak mereset payload/status yang sudah tercatat. Perubahan template ini berlaku untuk closing baru; tidak mengirim ulang laporan yang telah terkirim.

Closing ulang dari web maupun `closing-save` Android menghitung ulang data dan memperbarui baris `tb_closingan` pada tanggal yang sama dengan ID tetap. Retry request Android juga menghitung ulang penjualan terbaru; pengeluaran dengan request/payload yang sama tidak dimasukkan ulang. Snapshot WhatsApp tetap seperti pengiriman pertama. Terapkan `20260927_repeatable_mobile_closing.sql` agar tanggal dapat memiliki beberapa request sinkronisasi, sementara `request_id` tetap unik. Tes terisolasi: `docker exec lampp_web php /var/www/html/tests/reclosing_regression.php`.
