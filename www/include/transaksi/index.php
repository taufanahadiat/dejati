<link rel="stylesheet" href="dist/css/material-symbols.css">
<link rel="stylesheet" href="include/transaksi/custom.css">
<?php
require_once __DIR__ . '/../data/cafe/cafe_image_helper.php';
$breadcrumb = [
    ['label' => 'Transaksi Kasir', 'link' => '#'],
];

$categories = [];
// Query categories
$sql = "SELECT id_cat, name_cat, icon FROM tb_category ORDER BY name_cat ASC";
$result = $conn->query($sql);

$cat_first = [
    'all' => ['label' => 'All', 'icon' => 'grid_view'],
    'carwash' => ['label' => 'Carwash', 'icon' => 'local_car_wash'],
    'detailing' => ['label' => 'Detailing', 'icon' => 'auto_awesome']
];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[$row['id_cat']] = [
            'label' => $row['name_cat'],
            'icon'  => $row['icon']
        ];
    }
}

?>

<?php
$products = [];
$sql = "SELECT id_prod, nama_prod, biaya, variant, nama_var, biaya_var, id_cat, foto FROM tb_datacafe ORDER BY nama_prod ASC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id'       => $row['id_prod'],
            'name'     => $row['nama_prod'],
            'price'    => $row['biaya'] ?? 0,
            'variant'  => $row['variant'] ?? '',
            'nama_var' => $row['nama_var'] ?? '',
            'biaya_var' => $row['biaya_var'] ?? 0,
            'category' => $row['id_cat'],
            'image'    => cafe_product_thumb_url($row['foto'] ?? ''),
            'image_full' => cafe_product_image_url($row['foto'] ?? ''),
        ];
    }
}

$carwashQuery = "SELECT id_produk, produk, biaya, variant, nama_var, biaya_var, foto FROM tb_datacarwash ORDER BY produk ASC";
$carwashResult = $conn->query($carwashQuery);

$carwashProducts = [];
while ($row = mysqli_fetch_assoc($carwashResult)) {
    $carwashProducts[] = [
        'id' => $row['id_produk'],
        'name' => $row['produk'],
        'price' => $row['biaya'],
        'category' => 'carwash',
        'variant' => $row['variant'] ?? 0,
        'biaya_var' => $row['biaya_var'] ?? '',
        'nama_var' => $row['nama_var'] ?? '',
        'image' => !empty($row['foto']) ? cafe_product_thumb_url($row['foto']) : '',
        'image_full' => !empty($row['foto']) ? cafe_product_image_url($row['foto']) : '',
    ];
}

$detailingQuery = "SELECT id_produk, produk, biaya, variant, nama_var, biaya_var, foto FROM tb_datadetailing ORDER BY produk ASC";
$detailingResult = $conn->query($detailingQuery);

$detailingProducts = [];
while ($row = mysqli_fetch_assoc($detailingResult)) {
    $detailingProducts[] = [
        'id' => $row['id_produk'],
        'name' => $row['produk'],
        'price' => $row['biaya'],
        'category' => 'detailing',
        'variant' => $row['variant'] ?? 0,
        'biaya_var' => $row['biaya_var'] ?? '',
        'nama_var' => $row['nama_var'] ?? '',
        'image' => !empty($row['foto']) ? cafe_product_thumb_url($row['foto']) : '',
        'image_full' => !empty($row['foto']) ? cafe_product_image_url($row['foto']) : '',
    ];
}

$allProducts = array_merge($carwashProducts, $detailingProducts, $products);

