<?php
include "../../config/config.php";
$id = intval($_GET['id']);

// Get normal items
$items = mysqli_query($conn, "SELECT * FROM order_items WHERE id_tr=$id");
// Get carwash items
$carwash = mysqli_query($conn, "SELECT * FROM order_carwash WHERE id_tr=$id");
?>
<h5>Order #<?= $id ?></h5>
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
        <?php while ($i = mysqli_fetch_assoc($items)): ?>
            <tr>
                <td><?= $i['item_name'] ?></td>
                <td><?= number_format($i['item_price'], 0, ",", ".") ?></td>
                <td><?= $i['quantity'] ?></td>
                <td><?= number_format($i['total'], 0, ",", ".") ?></td>
            </tr>
        <?php endwhile; ?>

        <?php while ($c = mysqli_fetch_assoc($carwash)): ?>
            <tr class="table-warning">
                <td><?= $c['item_name'] ?><br>
                    <small>
                        Nopol: <?= $c['nopol'] ?> |
                        Service: <?= $c['service'] ?> |
                        Ukuran: <?= $c['ukuran'] ?> |
                        Vacuum: <?= ucfirst($c['vacuum']) ?>
                    </small>
                </td>
                <td><?= number_format($c['unit_price'], 0, ",", ".") ?></td>
                <td><?= $c['qty'] ?></td>
                <td><?= number_format($c['total'], 0, ",", ".") ?><br>
                    <small class="text-success">Pegawai: <?= number_format($c['profit_pegawai'], 0, ",", ".") ?></small><br>
                    <small class="text-primary">Management: <?= number_format($c['profit_management'], 0, ",", ".") ?></small>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>