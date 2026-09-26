<?php
if (($_SESSION['level'] ?? '') !== 'Administrator') {
    echo '<section class="content pt-3"><div class="container-fluid"><div class="alert alert-danger">Akses ditolak.</div></div></section>';
    return;
}

$breadcrumb = [['label' => 'Data Master'], ['label' => 'Stock Management']];
$_SESSION['stock_csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['stock_csrf'];

$stockRows = $conn->query("SELECT b.*, COUNT(DISTINCT pb.product_id) product_count,
    GROUP_CONCAT(DISTINCT pb.product_id ORDER BY p.nama_prod) product_ids,
    GROUP_CONCAT(DISTINCT p.nama_prod ORDER BY p.nama_prod SEPARATOR ', ') products
    FROM vw_stock_balances b
    LEFT JOIN stock_product_bindings pb ON pb.stock_item_id=b.id
    LEFT JOIN tb_datacafe p ON p.id_prod=pb.product_id
    GROUP BY b.id,b.name,b.initial_quantity,b.unit,b.active,b.current_quantity,b.updated_at
    ORDER BY b.name")->fetch_all(MYSQLI_ASSOC);
$products = $conn->query("SELECT p.id_prod,p.nama_prod,c.name_cat,
    GROUP_CONCAT(pb.stock_item_id ORDER BY s.name) stock_ids,
    GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') stock_names,
    GROUP_CONCAT(CONCAT(s.name, ' x', CAST(pb.quantity_per_sale AS DECIMAL(12,3))) ORDER BY s.name SEPARATOR ', ') usage_summary,
    COALESCE(MAX(pb.quantity_per_sale),1) quantity_per_sale
    FROM tb_datacafe p
    LEFT JOIN tb_category c ON c.id_cat=p.id_cat
    LEFT JOIN stock_product_bindings pb ON pb.product_id=p.id_prod
    LEFT JOIN stock_items s ON s.id=pb.stock_item_id
    GROUP BY p.id_prod,p.nama_prod,c.name_cat ORDER BY p.nama_prod")->fetch_all(MYSQLI_ASSOC);
$movements = $conn->query("SELECT m.created_at,s.name,m.movement_type,m.quantity_delta,m.stock_in_quantity,
    m.order_id,m.order_item_id,m.note,oi.id_prod product_id,oi.item_name product_name
    FROM stock_movements m
    JOIN stock_items s ON s.id=m.stock_item_id
    LEFT JOIN order_items oi ON oi.id=m.order_item_id
    ORDER BY m.id DESC LIMIT 250")->fetch_all(MYSQLI_ASSOC);
$stockInToday = (float)$conn->query("SELECT COALESCE(SUM(stock_in_quantity),0) total FROM stock_movements WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['total'];
$outOfStockCount = count(array_filter($stockRows, fn($row) => (float)$row['current_quantity'] <= 0));
$boundCount = count(array_filter($products, fn($row) => $row['stock_ids'] !== null));
$formatQty = static function ($value): string {
    $number = (float)$value;
    return abs($number - round($number)) < 0.0005 ? number_format($number, 0, ',', '.') : rtrim(rtrim(number_format($number, 3, ',', '.'), '0'), ',');
};
$jsonAttr = static fn($value): string => htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
?>
<section class="content pt-2">
  <div class="container-fluid">
    <?php if (!empty($_SESSION['stock_success'])): ?><div class="alert alert-success alert-dismissible"><button class="close" data-dismiss="alert">&times;</button><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($_SESSION['stock_success']) ?></div><?php unset($_SESSION['stock_success']); endif; ?>
    <?php if (!empty($_SESSION['stock_error'])): ?><div class="alert alert-danger alert-dismissible"><button class="close" data-dismiss="alert">&times;</button><i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($_SESSION['stock_error']) ?></div><?php unset($_SESSION['stock_error']); endif; ?>

    <div class="row">
      <div class="col-sm-6 col-lg-3"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-boxes"></i></span><div class="info-box-content"><span class="info-box-text">Item Stock</span><span class="info-box-number"><?= count($stockRows) ?></span></div></div></div>
      <div class="col-sm-6 col-lg-3"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-link"></i></span><div class="info-box-content"><span class="info-box-text">Produk Terikat</span><span class="info-box-number"><?= $boundCount ?></span></div></div></div>
      <div class="col-sm-6 col-lg-3"><div class="info-box"><span class="info-box-icon bg-primary"><i class="fas fa-arrow-down"></i></span><div class="info-box-content"><span class="info-box-text">Stock Masuk (Hari Ini)</span><span class="info-box-number"><?= $formatQty($stockInToday) ?></span></div></div></div>
      <div class="col-sm-6 col-lg-3"><div class="info-box"><span class="info-box-icon bg-danger"><i class="fas fa-box-open"></i></span><div class="info-box-content"><span class="info-box-text">Stock Habis</span><span class="info-box-number"><?= $outOfStockCount ?></span></div></div></div>
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
              <thead><tr><th>Item</th><th class="text-right">Jumlah</th><th>Produk Terikat</th><th>Action</th></tr></thead><tbody>
              <?php foreach ($stockRows as $row): $quantity=(float)$row['current_quantity']; $productIds=array_values(array_filter(array_map('intval',explode(',',(string)$row['product_ids'])))); ?>
                <tr><td class="font-weight-bold"><?= htmlspecialchars($row['name']) ?></td>
                  <td class="text-right"><span class="badge badge-<?= $quantity < 0 ? 'danger' : ($quantity == 0 ? 'warning' : 'success') ?> px-2 py-1"><?= $formatQty($quantity) ?> <?= htmlspecialchars($row['unit']) ?></span></td>
                  <td><?php if ($row['products']): foreach (explode(', ', $row['products']) as $boundProduct): ?><span class="badge badge-info bound-product-badge"><?= htmlspecialchars($boundProduct) ?></span><?php endforeach; else: ?><span class="text-muted">Belum ada binding</span><?php endif; ?></td>
                  <td><button class="btn btn-success btn-sm edit-stock" data-toggle="modal" data-target="#editStockModal" data-id="<?= (int)$row['id'] ?>" data-name="<?= htmlspecialchars($row['name'],ENT_QUOTES) ?>" data-quantity="<?= htmlspecialchars((string)$quantity) ?>" data-products="<?= $jsonAttr($productIds) ?>" title="Edit item stock" aria-label="Edit item stock"><i class="fas fa-edit"></i></button></td></tr>
              <?php endforeach; ?>
              </tbody></table></div>
          </div>
          <div class="tab-pane fade" id="stock-bindings">
            <div class="alert alert-light border"><i class="fas fa-info-circle mr-1"></i> Satu produk dapat terikat ke beberapa item stock. Produk tetap dapat dijual saat stock nol atau minus.</div>
            <div class="table-responsive"><table id="bindingTable" class="table table-bordered table-striped table-hover">
              <thead><tr><th>Produk Cafe</th><th>Kategori</th><th>Item Stock</th><th>Pemakaian</th><th>Action</th></tr></thead><tbody>
              <?php foreach ($products as $product): $selected=array_values(array_filter(array_map('intval',explode(',',(string)$product['stock_ids'])))); ?>
                <tr><td class="font-weight-bold"><?= htmlspecialchars($product['nama_prod']) ?></td><td><?= htmlspecialchars($product['name_cat'] ?? '-') ?></td>
                  <td><?= $product['stock_names'] ? htmlspecialchars($product['stock_names']) : '<span class="text-muted">Belum terikat</span>' ?></td>
                  <td><?= $product['usage_summary'] ? htmlspecialchars($product['usage_summary']) : '-' ?></td>
                  <td><button class="btn btn-success btn-sm edit-binding" data-toggle="modal" data-target="#editBindingModal" data-id="<?= (int)$product['id_prod'] ?>" data-name="<?= htmlspecialchars($product['nama_prod'],ENT_QUOTES) ?>" data-stocks="<?= $jsonAttr($selected) ?>" data-usage="<?= htmlspecialchars((string)(float)$product['quantity_per_sale']) ?>" title="Edit binding" aria-label="Edit binding"><i class="fas fa-edit"></i></button></td></tr>
              <?php endforeach; ?>
              </tbody></table></div>
          </div>
          <div class="tab-pane fade" id="stock-history">
            <div class="table-responsive"><table id="movementTable" class="table table-bordered table-striped table-hover">
              <thead><tr><th>Waktu</th><th>Item Stock</th><th>Jenis</th><th class="text-right">Perubahan Saldo</th><th class="text-right">Stock Masuk</th><th>ID Transaksi</th><th>Produk</th><th>Catatan</th></tr></thead><tbody>
              <?php foreach ($movements as $movement): $delta=(float)$movement['quantity_delta']; $stockIn=(float)$movement['stock_in_quantity']; $type=$movement['movement_type']==='adjustment' ? ($stockIn>0?'Stock Masuk':'Koreksi') : ($movement['movement_type']==='sale'?'Penjualan':'Pembatalan'); ?>
                <tr><td><?= htmlspecialchars($movement['created_at']) ?></td><td><?= htmlspecialchars($movement['name']) ?></td><td><?= htmlspecialchars($type) ?></td>
                  <td class="text-right text-<?= $delta<0?'danger':'success' ?> font-weight-bold"><?= $delta>0?'+':'' ?><?= $formatQty($delta) ?></td>
                  <td class="text-right"><?= $stockIn>0?'+'.$formatQty($stockIn):'-' ?></td><td><?= $movement['order_id'] ? '#'.(int)$movement['order_id'] : '-' ?></td>
                  <td><?= $movement['product_name'] ? '#'.(int)$movement['product_id'].' '.htmlspecialchars($movement['product_name']) : '-' ?></td><td><?= htmlspecialchars($movement['note'] ?? '-') ?></td></tr>
              <?php endforeach; ?>
              </tbody></table></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="modal fade" id="addStockModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="/include/data/stock/stock_action.php">
  <div class="modal-header"><h5 class="modal-title">Tambah Item Stock</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
  <div class="modal-body"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="add_item"><div class="form-group"><label for="stockName">Nama item</label><input id="stockName" class="form-control" name="name" maxlength="100" required></div><div class="form-group mb-0"><label for="stockInitial">Jumlah awal</label><input id="stockInitial" class="form-control" type="number" step="0.001" name="quantity" value="0" required></div></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button></div>
</form></div></div>

<div class="modal fade" id="editStockModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" action="/include/data/stock/stock_action.php">
  <div class="modal-header"><h5 class="modal-title">Edit Item Stock</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
  <div class="modal-body"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="edit_stock_item"><input type="hidden" name="stock_item_id" id="editStockId">
    <div class="form-group"><label for="editStockQuantity">Jumlah sekarang</label><input id="editStockQuantity" class="form-control" type="number" step="0.001" name="quantity" required></div>
    <div class="form-group mb-0"><label for="editStockProducts">Produk Cafe Terikat</label><select id="editStockProducts" class="form-control" name="product_ids[]" multiple><?php foreach ($products as $product): ?><option value="<?= (int)$product['id_prod'] ?>"><?= htmlspecialchars($product['nama_prod']) ?></option><?php endforeach; ?></select><small class="form-text text-muted">Cari lalu pilih beberapa produk. Produk terpilih ditampilkan sebagai highlight.</small></div>
  </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button></div>
</form></div></div>

<div class="modal fade" id="editBindingModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="/include/data/stock/stock_action.php">
  <div class="modal-header"><h5 class="modal-title">Edit Binding Produk</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
  <div class="modal-body"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="save_binding"><input type="hidden" name="product_id" id="editBindingProductId">
    <div class="form-group"><label for="editBindingStocks">Item Stock Terikat</label><select id="editBindingStocks" class="form-control" name="stock_item_ids[]" multiple><?php foreach ($stockRows as $stock): ?><option value="<?= (int)$stock['id'] ?>"><?= htmlspecialchars($stock['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="editBindingUsage">Pemakaian per Produk Terjual</label><input id="editBindingUsage" class="form-control" type="number" min="0.001" step="0.001" name="quantity_per_sale" value="1" required></div>
  </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button></div>
</form></div></div>

<script>
$(function () {
  $('#stockTable').DataTable({pageLength:25,order:[[0,'asc']],responsive:true,autoWidth:false});
  $('#bindingTable').DataTable({pageLength:25,order:[[0,'asc']],responsive:true,autoWidth:false});
  $('#movementTable').DataTable({pageLength:25,order:[[0,'desc']],responsive:true,autoWidth:false});
  const selectedValues = value => {
    if (Array.isArray(value)) return value.map(String);
    if (typeof value === 'string' && value) { try { return JSON.parse(value).map(String); } catch (error) { return value.split(',').filter(Boolean); } }
    return [];
  };
  $('#editStockProducts').select2({theme:'bootstrap4',width:'100%',dropdownParent:$('#editStockModal'),placeholder:'Cari produk cafe',allowClear:true,closeOnSelect:false});
  $('#editBindingStocks').select2({theme:'bootstrap4',width:'100%',dropdownParent:$('#editBindingModal'),placeholder:'Cari item stock',allowClear:true,closeOnSelect:false});
  $(document).on('click','.edit-stock',function(){
    const button=$(this); $('#editStockModal .modal-title').text('Edit '+button.data('name')); $('#editStockId').val(button.data('id')); $('#editStockQuantity').val(button.data('quantity')); $('#editStockProducts').val(selectedValues(button.attr('data-products'))).trigger('change');
  });
  $(document).on('click','.edit-binding',function(){
    const button=$(this); $('#editBindingModal .modal-title').text('Binding '+button.data('name')); $('#editBindingProductId').val(button.data('id')); $('#editBindingStocks').val(selectedValues(button.attr('data-stocks'))).trigger('change'); $('#editBindingUsage').val(button.data('usage'));
  });
});
</script>
<style>
.bound-product-badge{font-size:.875rem;padding:.4rem .55rem;margin:0 .3rem .3rem 0;white-space:normal;text-align:left}
#editStockModal .select2-selection__choice,#editBindingModal .select2-selection__choice{background:#007bff!important;border-color:#006fe6!important;color:#fff!important;font-size:.9rem;padding:.25rem .5rem!important}
#editStockModal .select2-selection__choice__remove,#editBindingModal .select2-selection__choice__remove{color:#fff!important;margin-right:.35rem!important}
#editStockModal .select2-selection--multiple,#editBindingModal .select2-selection--multiple{min-height:44px}
</style>
