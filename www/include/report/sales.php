<?php
if (empty($_SESSION['loggedin'])) {http_response_code(403);exit('Forbidden');}
require_once __DIR__.'/../../config/combined_sales.php';
$breadcrumb=[['label'=>'Report'],['label'=>'Laporan Penjualan']];
$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$money=static fn($v)=>'Rp '.number_format((float)$v,0,',','.');
$start=(string)($_GET['start']??date('Y-m-d'));$end=(string)($_GET['end']??date('Y-m-d'));
try {$report=combinedSales($conn,$start,$end);}catch(InvalidArgumentException $e){echo '<div class="alert alert-warning">'.$escape($e->getMessage()).'</div>';return;}
$_SESSION['olsera_csrf']??=bin2hex(random_bytes(32));
$labels=['cafe'=>'Cafe','carwash'=>'Carwash','detailing'=>'Detailing'];
$states=['pending'=>'Menunggu pengambilan Excel','running'=>'Sedang mengambil dan mengimpor','succeeded'=>'Selesai','failed'=>'Gagal — akan dicoba ulang'];
$days=(int)round((strtotime($end)-strtotime($start))/86400)+1;
$complete=count(array_filter($report['jobs'],fn($j)=>$j['state']==='succeeded'));
?>
<style>@media (max-width:575.98px){.sales-report-page{padding-top:3.5rem!important}}</style>
<section class="content pt-3 sales-report-page"><div class="container-fluid">
<div class="card"><div class="card-header d-flex flex-wrap justify-content-between"><h4>Laporan Penjualan</h4>
<form method="GET" class="form-inline"><input type="hidden" name="id" value="salesReport">
<label for="sales-start" class="mr-2">Dari</label><input id="sales-start" type="date" name="start" value="<?= $escape($start) ?>" max="<?= date('Y-m-d') ?>" class="form-control mr-3" required>
<label for="sales-end" class="mr-2">Sampai</label><input id="sales-end" type="date" name="end" value="<?= $escape($end) ?>" max="<?= date('Y-m-d') ?>" class="form-control mr-3" required><button class="btn btn-primary">Tampilkan</button></form></div>
<div class="card-body">
<?php if ($complete<$days): ?><div class="alert alert-info">Data Olsera selesai untuk <?= $complete ?> dari <?= $days ?> hari. Total gabungan sementara hanya memasukkan data Olsera yang sudah berhasil diimpor.</div><?php endif; ?>
<div class="row">
<?php foreach (['Dejati POS'=>(float)$report['dejati']['total'],'Olsera POS tercatat'=>$report['olsera']['total'],'Total gabungan'=>(float)$report['dejati']['total']+$report['olsera']['total']] as $label=>$amount): ?>
<div class="col-md-4"><div class="small-box bg-light p-3"><div class="inner"><p><?= $escape($label) ?></p><h3 style="font-size:1.5rem"><?= $money($amount) ?></h3></div></div></div>
<?php endforeach; ?></div>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Usaha</th><th>Dejati POS</th><th>Olsera POS</th><th>Gabungan</th></tr></thead><tbody>
<?php foreach($labels as $key=>$label): $local=$report['divisions'][$key]['revenue']; ?><tr><td><?= $label ?></td><td><?= $money($local) ?></td><td><?= $money($report['olsera'][$key]) ?></td><td><strong><?= $money($local+$report['olsera'][$key]) ?></strong></td></tr><?php endforeach; ?>
<?php $unclassified=(float)$report['dejati']['total']-array_sum(array_column($report['divisions'],'revenue'));if($unclassified!=0): ?><tr><td>Dejati belum terklasifikasi</td><td><?= $money($unclassified) ?></td><td>—</td><td><?= $money($unclassified) ?></td></tr><?php endif; ?>
</tbody></table></div><p class="text-muted">Dejati: transaksi lunas, dengan diskon dan penyesuaian transaksi dialokasikan per usaha. Olsera: kolom total sales amount pada Excel Penjualan berdasarkan SKU. Transaksi kedua POS dijumlahkan sebagai sumber terpisah.</p>
</div></div>
<div class="card"><div class="card-header"><h5>Status Sinkronisasi Olsera</h5></div><div class="card-body">
<p>Closing hari ini dari Android atau web memulai pengambilan Excel. Laporan WhatsApp menunggu impor serta pengurangan stok selesai.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Tanggal</th><th>Status</th><th>Diambil (WIB)</th><th>Baris Excel</th><th>Keterangan</th><th></th></tr></thead><tbody>
<?php foreach($report['jobs'] as $job): ?><tr><td><?= $escape($job['sales_date']) ?></td><td><?= $escape($states[$job['state']]) ?></td><td><?= $escape($job['fetched_at']??'—') ?></td><td><?= $escape($job['row_count']??'—') ?></td><td><?= $escape($job['last_error']??'') ?></td><td>
<?php if (($_SESSION['level']??'')==='Administrator'&&$job['state']==='failed'): ?><form method="POST" action="api/olsera-retry"><input type="hidden" name="date" value="<?= $escape($job['sales_date']) ?>"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['olsera_csrf']) ?>"><button class="btn btn-sm btn-outline-primary">Coba lagi</button></form><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$report['jobs']): ?><tr><td colspan="6">Belum ada pekerjaan Olsera pada periode ini.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
<div class="card"><div class="card-header"><h5>Rincian Penjualan Olsera</h5></div><div class="card-body table-responsive"><table class="table table-striped"><thead><tr><th>Tanggal</th><th>Usaha</th><th>Produk</th><th>Varian</th><th>Terjual</th><th>Penjualan</th></tr></thead><tbody>
<?php foreach($report['items'] as $r): ?><tr><td><?= $escape($r['sales_date']) ?></td><td><?= $labels[$r['business']] ?></td><td><?= $escape($r['product_name']) ?></td><td><?= $escape($r['variant']) ?></td><td><?= number_format((float)$r['quantity'],3,',','.') ?></td><td><?= $money($r['total_sales']) ?></td></tr><?php endforeach; ?>
<?php if (!$report['items']): ?><tr><td colspan="6">Belum ada produk Olsera tercatat.</td></tr><?php endif; ?></tbody></table></div></div>
</div></section>
