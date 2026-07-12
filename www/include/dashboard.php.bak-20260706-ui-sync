<?php
$breadcrumb = [
  ['label' => 'Dashboard', 'link' => '#'], // Header
];
?>

<?php
require_once 'config/config.php';

$query = mysqli_query($conn, "SELECT * FROM tb_user");
$pengguna = $query->num_rows;

$tanggal = date("Y-m-d");


?>

<!-- Main content -->
<section class="content">
  <div class="callout callout-info">
    <h4>Hello <?= $_SESSION['nama_user'] . "!"; ?></h4>
    Anda login sebagai <?= $_SESSION['level']; ?>.
  </div>
  <!-- Info boxes -->
  <div class="row">
    <div class="col-md-3 col-sm-6 col-xs-12">
      <div class="info-box">
        <span class="info-box-icon bg-aqua"><i class="ion ion-person"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Pengguna</span>
          <span class="info-box-number"><?= $pengguna; ?></span>
        </div>
        <!-- /.info-box-content -->
      </div>
      <!-- /.info-box -->
    </div>
    <!-- /.col -->

    <!-- fix for small devices only -->
    <div class="clearfix visible-sm-block"></div>

    <!-- /.info-box-content -->
  </div>
  <!-- /.info-box -->
  </div>
  <!-- /.col -->
  </div>
  <!-- /.row -->
</section>

<!-- /.content -->