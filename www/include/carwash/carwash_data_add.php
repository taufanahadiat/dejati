<?php
$breadcrumb = [
  ['label' => 'Daftar Carwash', 'link' => '#'],
  ['label' => 'Data', 'link' => '', 'active' => true]
];
?>
<!-- Main content -->
<section class="content">

  <?php if (isset($_SESSION['error'])) : ?>
    <p>
      <?php
      $error = $_SESSION['error'];
      if ($error) {
      ?>
    <div id="errorMessage" class="alert alert-danger" role="alert">
      <i class="fa fa-exclamation-circle"></i>&nbsp;<?= $error; ?>
    </div>
  <?php
      }
  ?>
  </p>
<?php
    unset($_SESSION['error']);
  endif;
?>

<!-- Form Card -->
<div class="row">
  <div class="col-12 mt-2">
    <div class="card card-dark card-outline">
      <div class="card-header">
        <h3 class="card-title">Form Tambah Data Paket</h3>
      </div>

      <form method="post" action="paket_add_process.php">
        <div class="card-body">
          <div class="form-group">
            <label for="namaPaket">Nama Paket</label>
            <input type="text" class="form-control" id="namaPaket" placeholder="Nama Paket" name="paket" required autofocus>
          </div>

          <div class="form-group">
            <label for="h_paket">Harga Paket</label>
            <input type="text" class="form-control h_paket" id="h_paket" placeholder="Harga Paket" name="biaya" required>
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
</div><!-- /.container-fluid -->
</section>