<?php
$breadcrumb = [
  ['label' => 'Dashboard', 'link' => '#'],
];

require_once 'config/config.php';

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$yearStart = date('Y-01-01');
$yearEnd = date('Y-12-31');

function dashMoney($value)
{
  return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

function dashNumber($value)
{
  return number_format((float)$value, 0, ',', '.');
}

function dashPercent($current, $previous)
{
  $current = (float)$current;
  $previous = (float)$previous;

  if ($previous <= 0) {
    return $current > 0 ? 100 : 0;
  }

  return round((($current - $previous) / $previous) * 100, 1);
}

function dashScalar($conn, $sql, $types = '', $params = [])
{
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    return 0;
  }

  if ($types !== '' && $params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
  }

  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $row = $result ? mysqli_fetch_assoc($result) : null;
  mysqli_stmt_close($stmt);

  return (float)($row['value'] ?? 0);
}

function dashRows($conn, $sql, $types = '', $params = [])
{
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    return [];
  }

  if ($types !== '' && $params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
  }

  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $rows = [];

  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $rows[] = $row;
    }
  }

  mysqli_stmt_close($stmt);
  return $rows;
}

function dashOrdersSummary($conn, $start, $end)
{
  $row = dashRows(
    $conn,
    "SELECT COUNT(*) AS trx_count,
            COALESCE(SUM(total_amount), 0) AS revenue,
            COALESCE(AVG(NULLIF(total_amount, 0)), 0) AS avg_order
     FROM orders
     WHERE created_at BETWEEN ? AND ?",
    'ss',
    [$start . ' 00:00:00', $end . ' 23:59:59']
  );

  return $row[0] ?? ['trx_count' => 0, 'revenue' => 0, 'avg_order' => 0];
}

