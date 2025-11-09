<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../../config/config.php";
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo "<div class='alert alert-danger'>Invalid order ID.</div>";
    exit;
}

$items = mysqli_query($conn, "SELECT * FROM order_items WHERE id_tr=$id");
$carwash = mysqli_query($conn, "SELECT * FROM order_carwash WHERE id_tr=$id");

$item_count = $items ? mysqli_num_rows($items) : 0;
$carwash_count = $carwash ? mysqli_num_rows($carwash) : 0;
?>
<h5>Order #<?= $id ?></h5>
<?php if ($item_count == 0 && $carwash_count == 0): ?>
    <div class="alert alert-warning">No items found for this order.</div>
<?php else: ?>
<table class="table table-sm table-bordered">
    <thead>
        <tr>
            <th>Product</th>
            <th>Price</th>
            <th>Qty</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($items) while ($i = mysqli_fetch_assoc($items)): ?>
            <tr>
                <td><?= htmlspecialchars($i['item_name']) ?></td>
                <td><?= number_format($i['item_price'], 0, ",", ".") ?></td>
                <td><?= $i['quantity'] ?></td>
                <td><?= number_format($i['total'], 0, ",", ".") ?></td>
            </tr>
        <?php endwhile; ?>

        <?php if ($carwash) while ($c = mysqli_fetch_assoc($carwash)): ?>
            <tr class="table-warning">
                <td>
                    <?= htmlspecialchars($c['item_name']) ?><br>
                    <small>
                        Nopol: <?= htmlspecialchars($c['nopol']) ?> |
                        Service: <?= htmlspecialchars($c['service']) ?> |
                        Ukuran: <?= htmlspecialchars($c['ukuran']) ?> |
                        Vacuum: <?= ucfirst($c['vacuum']) ?>
                    </small>
                </td>
                <td><?= number_format($c['unit_price'], 0, ",", ".") ?></td>
                <td><?= $c['qty'] ?></td>
                <td>
                    <?= number_format($c['total'], 0, ",", ".") ?><br>
                    <small class="text-success">Pegawai: <?= number_format($c['profit_pegawai'], 0, ",", ".") ?></small><br>
                    <small class="text-primary">Management: <?= number_format($c['profit_management'], 0, ",", ".") ?></small>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php endif; ?>