// Sort alphabetically by 'name'
usort($allProducts, function ($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

function formatPrice($number)
{
    return number_format($number, 0, ',', '.');
}
?>

<section class="content pt-3">
    <div class="container-fluid p-0">
        <!-- Begin::Layout -->
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-4 mt-3 mt-lg-0">
                <div class="position-sticky" style="top: 70px;">
                    <div class="card d-flex flex-column" style="height: calc(100vh - 100px);">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Order Summary</h5>
                        </div>

                        <!-- Scrollable body -->
                        <div class="card-body d-flex flex-column p-2 overflow-auto">
                            <div class="table-responsive flex-grow-1 overflow-auto">
                                <table class="table table-sm mb-3 text-center" id="order-table"
                                    style="border:1px solid #dee2e6; border-collapse: separate; border-spacing: 0;">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Item</th>
                                            <th style="width: 90px;">Price</th>
                                            <th style="width: 60px;">Qty</th>
                                            <th style="width: 90px;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <div class="mt-auto border-top pt-2">
                                <h5>Total: <span id="total-amount">Rp 0</span></h5>
                                <button class="btn btn-success btn-block mt-2" id="payNow">Pay Now</button>
                                <button class="btn btn-info btn-block mb-2" id="openBill">Open Bill</button>
                                <button class="btn btn-danger btn-block mt-2" id="clearCart">Clear Transaction</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Open Bill Modal -->
<!-- Open Bill Modal -->
<div class="modal fade" id="openBillModal" tabindex="-1" aria-labelledby="openBillModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg"> <!-- modal-lg makes it wider -->
    <form id="openBillForm" class="w-100">
      <div class="modal-content shadow-lg" style="border-radius: 12px;">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title" id="openBillModalLabel">Open Bill</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body px-4 py-3">
          <div class="form-group">
            <label for="open-table-number" class="font-weight-semibold">Table Number</label>
            <input type="text" class="form-control form-control-lg" id="open-table-number" name="table_number" placeholder="Enter table number, e.g. A1 / VIP 2 / Takeaway" maxlength="50" required>
          </div>
        </div>

        <div class="modal-footer justify-content-end border-0 px-4 pb-4">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-info px-4">Save Open Bill</button>
        </div>
      </div>
    </form>
  </div>
</div>



            <!-- Pay Modal -->
            <div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form id="payForm">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="payModalLabel">Payment</h5>
                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <!-- Table number -->
                                <div class="form-group">
                                    <label for="table-number">Table Number</label>
                                    <input type="text" class="form-control" id="table-number" name="table_number" placeholder="e.g. A1 / VIP 2 / Takeaway" maxlength="50" required>
                                </div>

                                <!-- Payment method -->
                                <div class="form-group">
                                    <label for="payment-method">Payment Method</label>
                                    <select class="form-control" id="payment-method" name="payment_method" required>
                                        <option value="">-- Select Method --</option>
                                        <option value="cash">Cash</option>
                                        <option value="credit_card">Credit Card</option>
                                        <option value="qris">QRIS</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Subtotal</label>
                                    <input type="text" class="form-control" id="modal-subtotal" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="payment-discount">Discount (%)</label>
                                    <input type="text" class="form-control" id="payment-discount" inputmode="numeric" placeholder="0">
                                    <small class="form-text text-muted">Example: enter 10 for 10% discount.</small>
                                </div>

                                <div class="form-group">
                                    <label>Grand Total</label>
                                    <input type="text" class="form-control" id="modal-total" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="customer-pay">Customer Pay</label>
                                    <input type="text" class="form-control" id="customer-pay" inputmode="numeric" required>
                                    <div class="btn-group btn-group-sm mt-2" role="group" aria-label="Quick payment buttons">
                                        <button type="button" class="btn btn-outline-primary quick-pay" data-amount="50000">50,000</button>
                                        <button type="button" class="btn btn-outline-primary quick-pay" data-amount="100000">100,000</button>
                                        <button type="button" class="btn btn-outline-primary quick-pay" data-amount="500000">500,000</button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Change</label>
                                    <input type="text" class="form-control" id="change-amount" readonly>
                                </div>
                            </div>
                            <div class="modal-footer justify-content-between">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Print Invoice</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- POS Section -->
            <div class="col-lg-8">
                <!-- Search Input -->
                <div class="mb-2">
                    <input type="text" id="product-search" class="form-control" style="height: 50px;" placeholder="Search product...">
                </div>

                <?php
                // Merge cat_first + categories (cat_first first, then DB categories)
                $allCategories = $cat_first + $categories;
                ?>

                <!-- Category Nav -->
                <ul class="nav nav-pills d-flex justify-content-start nav-pills-custom border rounded p-1 mb-2"
                    style="overflow-x:auto;" role="tablist" id="categoryTabs">
                    <?php foreach ($allCategories as $key => $cat): ?>
                        <li class="nav-item mr-1" role="presentation">
                            <a class="order-cat nav-link btn btn-outline btn-sm p-1 flex-column align-items-center"
                                style="max-width:80px;height:75px;"
                                data-category="<?= $key ?>" href="#">
                                <div class="mb-1" style="line-height:1;">
                                    <span class="material-symbols-outlined" style="font-size:18px;">
                                        <?= $cat['icon'] ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="text-dark text-bold d-block" style="font-size:12px;">
                                        <?= $cat['label'] ?>
                                    </span>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Product Grid Card -->
                <div class="card shadow-sm" style="min-height: calc(75vh - 75px);">
                    <div class="card-body">
                        <div class="row justify-content-left" id="product-list">
                            <?php foreach ($allProducts as $p): ?>
                                <div class="col-md-2 col-4 mb-2 px-1" style="max-width: 130px;" data-category="<?= $p['category'] ?>" data-name="<?= htmlspecialchars(strtolower($p['name']), ENT_QUOTES) ?>">
                                    <div class="card product-card shadow-sm p-1" style="height:170px; cursor: pointer;"
                                        onclick="<?= $p['variant'] == 1
                                            ? "showVariantModal('{$p['id']}', " . htmlspecialchars(json_encode($p['name']), ENT_QUOTES) . ", " . htmlspecialchars(json_encode($p['nama_var']), ENT_QUOTES) . ", " . htmlspecialchars(json_encode($p['biaya_var']), ENT_QUOTES) . ", '" . (in_array($p['category'], ['carwash', 'detailing'], true) ? $p['category'] : 'product') . "')"
                                            : (in_array($p['category'], ['carwash', 'detailing'], true)
                                                ? "showCarwashModal('{$p['id']}', " . htmlspecialchars(json_encode($p['name']), ENT_QUOTES) . ", {$p['price']}, '{$p['category']}')"
                                                : "showBuyQueryModal('{$p['id']}', " . htmlspecialchars(json_encode($p['name']), ENT_QUOTES) . ", {$p['price']})") ?>">
                                        <?php
                                        $imgSrc = $p['image'] ?? '';
                                        $imgExists = !empty($imgSrc);
                                        $words = preg_split('/\s+/', trim($p['name']));
                                        $initials = strtoupper(implode('', array_map(fn($w) => $w !== '' ? $w[0] : '', $words)));
                                        ?>

                                        <?php if ($imgExists): ?>
                                            <img class="card-img-top p-1 mx-auto d-block" loading="lazy" decoding="async"
                                                style="width: 100px; height: 100px; object-fit: cover; border-radius: 10%;"
                                                src="<?= htmlspecialchars($imgSrc) ?>"
                                                alt="<?= htmlspecialchars($p['name']) ?>" width="100" height="100"
                                                onerror="this.style.display='none'; this.insertAdjacentHTML('afterend', '<div class=\'product-no-img card-img-top p-1 d-flex justify-content-center align-items-center bg-secondary text-white\'><?= $initials ?></div>');">
                                        <?php else: ?>
                                            <div class="product-no-img card-img-top p-1 d-flex justify-content-center align-items-center bg-secondary text-white"
                                                style="width: 100px; height: 100px; border-radius: 10%;">
                                                <?= $initials ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="card-body text-center p-2">
                                            <h6 class="product-title mb-1 text-left" style="font-size: 12px; line-height: 1.2; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; white-space: normal; height: 30px;">
                                                <?= $p['name'] ?>
                                            </h6>
                                            <p class="product-price mb-0 text-right text-muted" style="font-size: 11px;">
                                                <?= $p['variant'] == '1'
                                                    ? formatPrice((int)(explode(';', $p['biaya_var'])[0] ?? 0))
                                                    : formatPrice($p['price']) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <!-- End::Layout -->
            </div>
            <!-- Variant Selection Modal -->
            <div class="modal fade" id="variantModal" tabindex="-1" role="dialog" aria-labelledby="variantModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <h5 class="modal-title text-white" id="variantModalLabel">Select Variant</h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div id="variant-options" class="list-group"></div>
                        </div>
                    </div>
                </div>
            </div>
                               
            <!-- Buy Query Modal -->
            <div class="modal fade" id="buyQueryModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <h5 class="modal-title text-white" id="buyQueryTitle"></h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <!-- Price -->
                                <div class="form-group col-md-6">
                                    <label>Price</label>
                                    <h5 id="buyQueryPrice"></h5>
                                </div>

                                <!-- Quantity -->
                                <div class="form-group col-md-6">
                                    <label>Quantity</label>
                                    <div class="input-group">
                                        <input type="text" id="buyQty" class="form-control text-center col-md-3" value="1" disabled>
                                        <div class="input-group-prepend">
                                            <button class="btn btn-secondary" type="button" onclick="changeBuyQty(-1)">-</button>
                                        </div>
                                        <div class="input-group-append">
                                            <button class="btn btn-secondary" type="button" onclick="changeBuyQty(1)">+</button>
                                        </div>
                                    </div>
                                </div>


                                <!-- Order Type Section -->
                                <div class="row form-group ml-1">
                                    <div class="d-flex align-items-center">
                                        <input type="checkbox" name="orderType" id="orderTypeSwitch" value="dine-in" checked data-bootstrap-switch data-on-text="Dine In" data-off-text="Take Away" data-on-color="info" data-off-color="warning">
                                    </div>
                                </div>

                                <!-- Notes (Full Width) -->
                                <div class="form-group col-md-12">
                                    <label>Notes</label>
                                    <textarea id="buyNotes" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" id="deleteCartItemBtn" style="display:none;">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <button type="button" class="btn btn-success" onclick="saveBuyQuery()">
                                <i class="fas fa-save"></i> Save
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="carwashModal" tabindex="-1" role="dialog" aria-labelledby="carwashModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <form id="carwash-form">
                            <div class="modal-header bg-primary">
                                <h5 class="modal-title" id="carwashModalLabel">Carwash Service</h5>
                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" id="carwash-variant">
                                <input type="hidden" id="carwash-type" value="carwash">
                                <input type="hidden" id="carwash-id">
                                <input type="hidden" id="carwash-name">
                                <input type="hidden" id="carwash-price">

                                <div class="mb-3">
                                    <label for="nopol" class="form-label">No Polisi</label>
                                    <input type="text" class="form-control" id="nopol" required>
                                </div>

                                <div class="mb-3">
                                    <label for="service" class="form-label">Service</label>
                                    <input type="text" class="form-control" id="service" required>
                                </div>

                                <div class="mb-3">
                                    <label for="ukuran" class="form-label">Ukuran Kendaraan</label>
                                    <select class="form-control" id="ukuran" required>
                                        <option value="">Pilih Ukuran</option>
                                        <option value="Mobil Besar">Mobil Besar</option>
                                        <option value="Mobil Sedang">Mobil Sedang</option>
                                        <option value="Motor">Motor</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label d-block">Gunakan Vacuum?</label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="vacuum" id="vacuumYes" value="yes">
                                        <label class="form-check-label" for="vacuumYes">Ya (+Rp5.000)</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="vacuum" id="vacuumNo" value="no" checked>
                                        <label class="form-check-label" for="vacuumNo">Tidak</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <strong>Total: Rp <span id="carwash-total">0</span></strong>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" id="carwash-hold">Hold</button>
                                <button type="submit" class="btn btn-success">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
</section>


<?php
// place this near the top of index.php, before HTML output
$imported_order_json = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['order_details'])) {
    // order_details is a JSON string sent via form hidden input
    $raw = $_POST['order_details'];
    // validate / decode
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && !empty($decoded)) {
        // keep for injection into JS below
        $imported_order_json = json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }
}
?>
<?php
// jika page menerima order_details via POST dari report.php
if (!empty($imported_order_json)):
    // $imported_order_json sudah berisi JSON dari server (lihat chat sebelumnya)
