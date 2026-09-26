<?php
require_once __DIR__ . '/product_helpers.php';
$table = service_product_table($serviceType);
$label = ucfirst($serviceType);
$breadcrumb = [
  ['label' => 'Daftar Produk ' . $label, 'link' => '#'],
  ['label' => 'Data', 'link' => '', 'active' => true]
];
?>

<section class="content">
  <?php if (isset($_SESSION['error'])): ?>
    <?php if ($_SESSION['error']): ?>
      <div id="errorMessage" class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i>&nbsp;<?= htmlspecialchars($_SESSION['error']); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
    <?php endif; ?>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['success'])): ?>
    <?php if ($_SESSION['success']): ?>
      <div id="successMessage" class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i>&nbsp;<?= htmlspecialchars($_SESSION['success']); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
    <?php endif; ?>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <div class="row">
    <div class="col-12 mt-2">
      <div class="card card-dark card-outline">
        <div class="card-header">
          <h3 class="card-title">Data <?= $label ?></h3>
          <div class="card-tools">
            <a class="btn btn-primary btn-sm" href="/main?id=<?= $serviceType ?>Data_add">
              <i class="fas fa-plus"></i> Tambah Data Produk
            </a>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover" width="100%">
              <thead class="thead-light">
                <tr>
                  <th>No</th>
                  <th>Gambar</th>
                  <th>Produk</th>
                  <th>Varian</th>
                  <th>Harga Produk</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $query = mysqli_query($conn, "SELECT * FROM $table ORDER BY produk ASC");
                if (mysqli_num_rows($query) > 0) {
                  $no = 1;
                  while ($row = mysqli_fetch_array($query)) {
                ?>
                    <tr>
                      <td><?= $no++; ?></td>
                      <td><img src="<?= htmlspecialchars(cafe_product_thumb_url($row['foto'] ?? ''), ENT_QUOTES) ?>" alt="Foto produk" loading="lazy" style="width:60px;height:60px;object-fit:cover"></td>
                      <td><?= htmlspecialchars($row['produk']); ?></td>
                      <td><?= !empty($row['variant']) ? 'Ya' : 'Tidak' ?></td>
                      <td>
                        <?php if (!empty($row['variant'])): ?>
                          <?php $prices = explode(';', (string)$row['biaya_var']); ?>
                          <?php foreach (explode(';', (string)$row['nama_var']) as $i => $name): ?>
                            <div><?= htmlspecialchars($name) ?>: Rp <?= number_format((int)($prices[$i] ?? 0), 0, ',', '.') ?></div>
                          <?php endforeach; ?>
                        <?php else: ?>
                          Rp <?= number_format((int)$row['biaya'], 0, ',', '.') ?>
                        <?php endif; ?>
                      </td>
                      <td>
                        <a href="/main?id=<?= $serviceType ?>Data_edit&id_produk=<?= (int)$row['id_produk']; ?>" class="btn btn-success btn-sm">
                          <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="/include/data/<?= $serviceType ?>/paket_delete?id_produk=<?= (int)$row['id_produk']; ?>" onclick="return confirmDialog();" class="btn btn-danger btn-sm">
                          <i class="fas fa-trash"></i> Delete
                        </a>
                      </td>
                    </tr>
                <?php
                  }
                } else {
                  echo '<tr><td colspan="6" class="text-center"><b>Tidak ada data yang tersedia.</b></td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<script>
  function confirmDialog() {
    return confirm("Data yang dihapus tidak akan bisa dikembalikan. Apakah Anda yakin akan menghapus data ini?");
  }
</script>
