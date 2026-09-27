<?php
declare(strict_types=1);

$event = trim((string)($_GET['event'] ?? ''));
$source = trim((string)($_GET['source'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$where = ['DATE(l.created_at) = ?'];
$types = 's';
$params = [$date];
if ($event !== '') { $where[] = 'l.event_type = ?'; $types .= 's'; $params[] = $event; }
if ($source !== '') { $where[] = 'l.source = ?'; $types .= 's'; $params[] = $source; }
if ($search !== '') {
    $where[] = '(l.table_number LIKE ? OR l.client_order_id LIKE ? OR CAST(l.order_id AS CHAR) LIKE ?)';
    $types .= 'sss';
    $term = '%'.$search.'%';
    array_push($params, $term, $term, $term);
}
$sql = 'SELECT l.*,u.nama_user FROM transaction_audit_logs l LEFT JOIN tb_user u ON u.id_user=l.user_id WHERE '.implode(' AND ', $where).' ORDER BY l.created_at DESC,l.id DESC LIMIT 500';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$events = $conn->query('SELECT DISTINCT event_type FROM transaction_audit_logs ORDER BY event_type')->fetch_all(MYSQLI_ASSOC);
$sources = $conn->query('SELECT DISTINCT source FROM transaction_audit_logs ORDER BY source')->fetch_all(MYSQLI_ASSOC);

function auditEsc(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function auditLabel(string $value): string { return ucwords(str_replace('_', ' ', $value)); }
?>
<section class="content pt-3">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-clipboard-list mr-2"></i>Log Aktivitas Transaksi</h3></div>
      <div class="card-body">
        <form class="row align-items-end mb-3" method="get">
          <input type="hidden" name="id" value="activityLog">
          <div class="col-md-2"><label for="auditDate">Tanggal</label><input id="auditDate" class="form-control" type="date" name="date" value="<?= auditEsc($date) ?>"></div>
          <div class="col-md-3"><label for="auditEvent">Kegiatan</label><select id="auditEvent" class="form-control" name="event"><option value="">Semua kegiatan</option><?php foreach ($events as $row): ?><option value="<?= auditEsc($row['event_type']) ?>" <?= $event === $row['event_type'] ? 'selected' : '' ?>><?= auditEsc(auditLabel($row['event_type'])) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label for="auditSource">Sumber</label><select id="auditSource" class="form-control" name="source"><option value="">Semua sumber</option><?php foreach ($sources as $row): ?><option value="<?= auditEsc($row['source']) ?>" <?= $source === $row['source'] ? 'selected' : '' ?>><?= auditEsc(auditLabel($row['source'])) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label for="auditSearch">Order / meja / UUID</label><input id="auditSearch" class="form-control" name="search" value="<?= auditEsc($search) ?>"></div>
          <div class="col-md-2"><button class="btn btn-primary btn-block"><i class="fas fa-filter mr-1"></i>Tampilkan</button></div>
        </form>
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover">
            <thead><tr><th>Waktu Kejadian</th><th>Waktu Server</th><th>Kegiatan</th><th>Status</th><th>Sumber</th><th>Order</th><th>Meja</th><th>Pembayaran</th><th class="text-right">Nominal</th><th>Pengguna</th><th>Detail</th></tr></thead>
            <tbody><?php foreach ($rows as $row): ?><tr>
              <td class="text-nowrap"><?= auditEsc($row['client_event_at'] ?: '-') ?></td>
              <td class="text-nowrap"><?= auditEsc($row['created_at']) ?></td>
              <td><?= auditEsc(auditLabel($row['event_type'])) ?></td>
              <td><span class="badge badge-<?= $row['event_status'] === 'success' ? 'success' : ($row['event_status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= auditEsc($row['event_status']) ?></span></td>
              <td><?= auditEsc(auditLabel($row['source'])) ?></td>
              <td><div>#<?= auditEsc($row['order_id'] ?: '-') ?></div><small class="text-muted"><?= auditEsc($row['client_order_id'] ?: '-') ?></small></td>
              <td><?= auditEsc($row['table_number'] ?: '-') ?></td>
              <td><?= auditEsc(strtoupper((string)($row['payment_method'] ?: '-'))) ?></td>
              <td class="text-right text-nowrap"><?= $row['amount'] === null ? '-' : 'Rp '.number_format((int)$row['amount'], 0, ',', '.') ?></td>
              <td><?= auditEsc($row['nama_user'] ?: '-') ?></td>
              <td><?php if ($row['details']): ?><details><summary>Lihat</summary><pre class="small mb-0" style="max-width:420px;white-space:pre-wrap"><?= auditEsc(json_encode(json_decode($row['details'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></details><?php else: ?>-<?php endif; ?></td>
            </tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="11" class="text-center text-muted py-4">Belum ada log untuk filter ini.</td></tr><?php endif; ?></tbody>
          </table>
        </div>
        <small class="text-muted">Maksimal 500 kegiatan terbaru ditampilkan. Waktu menggunakan zona Asia/Jakarta.</small>
      </div>
    </div>
  </div>
</section>
