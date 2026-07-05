<?php
$isEdit = isset($_GET['id_produk']);
$product = null;

if ($isEdit) {
    $id_produk = (int)$_GET['id_produk'];
    $stmt = $conn->prepare("SELECT * FROM tb_datacarwash WHERE id_produk = ?");
    $stmt->bind_param("i", $id_produk);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        ?>
        <section class="content">
          <div class="alert alert-danger mt-2">Data produk carwash tidak ditemukan.</div>
          <a href="main.php?id=carwashData" class="btn btn-secondary">Kembali</a>
        </section>
        <?php
        return;
    }
}

$breadcrumb = [
  ['label' => 'Daftar Carwash', 'link' => '#'],
  ['label' => 'Data', 'link' => 'main.php?id=carwashData'],
  ['label' => $isEdit ? 'Edit' : 'Tambah', 'link' => '', 'active' => true]
];
?>
<section class="content">
  <?php if (isset($_SESSION['error'])) : ?>
    <?php if ($_SESSION['error']) : ?>
      <div id="errorMessage" class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i>&nbsp;<?= htmlspecialchars($_SESSION['error']); ?>
      </div>
    <?php endif; ?>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>

  <div class="row">
    <div class="col-12 mt-2">
      <div class="card card-dark card-outline">
        <div class="card-header">
          <h3 class="card-title"><?= $isEdit ? 'Edit Produk Carwash' : 'Tambah Produk Carwash'; ?></h3>
        </div>

        <form method="post" action="include/carwash/<?= $isEdit ? 'paket_edit_process.php' : 'paket_add_process.php'; ?>">
          <div class="card-body">
            <?php if ($isEdit): ?>
              <input type="hidden" name="id_produk" value="<?= (int)$product['id_produk']; ?>">
            <?php endif; ?>

            <div class="form-group">
              <label for="produk">Nama Produk</label>
              <input type="text" class="form-control" id="produk" placeholder="Nama Produk" name="produk" value="<?= htmlspecialchars($product['produk'] ?? ''); ?>" required autofocus>
            </div>

            <div class="form-group">
              <label for="biaya">Harga Produk</label>
              <input type="text" class="form-control h_paket" id="biaya" placeholder="Harga Produk" name="biaya" value="<?= isset($product['biaya']) ? number_format((float)$product['biaya'], 0, ',', '.') : ''; ?>" required>
            </div>
          </div>

          <div class="card-footer">
            <button type="submit" name="simpan" class="btn btn-success">Simpan Data</button>
            <a href="main.php?id=carwashData" class="btn btn-secondary">Kembali</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

