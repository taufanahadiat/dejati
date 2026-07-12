<?php
// Default: no filter
$filter_sql = "";
$selected_month = "";

if (isset($_GET['month']) && $_GET['month'] !== "") {
    $selected_month = $_GET['month']; // e.g., "11"
    $filter_sql = "WHERE MONTH(tanggal) = '$selected_month'";
}

$records = mysqli_query($conn, "SELECT * FROM tb_closingan $filter_sql ORDER BY tanggal DESC");

// Months
$months = [
    '01'=>'January','02'=>'February','03'=>'March','04'=>'April',
    '05'=>'May','06'=>'June','07'=>'July','08'=>'August',
    '09'=>'September','10'=>'October','11'=>'November','12'=>'December'
];
?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h4>Daily Report (Closingan)</h4>
    <!-- Month filter inside header -->
    <form method="GET" class="form-inline mb-0">
      <input type="hidden" name="id" value="dailyReport">
      <div class="input-group input-group-sm">
        <select name="month" class="form-control">
          <option value="">-- All Months --</option>
          <?php foreach($months as $num => $name): ?>
            <option value="<?= $num ?>" <?= ($selected_month === $num ? 'selected' : '') ?>>
              <?= $name ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="input-group-append">
          <button class="btn btn-primary">Filter</button>
          <a href="main.php?id=dailyReport" class="btn btn-secondary">Reset</a>
        </div>
      </div>
    </form>
  </div>

  <div class="card-body table-responsive">
    <table class="table table-bordered table-striped table-hover">
      <thead class="thead-dark">
        <tr>
          <th>Tanggal</th>
          <th>Total Penjualan</th>
          <th>Cash</th>
          <th>QRIS</th>
          <th>Kartu</th>
          <th>Cafe</th>
          <th>Carwash</th>
          <th>Total Pengeluaran</th>
          <th>Detail</th>
        </tr>
      </thead>

      <tbody>
        <?php 
        $modal_list = ""; // store modals here

        while ($row = mysqli_fetch_assoc($records)) :
          $detail = json_decode($row['detail_pengeluaran'], true) ?? [];

          // total pengeluaran
          $total_pengeluaran = 0;
          foreach ($detail as $d) {
            $total_pengeluaran += intval($d['total']);
          }
        ?>

          <tr>
            <td><?= $row['tanggal'] ?></td>
            <td>Rp <?= number_format($row['total_penjualan']) ?></td>
            <td>Rp <?= number_format($row['cash']) ?></td>
            <td>Rp <?= number_format($row['qris']) ?></td>
            <td>Rp <?= number_format($row['card']) ?></td>
            <td>Rp <?= number_format($row['cafe']) ?></td>
            <td>Rp <?= number_format($row['carwash']) ?></td>

            <td><span class="badge badge-danger">Rp <?= number_format($total_pengeluaran) ?></span></td>

            <td>
              <?php if ($detail) : ?>
                <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#detailModal<?= $row['id'] ?>">
                  Lihat
                </button>
              <?php else : ?>
                <span class="text-muted">-</span>
              <?php endif; ?>
            </td>
          </tr>

          <?php
          // BUILD MODAL HTML (OUTSIDE THE TABLE)
          $modal_list .= '
          <div class="modal fade" id="detailModal'.$row['id'].'">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Detail Pengeluaran ('.$row['tanggal'].')</h5>
                  <button class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                  <table class="table table-bordered">
                    <thead>
                      <tr>
                        <th>Keterangan</th>
                        <th>Total</th>
                        <th>Tanggal Input</th>
                      </tr>
                    </thead>
                    <tbody>';
          
          foreach ($detail as $d) {
            $modal_list .= '
              <tr>
                <td>'.htmlspecialchars($d['keterangan']).'</td>
                <td>Rp '.number_format($d['total']).'</td>
                <td>'.$d['created_at'].'</td>
              </tr>';
          }

          $modal_list .= '
                    </tbody>
                  </table>
                </div>

                <div class="modal-footer">
                  <button class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
              </div>
            </div>
          </div>';
          ?>

        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- PRINT ALL MODALS HERE -->
<?= $modal_list ?>