?>
<script>
(function(){
  // data dari server (format yang dikirim dari report.php)
  const IMPORTED = <?= $imported_order_json ?>;

  // build cart array sesuai struktur yang dipakai script.php:updateCartDisplay()
  const importedCart = [];

  // helper to safely parse int
  const toInt = v => {
    if (typeof v === 'string') v = v.replace(/[^\d\-]/g,'');
    return parseInt(v) || 0;
  };

  if (Array.isArray(IMPORTED.items)) {
    IMPORTED.items.forEach(it => {
      importedCart.push({
        id: it.id_prod || it.item_id || 0,
        name: (it.item_name || it.name || '').trim(),
        unitPrice: toInt(it.item_price || it.price || 0),
        finalPrice: toInt(it.item_price || it.price || 0), // assume no discount imported
        discountValue: 0,
        discountType: 'amount', // or 'percent' if you want
        qty: toInt(it.quantity || it.qty || 1),
        orderType: it.order_type ?? null,
        cartType: 'product',
        notes: it.notes || ''
      });
    });
  }

  if (Array.isArray(IMPORTED.carwash)) {
    IMPORTED.carwash.forEach(cw => {
      importedCart.push({
        id: cw.id_prod || cw.id || 0,
        name: (cw.item_name || cw.name || 'Carwash').trim() + ' (Carwash)',
        unitPrice: toInt(cw.unit_price || cw.item_price || cw.price || 0),
        finalPrice: toInt(cw.total ? (toInt(cw.total) / Math.max(1, toInt(cw.qty))) : (cw.unit_price || cw.item_price || cw.price) ),
        discountValue: 0,
        discountType: 'amount',
        qty: toInt(cw.qty || 1),
        orderType: cw.order_type || 'dine-in',
        cartType: 'carwash',
        variantName: cw.variant_name || '',
        nopol: cw.nopol || '', service: cw.service || '', ukuran: cw.ukuran || '', vacuum: cw.vacuum || 'no',
        notes: `NoPol: ${cw.nopol || ''} Service: ${cw.service || ''} Ukuran: ${cw.ukuran || ''} Vacuum: ${cw.vacuum || ''}`
      });
    });
  }

  if (Array.isArray(IMPORTED.detailing)) {
    IMPORTED.detailing.forEach(cw => {
      importedCart.push({
        id: cw.id_prod || cw.id || 0,
        name: (cw.item_name || cw.name || 'Detailing').trim() + ' (Detailing)',
        unitPrice: toInt(cw.unit_price || cw.item_price || cw.price || 0),
        finalPrice: toInt(cw.total ? (toInt(cw.total) / Math.max(1, toInt(cw.qty))) : (cw.unit_price || cw.item_price || cw.price) ),
        discountValue: 0,
        discountType: 'amount',
        qty: toInt(cw.qty || 1),
        orderType: cw.order_type || 'dine-in',
        cartType: 'detailing',
        variantName: cw.variant_name || '',
        nopol: cw.nopol || '', service: cw.service || '', ukuran: cw.ukuran || '', vacuum: cw.vacuum || 'no',
        notes: `NoPol: ${cw.nopol || ''} Service: ${cw.service || ''} Ukuran: ${cw.ukuran || ''} Vacuum: ${cw.vacuum || ''}`
      });
    });
  }

  // Save to localStorage exactly as script.php expects
  localStorage.setItem('cart', JSON.stringify(importedCart));
  localStorage.setItem('activeTableNumber', (IMPORTED.order && IMPORTED.order.table_number) ? String(IMPORTED.order.table_number) : '');
  console.log('ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ Imported cart written to localStorage:', importedCart);

  // We DO NOT directly modify table HTML here.
  // include/transaksi/script.php will call updateCartDisplay() on DOM ready
  // and render rows with the correct structure.

})();
</script>
<?php endif; ?>

<!-- Script -->
<?php include 'include/transaksi/script.php'; ?>
