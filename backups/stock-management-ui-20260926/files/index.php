<?php
if (($_SESSION['level'] ?? '') !== 'Administrator') {
    echo '<section class="content pt-3"><div class="container-fluid"><div class="alert alert-danger">Akses ditolak.</div></div></section>';
    return;
}

$breadcrumb = [['label' => 'Data Master'], ['label' => 'Manajemen Stock']];
$_SESSION['stock_csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['stock_csrf'];

$stockRows = $conn->query("SELECT b.*, COUNT(DISTINCT pb.product_id) product_count,
    GROUP_CONCAT(DISTINCT p.nama_prod ORDER BY p.nama_prod SEPARATOR ', ') products
    FROM vw_stock_balances b
    LEFT JOIN stock_product_bindings pb ON pb.stock_item_id=b.id
    LEFT JOIN tb_datacafe p ON p.id_prod=pb.product_id
    GROUP BY b.id,b.name,b.initial_quantity,b.unit,b.active,b.current_quantity,b.updated_at
    ORDER BY b.name")->fetch_all(MYSQLI_ASSOC);
$products = $conn->query("SELECT p.id_prod,p.nama_prod,c.name_cat,
    GROUP_CONCAT(pb.stock_item_id ORDER BY s.name) stock_ids,
    GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') stock_names,
    COALESCE(MAX(pb.quantity_per_sale),1) quantity_per_sale
    FROM tb_datacafe p
    LEFT JOIN tb_category c ON c.id_cat=p.id_cat
    LEFT JOIN stock_product_bindings pb ON pb.product_id=p.id_prod
    LEFT JOIN stock_items s ON s.id=pb.stock_item_id
    GROUP BY p.id_prod,p.nama_prod,c.name_cat ORDER BY p.nama_prod")->fetch_all(MYSQLI_ASSOC);
$movements = $conn->query("SELECT m.created_at,s.name,m.movement_type,m.quantity_delta,m.order_id,m.note
    FROM stock_movements m JOIN stock_items s ON s.id=m.stock_item_id
    ORDER BY m.id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
$negativeCount = count(array_filter($stockRows, fn($row) => (float)$row['current_quantity'] < 0));
$zeroCount = count(array_filter($stockRows, fn($row) => (float)$row['current_quantity'] == 0));
$boundCount = count(array_filter($products, fn($row) => $row['stock_ids'] !== null));
$formatQty = static function ($value): string {
    $number = (float)$value;
    return abs($number - round($number)) < 0.0005 ? number_format($number, 0, ',', '.') : rtrim(rtrim(number_format($number, 3, ',', '.'), '0'), ',');
};
?>
<section class="content pt-2">
  <div class="container-fluid">
    <?php if (!empty($_SESSION['stock_success'])): ?><div class="alert alert-success alert-dismissible"><button class="close" data-dismiss="alert">&times;</button><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($_SESSION['stock_success']) ?></div><?php unset($_SESSION['stock_success']); endif; ?>
    <?php if (!empty($_SESSION['stock_error'])): ?><div class="alert alert-danger alert-dismissible"><button class="close" data-dismiss="alert">&times;</button><i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($_SESSION['stock_error']) ?></div><?php unset($_SESSION['stock_error']); endif; ?>

    <div class="row">
      <div class="col-sm-6 col-lg-3"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-boxes"></i></span><div class="info-box-content"><span class="info-box-text">Item Stock</span><span class="info-box-number"><?= count($stockRows) ?></span></div></div></div>
      <div class="col-sm-6 col-lg-3"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-link"></i></span><div class="info-box-content"><span class="info-box-text">Produk Terikat</span><span class="info-box-number"><?= $boundCount ?></span></div></div></div>
      <div class="col-sm-6 col-lg-3"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-equals"></i></span><div class="info-box-content"><span class="info-box-text">Stock Nol</span><span class="info-box-number"><?= $zeroCount ?></span></div></div></div>
      <div class="col-sm-6 col-lg-3"><div class="info-box"><span class="info-box-icon bg-danger"><i class="fas fa-arrow-down"></i></span><div class="info-box-content"><span class="info-box-text">Stock Minus</span><span class="info-box-number"><?= $negativeCount ?></span></div></div></div>
    </div>

    <div class="card card-dark card-outline">
      <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs" role="tablist">
          <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#stock-current"><i class="fas fa-boxes mr-1"></i> Stock Saat Ini</a></li>
          <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#stock-bindings"><i class="fas fa-link mr-1"></i> Binding Produk</a></li>
          <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#stock-history"><i class="fas fa-history mr-1"></i> Riwayat</a></li>
        </ul>
      </div>
      <div class="card-body">
        <div class="tab-content">
          <div class="tab-pane fade show active" id="stock-current">
            <div class="mb-3"><button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addStockModal"><i class="fas fa-plus mr-1"></i> Tambah Item Stock</button></div>
            <div class="table-responsive"><table id="stockTable" class="table table-bordered table-striped table-hover">
              <thead><tr><th>Item</th><th class="text-right">Jumlah</th><th>Produk Terikat</th><th>Atur Jumlah</th></tr></thead><tbody>
              <?php foreach ($stockRows as $row): $quantity=(float)$row['current_quantity']; ?>
                <tr><td class="font-weight-bold"><?= htmlspecialchars($row['name']) ?></td>
                  <td class="text-right"><span class="badge badge-<?= $quantity < 0 ? 'danger' : ($quantity == 0 ? 'warning' : 'success') ?> px-2 py-1"><?= $formatQty($quantity) ?> <?= htmlspecialchars($row['unit']) ?></span></td>
                  <td><small><?= $row['products'] ? htmlspecialchars($row['products']) : '<span class="text-muted">Belum ada binding</span>' ?></small></td>
                  <td><form class="form-inline flex-nowrap" method="post" action="/include/data/stock/stock_action.php">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="set_quantity"><input type="hidden" name="stock_item_id" value="<?= (int)$row['id'] ?>">
                    <input class="form-control form-control-sm mr-1" style="width:92px" type="number" step="0.001" name="quantity" value="<?= htmlspecialchars((string)(float)$row['current_quantity']) ?>" aria-label="Jumlah baru" required>
                    <input class="form-control form-control-sm mr-1" style="min-width:150px" name="note" maxlength="180" placeholder="Catatan koreksi">
                    <button class="btn btn-primary btn-sm" title="Simpan jumlah" aria-label="Simpan jumlah"><i class="fas fa-save"></i></button>
                  </form></td></tr>
              <?php endforeach; ?>
              </tbody></table></div>
          </div>
          <div class="tab-pane fade" id="stock-bindings">
            <div class="alert alert-light border"><i class="fas fa-info-circle mr-1"></i> Produk tetap dapat dijual saat stock nol atau minus. Pemakaian default adalah 1 unit per produk terjual.</div>
            <div class="table-responsive"><table id="bindingTable" class="table table-bordered table-striped table-hover">
              <thead><tr><th>Produk Cafe</th><th>Kategori</th><th>Item Stock</th><th>Pemakaian</th><th>Action</th></tr></thead><tbody>
              <?php foreach ($products as $product): $selected=array_filter(explode(',', (string)$product['stock_ids'])); $formId='binding-'.(int)$product['id_prod']; ?>
                <tr>
                  <td class="font-weight-bold"><?= htmlspecialchars($product['nama_prod']) ?></td>
                  <td><?= htmlspecialchars($product['name_cat'] ?? '-') ?></td>
                  <td><select class="form-control form-control-sm select2" name="stock_item_ids[]" form="<?= $formId ?>" multiple data-placeholder="Belum terikat"><?php foreach ($stockRows as $stock): ?><option value="<?= (int)$stock['id'] ?>" <?= in_array((string)$stock['id'],$selected,true)?'selected':'' ?>><?= htmlspecialchars($stock['name']) ?></option><?php endforeach; ?></select></td>
                  <td><input class="form-control form-control-sm" style="width:90px" type="number" min="0.001" step="0.001" name="quantity_per_sale" form="<?= $formId ?>" value="<?= htmlspecialchars((string)(float)$product['quantity_per_sale']) ?>" required></td>
                  <td><form id="<?= $formId ?>" method="post" action="/include/data/stock/stock_action.php"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="save_binding"><input type="hidden" name="product_id" value="<?= (int)$product['id_prod'] ?>"><button class="btn btn-primary btn-sm" title="Simpan binding" aria-label="Simpan binding"><i class="fas fa-save"></i></button></form></td>
                </tr>
              <?php endforeach; ?>
              </tbody></table></div>
          </div>
          <div class="tab-pane fade" id="stock-history">
            <div class="table-responsive"><table id="movementTable" class="table table-bordered table-striped table-hover">
              <thead><tr><th>Waktu</th><th>Item</th><th>Jenis</th><th class="text-right">Perubahan</th><th>Order</th><th>Catatan</th></tr></thead><tbody>
              <?php foreach ($movements as $movement): $delta=(float)$movement['quantity_delta']; ?>
                <tr><td><?= htmlspecialchars($movement['created_at']) ?></td><td><?= htmlspecialchars($movement['name']) ?></td><td><?= htmlspecialchars(ucfirst($movement['movement_type'])) ?></td><td class="text-right text-<?= $delta<0?'danger':'success' ?> font-weight-bold"><?= $delta>0?'+':'' ?><?= $formatQty($delta) ?></td><td><?= $movement['order_id'] ? '#'.(int)$movement['order_id'] : '-' ?></td><td><?= htmlspecialchars($movement['note'] ?? '-') ?></td></tr>
              <?php endforeach; ?>
              </tbody></table></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<div class="modal fade" id="addStockModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><form class="modal-content" method="post" action="/include/data/stock/stock_action.php">
    <div class="modal-header"><h5 class="modal-title">Tambah Item Stock</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
    <div class="modal-body"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="add_item">
      <div class="form-group"><label for="stockName">Nama item</label><input id="stockName" class="form-control" name="name" maxlength="100" required></div>
      <div class="form-group mb-0"><label for="stockInitial">Jumlah awal</label><input id="stockInitial" class="form-control" type="number" step="0.001" name="quantity" value="0" required></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button></div>
  </form></div>
</div>
<script>
$(function () {
  $('#stockTable').DataTable({pageLength:25,order:[[0,'asc']],responsive:true,autoWidth:false});
  $('#bindingTable').DataTable({pageLength:25,order:[[0,'asc']],responsive:true,autoWidth:false});
  $('#movementTable').DataTable({pageLength:25,order:[[0,'desc']],responsive:true,autoWidth:false});
  $('.select2').select2({theme:'bootstrap4',width:'100%'});
});
</script>