function dashTrendRows($conn, $range)
{
  $labels = [];
  $revenue = [];
  $transactions = [];
  $format = '%Y-%m-%d';
  $labelFormat = 'd M';
  $start = date('Y-m-d') . ' 00:00:00';
  $end = date('Y-m-d') . ' 23:59:59';

  if ($range === 'today') {
    $format = '%H:00';
    $labelFormat = null;
    $start = date('Y-m-d') . ' 00:00:00';
    $end = date('Y-m-d') . ' 23:59:59';
    for ($hour = 0; $hour < 24; $hour++) {
      $labels[] = str_pad((string)$hour, 2, '0', STR_PAD_LEFT) . ':00';
    }
  } elseif ($range === 'week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
    $start = $startDate . ' 00:00:00';
    $end = $endDate . ' 23:59:59';
    for ($date = strtotime($startDate); $date <= strtotime($endDate); $date = strtotime('+1 day', $date)) {
      $labels[] = date($labelFormat, $date);
    }
  } elseif ($range === 'month') {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
    $start = $startDate . ' 00:00:00';
    $end = $endDate . ' 23:59:59';
    for ($date = strtotime($startDate); $date <= strtotime($endDate); $date = strtotime('+1 day', $date)) {
      $labels[] = date($labelFormat, $date);
    }
  } elseif ($range === 'year') {
    $format = '%Y-%m';
    $start = date('Y-01-01') . ' 00:00:00';
    $end = date('Y-12-31') . ' 23:59:59';
    for ($month = 1; $month <= 12; $month++) {
      $labels[] = date('M Y', strtotime(date('Y') . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-01'));
    }
  }

  foreach ($labels as $label) {
    $revenue[$label] = 0;
    $transactions[$label] = 0;
  }

  $rows = dashRows(
    $conn,
    "SELECT DATE_FORMAT(created_at, ?) AS label_key,
            COALESCE(SUM(total_amount), 0) AS revenue,
            COUNT(*) AS transactions
     FROM orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY label_key
     ORDER BY MIN(created_at)",
    'sss',
    [$format, $start, $end]
  );

  foreach ($rows as $row) {
    $label = (string)$row['label_key'];
    if ($range === 'week' || $range === 'month') {
      $label = date($labelFormat, strtotime($label));
    } elseif ($range === 'year') {
      $label = date('M Y', strtotime($label . '-01'));
    }

    if (array_key_exists($label, $revenue)) {
      $revenue[$label] = (int)$row['revenue'];
      $transactions[$label] = (int)$row['transactions'];
    }
  }

  return [
    'labels' => array_values($labels),
    'revenue' => array_values($revenue),
    'transactions' => array_values($transactions),
  ];
}

$pengguna = (int)dashScalar($conn, "SELECT COUNT(*) AS value FROM tb_user");
$activeUsers = (int)dashScalar($conn, "SELECT COUNT(*) AS value FROM tb_user WHERE status = 'Aktif'");
$productCount = (int)dashScalar($conn, "SELECT COUNT(*) AS value FROM tb_datacafe") + (int)dashScalar($conn, "SELECT COUNT(*) AS value FROM tb_datacarwash");

$todaySummary = dashOrdersSummary($conn, $today, $today);
$yesterdaySummary = dashOrdersSummary($conn, $yesterday, $yesterday);
$weekSummary = dashOrdersSummary($conn, $weekStart, $weekEnd);
$monthSummary = dashOrdersSummary($conn, $monthStart, $monthEnd);
$yearSummary = dashOrdersSummary($conn, $yearStart, $yearEnd);

$todayRevenue = (int)$todaySummary['revenue'];
$todayTransactions = (int)$todaySummary['trx_count'];
$todayAverage = (int)$todaySummary['avg_order'];
$yesterdayRevenue = (int)$yesterdaySummary['revenue'];
$revenueChange = dashPercent($todayRevenue, $yesterdayRevenue);
$transactionChange = dashPercent($todayTransactions, (int)$yesterdaySummary['trx_count']);

$openBillCount = (int)dashScalar($conn, "SELECT COUNT(*) AS value FROM orders WHERE DATE(created_at) = ? AND paid_amount = 0", 's', [$today]);
$expenseToday = (int)dashScalar($conn, "SELECT COALESCE(SUM(total), 0) AS value FROM pengeluaran WHERE DATE(created_at) = ?", 's', [$today]);
$netToday = $todayRevenue - $expenseToday;

$cafeToday = (int)dashScalar(
  $conn,
  "SELECT COALESCE(SUM(oi.total), 0) AS value
   FROM order_items oi
   INNER JOIN orders o ON o.id = oi.id_tr
   WHERE DATE(o.created_at) = ?",
  's',
  [$today]
);
$carwashToday = (int)dashScalar(
  $conn,
  "SELECT COALESCE(SUM(oc.total), 0) AS value
   FROM order_carwash oc
   INNER JOIN orders o ON o.id = oc.id_tr
   WHERE DATE(o.created_at) = ?",
  's',
  [$today]
);

$paymentRows = dashRows(
  $conn,
  "SELECT payment_method, COUNT(*) AS trx_count, COALESCE(SUM(total_amount), 0) AS total
   FROM orders
   WHERE DATE(created_at) = ?
   GROUP BY payment_method
   ORDER BY total DESC",
  's',
  [$today]
);
$paymentMap = [
  'cash' => ['label' => 'Cash', 'total' => 0, 'trx_count' => 0, 'class' => 'success'],
  'qris' => ['label' => 'QRIS', 'total' => 0, 'trx_count' => 0, 'class' => 'info'],
  'credit_card' => ['label' => 'Kartu', 'total' => 0, 'trx_count' => 0, 'class' => 'warning'],
];
foreach ($paymentRows as $row) {
  $method = (string)$row['payment_method'];
  if (!isset($paymentMap[$method])) {
    $paymentMap[$method] = ['label' => ucfirst(str_replace('_', ' ', $method)), 'total' => 0, 'trx_count' => 0, 'class' => 'secondary'];
  }
  $paymentMap[$method]['total'] = (int)$row['total'];
  $paymentMap[$method]['trx_count'] = (int)$row['trx_count'];
}

$topProducts = dashRows(
  $conn,
  "SELECT item_name, SUM(quantity) AS qty, SUM(total) AS total, source
   FROM (
     SELECT oi.item_name, oi.quantity, oi.total, 'Cafe' AS source
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.id_tr
     WHERE o.created_at BETWEEN ? AND ?
     UNION ALL
     SELECT oc.item_name, oc.qty AS quantity, oc.total, 'Carwash' AS source
     FROM order_carwash oc
     INNER JOIN orders o ON o.id = oc.id_tr
     WHERE o.created_at BETWEEN ? AND ?
   ) sold_items
   GROUP BY item_name, source
   ORDER BY qty DESC, total DESC
   LIMIT 8",
  'ssss',
  [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59', $monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']
);

$recentOrders = dashRows(
  $conn,
  "SELECT id, table_number, payment_method, total_amount, paid_amount, created_at
   FROM orders
   ORDER BY created_at DESC
   LIMIT 8"
);

$hourlyPeak = dashRows(
  $conn,
  "SELECT DATE_FORMAT(created_at, '%H:00') AS hour_label, COUNT(*) AS trx_count, COALESCE(SUM(total_amount), 0) AS total
   FROM orders
   WHERE DATE(created_at) = ?
   GROUP BY hour_label
   ORDER BY trx_count DESC, total DESC
   LIMIT 1",
  's',
  [$today]
);
$peakHour = $hourlyPeak[0] ?? ['hour_label' => '-', 'trx_count' => 0, 'total' => 0];

$trendData = [
  'today' => dashTrendRows($conn, 'today'),
  'week' => dashTrendRows($conn, 'week'),
  'month' => dashTrendRows($conn, 'month'),
  'year' => dashTrendRows($conn, 'year'),
];

$sourceChart = [
  'labels' => ['Cafe', 'Carwash'],
  'data' => [$cafeToday, $carwashToday],
];
$paymentChart = [
  'labels' => array_values(array_map(fn($row) => $row['label'], $paymentMap)),
  'data' => array_values(array_map(fn($row) => $row['total'], $paymentMap)),
];
?>

<style>
  .dashboard-hero {
    background: linear-gradient(135deg, #1f2937 0%, #2f4f4f 52%, #8c6a43 100%);
    border-radius: 8px;
    color: #fff;
    padding: 1.25rem;
    box-shadow: var(--theme-shadow, 0 0.5rem 1rem rgba(0, 0, 0, 0.08));
  }

  .dashboard-hero h4,
  .dashboard-hero p {
    margin: 0;
  }

  .dash-card {
    border: 1px solid var(--theme-border, #dee2e6);
    border-radius: 8px;
    box-shadow: none;
  }

  .dash-stat {
    min-height: 122px;
  }

  .dash-stat .card-body {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
  }

  .dash-stat-label {
    color: var(--theme-text-muted, #6c757d);
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
  }

  .dash-stat-value {
    color: var(--theme-text, #212529);
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1.15;
  }

  .dash-stat-note {
    color: var(--theme-text-muted, #6c757d);
    font-size: 0.86rem;
    margin-top: 0.35rem;
  }

  .dash-icon {
    align-items: center;
    border-radius: 8px;
    color: #fff;
    display: inline-flex;
    flex: 0 0 44px;
    height: 44px;
    justify-content: center;
    width: 44px;
  }

  .dash-chart-wrap {
    height: 330px;
    position: relative;
  }

  .dash-mini-chart {
    height: 245px;
    position: relative;
  }

  .dash-table td,
  .dash-table th {
    vertical-align: middle;
  }

  .dash-empty {
    color: var(--theme-text-muted, #6c757d);
    padding: 2rem 1rem;
    text-align: center;
  }

  @media (max-width: 575.98px) {
    .dashboard-hero {
      padding: 1rem;
    }

    .dash-stat-value {
      font-size: 1.3rem;
    }

    .dash-chart-wrap {
      height: 285px;
    }
  }
</style>

<section class="content pt-3">
  <div class="container-fluid">
    <div class="dashboard-hero mb-3">
      <div class="row align-items-center">
        <div class="col-lg-8">
          <h4>Halo, <?= htmlspecialchars($_SESSION['nama_user'] ?? 'User'); ?>!</h4>
          <p class="mt-1">Ringkasan operasional De'Jati hari ini, <?= date('d M Y'); ?>. Anda login sebagai <?= htmlspecialchars($_SESSION['level'] ?? '-'); ?>.</p>
        </div>
        <div class="col-lg-4 mt-3 mt-lg-0 text-lg-right">
          <a href="main.php?id=transaksi" class="btn btn-light btn-sm">
            <i class="fas fa-cash-register"></i> Buka Transaksi
          </a>
          <a href="main.php?id=report&range=today&start=<?= $today; ?>&end=<?= $today; ?>" class="btn btn-outline-light btn-sm">
            <i class="fas fa-history"></i> Lihat Report
          </a>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-xl-3 col-md-6">
        <div class="card dash-card dash-stat">
          <div class="card-body">
            <div>
              <div class="dash-stat-label">Omzet Hari Ini</div>
              <div class="dash-stat-value"><?= dashMoney($todayRevenue); ?></div>
              <div class="dash-stat-note">
                <?= $revenueChange >= 0 ? '+' : ''; ?><?= $revenueChange; ?>% vs kemarin
              </div>
            </div>
            <span class="dash-icon bg-success"><i class="fas fa-wallet"></i></span>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="card dash-card dash-stat">
          <div class="card-body">
            <div>
              <div class="dash-stat-label">Total Transaksi Hari Ini</div>
              <div class="dash-stat-value"><?= dashNumber($todayTransactions); ?></div>
              <div class="dash-stat-note">
                <?= $transactionChange >= 0 ? '+' : ''; ?><?= $transactionChange; ?>% vs kemarin
              </div>
            </div>
            <span class="dash-icon bg-info"><i class="fas fa-receipt"></i></span>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="card dash-card dash-stat">
          <div class="card-body">
            <div>
              <div class="dash-stat-label">Rata-rata Belanja</div>
              <div class="dash-stat-value"><?= dashMoney($todayAverage); ?></div>
              <div class="dash-stat-note"><?= dashNumber($openBillCount); ?> open bill belum final</div>
            </div>
            <span class="dash-icon bg-warning"><i class="fas fa-chart-line"></i></span>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="card dash-card dash-stat">
          <div class="card-body">
            <div>
              <div class="dash-stat-label">Nett Hari Ini</div>
              <div class="dash-stat-value"><?= dashMoney($netToday); ?></div>
              <div class="dash-stat-note">Pengeluaran <?= dashMoney($expenseToday); ?></div>
            </div>
            <span class="dash-icon bg-primary"><i class="fas fa-balance-scale"></i></span>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-xl-8">
        <div class="card dash-card">
          <div class="card-header">
            <div class="row align-items-center">
              <div class="col-md-7">
                <h3 class="card-title mb-0"><i class="fas fa-chart-area mr-1"></i> Trend Histori Transaksi</h3>
              </div>
              <div class="col-md-5 mt-2 mt-md-0">
                <select id="dashboardTrendRange" class="form-control form-control-sm">
                  <option value="today">Hari Ini per Jam</option>
                  <option value="week">Minggu Ini</option>
                  <option value="month" selected>Bulan Ini</option>
                  <option value="year">Tahun Ini</option>
                </select>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="dash-chart-wrap">
              <canvas id="dashboardTrendChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-4">
        <div class="card dash-card">
          <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-layer-group mr-1"></i> Ringkasan Periode</h3>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped dash-table mb-0">
              <thead>
                <tr>
                  <th>Periode</th>
                  <th class="text-right">Transaksi</th>
                  <th class="text-right">Omzet</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Hari Ini</td>
                  <td class="text-right"><?= dashNumber($todaySummary['trx_count']); ?></td>
                  <td class="text-right"><?= dashMoney($todaySummary['revenue']); ?></td>
                </tr>
                <tr>
                  <td>Minggu Ini</td>
                  <td class="text-right"><?= dashNumber($weekSummary['trx_count']); ?></td>
                  <td class="text-right"><?= dashMoney($weekSummary['revenue']); ?></td>
                </tr>
                <tr>
                  <td>Bulan Ini</td>
                  <td class="text-right"><?= dashNumber($monthSummary['trx_count']); ?></td>
                  <td class="text-right"><?= dashMoney($monthSummary['revenue']); ?></td>
                </tr>
                <tr>
                  <td>Tahun Ini</td>
                  <td class="text-right"><?= dashNumber($yearSummary['trx_count']); ?></td>
                  <td class="text-right"><?= dashMoney($yearSummary['revenue']); ?></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card dash-card">
          <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-clock mr-1"></i> Jam Tersibuk Hari Ini</h3>
          </div>
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="dash-stat-value"><?= htmlspecialchars($peakHour['hour_label']); ?></div>
                <div class="dash-stat-note"><?= dashNumber($peakHour['trx_count']); ?> transaksi, <?= dashMoney($peakHour['total']); ?></div>
              </div>
              <span class="dash-icon bg-secondary"><i class="fas fa-stopwatch"></i></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-4">
        <div class="card dash-card">
          <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-credit-card mr-1"></i> Metode Pembayaran Hari Ini</h3>
          </div>
          <div class="card-body">
            <div class="dash-mini-chart mb-3">
              <canvas id="dashboardPaymentChart"></canvas>
            </div>
            <?php foreach ($paymentMap as $payment): ?>
              <div class="mb-2">
                <div class="d-flex justify-content-between">
                  <span><?= htmlspecialchars($payment['label']); ?> <small class="text-muted">(<?= dashNumber($payment['trx_count']); ?> trx)</small></span>
                  <strong><?= dashMoney($payment['total']); ?></strong>
                </div>
                <div class="progress progress-sm">
                  <?php $percent = $todayRevenue > 0 ? min(100, round(((int)$payment['total'] / $todayRevenue) * 100)) : 0; ?>
                  <div class="progress-bar bg-<?= htmlspecialchars($payment['class']); ?>" style="width: <?= $percent; ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card dash-card">
          <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-store mr-1"></i> Cafe vs Carwash Hari Ini</h3>
          </div>
          <div class="card-body">
            <div class="dash-mini-chart mb-3">
              <canvas id="dashboardSourceChart"></canvas>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span>Cafe</span>
              <strong><?= dashMoney($cafeToday); ?></strong>
            </div>
            <div class="d-flex justify-content-between">
              <span>Carwash</span>
              <strong><?= dashMoney($carwashToday); ?></strong>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card dash-card">
          <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-info-circle mr-1"></i> Info Operasional</h3>
          </div>
          <div class="card-body">
            <div class="info-box bg-light">
              <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Pengguna Aktif</span>
                <span class="info-box-number"><?= dashNumber($activeUsers); ?> / <?= dashNumber($pengguna); ?></span>
              </div>
            </div>
            <div class="info-box bg-light">
              <span class="info-box-icon bg-success"><i class="fas fa-mug-hot"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Produk Terdaftar</span>
                <span class="info-box-number"><?= dashNumber($productCount); ?></span>
              </div>
            </div>
            <div class="info-box bg-light mb-0">
              <span class="info-box-icon bg-warning"><i class="fas fa-file-invoice"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Open Bill Hari Ini</span>
                <span class="info-box-number"><?= dashNumber($openBillCount); ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-xl-6">
        <div class="card dash-card">
          <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-star mr-1"></i> Produk Terlaris Bulan Ini</h3>
          </div>
          <div class="card-body p-0">
            <?php if ($topProducts): ?>
              <div class="table-responsive">
                <table class="table table-striped dash-table mb-0">
                  <thead>
                    <tr>
                      <th>Produk</th>
                      <th>Kategori</th>
                      <th class="text-right">Qty</th>
                      <th class="text-right">Omzet</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($topProducts as $product): ?>
                      <tr>
                        <td><?= htmlspecialchars($product['item_name']); ?></td>
                        <td><span class="badge badge-light border"><?= htmlspecialchars($product['source']); ?></span></td>
                        <td class="text-right"><?= dashNumber($product['qty']); ?></td>
                        <td class="text-right"><?= dashMoney($product['total']); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="dash-empty">Belum ada penjualan produk bulan ini.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-xl-6">
        <div class="card dash-card">
          <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-list mr-1"></i> Transaksi Terbaru</h3>
          </div>
          <div class="card-body p-0">
            <?php if ($recentOrders): ?>
              <div class="table-responsive">
                <table class="table table-striped dash-table mb-0">
                  <thead>
                    <tr>
                      <th>Waktu</th>
                      <th>Meja</th>
                      <th>Metode</th>
                      <th>Status</th>
                      <th class="text-right">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                      <tr>
                        <td><?= date('d M H:i', strtotime($order['created_at'])); ?></td>
                        <td><?= htmlspecialchars($order['table_number'] ?: '-'); ?></td>
                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $order['payment_method']))); ?></td>
                        <td>
                          <?php if ((int)$order['paid_amount'] === 0): ?>
                            <span class="badge badge-warning">Open Bill</span>
                          <?php else: ?>
                            <span class="badge badge-success">Paid</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-right"><?= dashMoney($order['total_amount']); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="dash-empty">Belum ada transaksi.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="plugins/chart.js/Chart.bundle.min.js"></script>
<script>
  (function() {
    const trendData = <?= json_encode($trendData, JSON_NUMERIC_CHECK); ?>;
    const sourceChart = <?= json_encode($sourceChart, JSON_NUMERIC_CHECK); ?>;
    const paymentChart = <?= json_encode($paymentChart, JSON_NUMERIC_CHECK); ?>;
    const money = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
    const trendCanvas = document.getElementById('dashboardTrendChart');
    const sourceCanvas = document.getElementById('dashboardSourceChart');
    const paymentCanvas = document.getElementById('dashboardPaymentChart');

    if (!window.Chart || !trendCanvas) return;

    const trendChart = new Chart(trendCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: trendData.month.labels,
        datasets: [{
          label: 'Omzet',
          data: trendData.month.revenue,
          borderColor: '#28a745',
          backgroundColor: 'rgba(40, 167, 69, 0.14)',
          borderWidth: 2,
          pointRadius: 2,
          yAxisID: 'yRevenue'
        }, {
          label: 'Transaksi',
          data: trendData.month.transactions,
          borderColor: '#17a2b8',
          backgroundColor: 'rgba(23, 162, 184, 0.12)',
          borderWidth: 2,
          pointRadius: 2,
          yAxisID: 'yTransactions'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: { display: true },
        tooltips: {
          mode: 'index',
          intersect: false,
          callbacks: {
            label: function(item, data) {
              const label = data.datasets[item.datasetIndex].label || '';
              return label === 'Omzet' ? label + ': ' + money(item.yLabel) : label + ': ' + item.yLabel;
            }
          }
        },
        scales: {
          yAxes: [{
            id: 'yRevenue',
            position: 'left',
            ticks: {
              beginAtZero: true,
              callback: value => money(value)
            }
          }, {
            id: 'yTransactions',
            position: 'right',
            ticks: {
              beginAtZero: true,
              precision: 0
            },
            gridLines: { drawOnChartArea: false }
          }],
          xAxes: [{
            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 }
          }]
        }
      }
    });

    const rangeSelect = document.getElementById('dashboardTrendRange');
    rangeSelect.addEventListener('change', function() {
      const selected = trendData[this.value] || trendData.month;
      trendChart.data.labels = selected.labels;
      trendChart.data.datasets[0].data = selected.revenue;
      trendChart.data.datasets[1].data = selected.transactions;
      trendChart.update();
    });

    if (sourceCanvas) {
      new Chart(sourceCanvas.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: sourceChart.labels,
          datasets: [{
            data: sourceChart.data,
            backgroundColor: ['#20c997', '#6f42c1']
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          legend: { position: 'bottom' },
          tooltips: {
            callbacks: {
              label: (item, data) => data.labels[item.index] + ': ' + money(data.datasets[0].data[item.index])
            }
          }
        }
      });
    }

    if (paymentCanvas) {
      new Chart(paymentCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: paymentChart.labels,
          datasets: [{
            data: paymentChart.data,
            backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#6c757d']
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          legend: { display: false },
          tooltips: {
            callbacks: {
              label: item => money(item.yLabel)
            }
          },
          scales: {
            yAxes: [{
              ticks: {
                beginAtZero: true,
                callback: value => money(value)
              }
            }]
          }
        }
      });
    }
  })();
</script>
