<?php
require_once __DIR__ . '/../../config/config.php';

$id = (int) ($_GET['id'] ?? 0);
$orderResult = $id > 0 ? mysqli_query($conn, "SELECT *, COALESCE(NULLIF(status_order, ''), CASE WHEN paid_amount = 0 THEN 'OPEN BILL' ELSE 'PAID' END) AS status_order FROM orders WHERE id=$id LIMIT 1") : false;
$order = $orderResult ? mysqli_fetch_assoc($orderResult) : null;
if (!$order) {
    http_response_code(404);
    echo '<div class="modal-body"><div class="alert alert-danger mb-0">Transaksi tidak ditemukan.</div></div>';
    exit;
}

$escape = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$money = static function ($value) { return 'Rp ' . number_format((float) $value, 0, ',', '.'); };
$groups = [];
$subtotal = 0;
foreach (['Cafe' => 'order_items', 'Carwash' => 'order_carwash', 'Detailing' => 'order_detailing'] as $label => $tableName) {
    $result = mysqli_query($conn, "SELECT * FROM $tableName WHERE id_tr=$id");
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    $amount = array_sum(array_column($rows, 'total'));
    $groups[$label] = ['items' => $rows, 'subtotal' => $amount];
    $subtotal += $amount;
}
$status = strtoupper((string) $order['status_order']);
$statusClass = $status === 'CANCEL' ? 'badge-danger' : ($status === 'OPEN BILL' ? 'badge-warning' : 'badge-success');
$discount = max(0, $subtotal - (float) $order['total_amount']);
?>
<div class="modal-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Order #<?= $id ?></h5>
        <span class="badge <?= $statusClass ?>"><?= $escape($status) ?></span>
    </div>
    <dl class="row mb-3">
        <dt class="col-sm-3">Date</dt><dd class="col-sm-9"><?= $escape($order['created_at']) ?></dd>
        <dt class="col-sm-3">Table</dt><dd class="col-sm-9"><?= $escape($order['table_number']) ?></dd>
        <dt class="col-sm-3">Payment</dt><dd class="col-sm-9"><?= $escape($order['payment_method'] ? ucfirst(str_replace('_', ' ', $order['payment_method'])) : '-') ?></dd>
        <?php if ($status === 'CANCEL'): ?>
            <dt class="col-sm-3">Cancel Reason</dt><dd class="col-sm-9"><?= $escape($order['cancel_reason'] ?? '-') ?></dd>
            <dt class="col-sm-3">Canceled At</dt><dd class="col-sm-9"><?= $escape($order['canceled_at'] ?? '-') ?></dd>
        <?php endif; ?>
    </dl>
    <?php $hasItems = false; ?>
    <?php foreach ($groups as $label => $group): ?>
        <?php if (!$group['items']) continue; ?>
        <?php $hasItems = true; $isCafe = $label === 'Cafe'; ?>
        <h6 class="font-weight-bold"><?= $label ?></h6>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light"><tr><th>Product</th><th class="text-right">Price</th><th class="text-center">Qty</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    <?php foreach ($group['items'] as $item): ?>
                        <tr>
                            <td>
                                <?= $escape($item['item_name']) ?>
                                <?php if (!$isCafe): ?>
                                    <small class="d-block text-muted">
                                        <?php if (!empty($item['variant_name'])): ?>Varian: <?= $escape($item['variant_name']) ?> | <?php endif; ?>
                                        Nopol: <?= $escape($item['nopol']) ?> |
                                        Service: <?= $escape($item['service']) ?> |
                                        Ukuran: <?= $escape($item['ukuran']) ?> |
                                        Vacuum: <?= $escape(ucfirst($item['vacuum'] ?? 'no')) ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-right text-nowrap"><?= $money($item[$isCafe ? 'item_price' : 'unit_price']) ?></td>
                            <td class="text-center"><?= (int) $item[$isCafe ? 'quantity' : 'qty'] ?></td>
                            <td class="text-right text-nowrap">
                                <?= $money($item['total']) ?>
                                <?php if (!$isCafe): ?>
                                    <small class="d-block text-muted">Pegawai: <?= $money($item['profit_pegawai']) ?></small>
                                    <small class="d-block text-muted">Management: <?= $money($item['profit_management']) ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-light"><tr><th colspan="3">Subtotal <?= $label ?></th><th class="text-right text-nowrap"><?= $money($group['subtotal']) ?></th></tr></tfoot>
            </table>
        </div>
    <?php endforeach; ?>
    <?php if (!$hasItems): ?><div class="alert alert-warning">No items found for this order.</div><?php endif; ?>
    <div class="border-top pt-3">
        <?php if ($discount > 0): ?>
            <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span><?= $money($subtotal) ?></span></div>
            <div class="d-flex justify-content-between mb-2"><span>Discount</span><span><?= $money($discount) ?></span></div>
        <?php endif; ?>
        <div class="d-flex justify-content-between h5"><strong>Total</strong><strong><?= $money($order['total_amount']) ?></strong></div>
        <div class="d-flex justify-content-between text-muted"><span>Paid</span><span><?= $money($order['paid_amount']) ?></span></div>
        <div class="d-flex justify-content-between text-muted"><span>Change</span><span><?= $money($order['change_amount']) ?></span></div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-warning print-chit" data-id="<?= $id ?>"><i class="fas fa-receipt"></i> Print Chit</button>
    <button type="button" class="btn btn-primary print-invoice" data-id="<?= $id ?>"><i class="fas fa-print"></i> Print Invoice</button>
    <button type="button" class="btn btn-danger cancel-order" data-id="<?= $id ?>" data-table="<?= $escape($order['table_number']) ?>" <?= $status === 'CANCEL' ? 'disabled' : '' ?>><i class="fas fa-times-circle"></i> Cancel</button>
</div>
