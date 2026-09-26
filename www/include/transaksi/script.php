<script>
    const products = <?= json_encode($products) ?>;

    function rupiah(number) {
        return 'Rp ' + number.toLocaleString('id-ID');
    }

    $(function() {
        // Initialize all bootstrap switches
        $("input[data-bootstrap-switch]").each(function() {
            $(this).bootstrapSwitch('state', $(this).prop('checked'));
        });

        // Listen to Order Type Switch
        $('#orderTypeSwitch').on('switchChange.bootstrapSwitch', function(event, state) {
            if (state) {
                $('#orderTypeSwitch').val('dine-in');
            } else {
                $('#orderTypeSwitch').val('take-away');
            }
        });
    });

    let cart = JSON.parse(localStorage.getItem('cart')) || [];

    function updateCartDisplay() {
        let tbody = $('#order-table tbody');
        tbody.empty();
        let total = 0;

        cart.forEach((item, index) => {
            const unitPrice = parseInt(item.unitPrice || item.finalPrice || 0, 10) || 0;
            const finalPrice = ["carwash", "detailing"].includes(item.cartType) ? (parseInt(item.finalPrice || unitPrice, 10) || 0) : unitPrice;
            item.unitPrice = unitPrice;
            item.finalPrice = finalPrice;
            item.discountValue = 0;
            item.discountType = "amount";
            let rowTotal = finalPrice * item.qty;
            total += rowTotal;

            let priceHtml = `<div>${unitPrice.toLocaleString("id-ID")}</div>`;

            let itemName = item.name;

            if (['carwash', 'detailing'].includes(item.cartType) && item.hold) {
                itemName = `<span class="text-danger">${itemName}</span>`;
            }

            if ((item.cartType || 'product') === 'product') {
                const orderTypeLabel = item.orderType === 'take-away' ? 'Take Away' : (item.orderType === 'dine-in' ? 'Dine In' : 'Belum tercatat');
                itemName += ` <small class="d-block text-muted">${orderTypeLabel}</small>`;
            }

            if (item.notes && item.notes.trim() !== '') {
                itemName += ` <small class="text-muted">**${item.notes}</small>`;
            }


            tbody.append(`
            <tr class="cart-row" data-index="${index}" style="cursor:pointer;">
                <td class="text-left">${itemName}</td>
                <td>${priceHtml}</td>
                <td>${item.qty}</td>
                <td>${rowTotal.toLocaleString('id-ID')}</td>
            </tr>
        `);
        });

        $('#total-amount').text(rupiah(total));
        localStorage.setItem('cart', JSON.stringify(cart));
    }

    $(document).on('click', '.cart-row', function() {
        let index = $(this).data('index');
        let item = cart[index];

        currentProduct = {
            id: item.id,
            name: item.name,
            price: item.unitPrice // always keep original unit price
        };

        $('#buyQueryTitle').text(item.name);
        $('#buyQty').val(item.qty);
        $('#buyNotes').val(item.notes || '');
        $('#orderTypeSwitch').bootstrapSwitch('state', item.orderType !== 'take-away', true);
        $('#orderTypeSwitch').val(item.orderType || 'dine-in');


        $('#buyQueryModal').data('edit-index', index);

        // show delete button when editing
        $('#deleteCartItemBtn').show();

        $('#buyQueryModal').modal('show');
        updateBuyQueryPrice();
    });

    let currentProduct = {};

    function showBuyQueryModal(id, name, price) {
        currentProduct = {
            id,
            name,
            price
        };

        $('#buyQueryTitle').text(name);
        $('#buyQty').val(1);
        $('#buyNotes').val('');
        $('#orderTypeSwitch').bootstrapSwitch('state', true, true);
        $('#orderTypeSwitch').val('dine-in');

        $('#buyQueryModal').removeData('edit-index');

        // hide delete button for new items
        $('#deleteCartItemBtn').hide();

        $('#buyQueryModal').modal('show');
        updateBuyQueryPrice();
    }

    $('#deleteCartItemBtn').on('click', function() {
        let editIndex = $('#buyQueryModal').data('edit-index');
        if (editIndex !== undefined && editIndex !== null) {
            cart.splice(editIndex, 1); // remove from cart
            updateCartDisplay();
        }

        $('#buyQueryModal').removeData('edit-index');
        $('#buyQueryModal').modal('hide');
    });


    function updateBuyQueryPrice() {
        let qty = parseInt($("#buyQty").val()) || 1;
        let basePrice = currentProduct.price * qty;
        $("#buyQueryPrice").html(`
            <span style="color:#000; font-weight:bold; font-size:16px;">
                ${(basePrice).toLocaleString("id-ID")}
            </span>
        `);
    }

    $("#buyQty").on("input change", updateBuyQueryPrice);

    function changeBuyQty(change) {
        let qty = parseInt($("#buyQty").val()) || 1;
        qty = Math.max(1, qty + change);
        $("#buyQty").val(qty);
        updateBuyQueryPrice();
    }

    function saveBuyQuery() {
        let qty = parseInt($("#buyQty").val()) || 1;
        let notes = $("#buyNotes").val();
        let orderType = $("#orderTypeSwitch").val();
        let basePrice = currentProduct.price;

        let editIndex = $("#buyQueryModal").data("edit-index");
        let cartItem = {
            id: currentProduct.id,
            name: currentProduct.name,
            unitPrice: basePrice,
            finalPrice: basePrice,
            discountValue: 0,
            discountType: "amount",
            qty: qty,
            orderType: orderType,
            cartType: "product",
            notes: notes
        };

        if (editIndex !== undefined && editIndex !== null) {
            const previous = cart[editIndex];
            cart[editIndex] = ['carwash', 'detailing'].includes(previous.cartType)
                ? { ...previous, qty, notes, orderType }
                : cartItem;
        } else {
            let existingIndex = cart.findIndex(item =>
                (item.cartType || "product") === cartItem.cartType &&
                item.id === cartItem.id &&
                item.name === cartItem.name &&
                item.unitPrice === cartItem.unitPrice &&
                item.orderType === cartItem.orderType &&
                item.notes === cartItem.notes
            );

            if (existingIndex > -1) {
                cart[existingIndex].qty += qty;
            } else {
                cart.push(cartItem);
            }
        }

        $("#buyQueryModal").removeData("edit-index");
        $("#buyQueryModal").modal("hide");
        updateCartDisplay();
    }

    function addVariantToCart(id, name, variant, price, cartType = 'product') {
        const displayName = `${name} (${variant})`;
        // Wait until Bootstrap finishes hiding the first modal before opening the next.
        $('#variantModal').one('hidden.bs.modal', function () {
            if (['carwash', 'detailing'].includes(cartType)) {
                showCarwashModal(id, displayName, price, cartType, variant);
            } else {
                showBuyQueryModal(id, displayName, price);
            }
        }).modal('hide');
    }

    function showVariantModal(id, name, namaVar, biayaVar, cartType = 'product') {
        const varNames = String(namaVar || '').split(';');
        const varPrices = String(biayaVar || '').split(';');
        const options = $('#variant-options').empty();
        varNames.forEach(function (value, i) {
            const varName = value.trim();
            const price = parseInt(varPrices[i], 10);
            if (!varName || !Number.isFinite(price) || price <= 0) return;
            const button = $('<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">');
            button.append($('<span>').text(`${name} - ${varName}`));
            button.append($('<strong>').text(rupiah(price)));
            button.on('click', function () { addVariantToCart(id, name, varName, price, cartType); });
            options.append(button);
        });
        $('#variantModal').modal('show');
    }

    function showCarwashModal(id, name, price, cartType = 'carwash', variantName = '') {
        $('#carwash-variant').val(variantName);
        $('#carwash-type').val(cartType);
        $('#carwashModalLabel').text(cartType === 'detailing' ? 'Detailing Service' : 'Carwash Service');
        $('#carwash-id').val(id);
        $('#carwash-name').val(name);
        $('#carwash-price').val(price);
        $('#nopol').val('');
        $('#service').val('');
        $('#ukuran').val('');
        $('input[name="vacuum"][value="no"]').prop('checked', true);
        updateCarwashTotal();
        $('#carwashModal').modal('show');
    }

    // Live update total when price or vacuum option changes
    $('#carwash-price, input[name="vacuum"]').on('input change', function() {
        updateCarwashTotal();
    });

    function updateCarwashTotal() {
        let basePrice = parseInt($('#carwash-price').val()) || 0;
        let vacuumPrice = $('input[name="vacuum"]:checked').val() === 'yes' ? 5000 : 0;
        let total = basePrice + vacuumPrice;
        $('#carwash-total').text(total.toLocaleString('id-ID'));
    }

    // On Save
    $('#carwash-form').on('submit', function(e) {
        e.preventDefault();
        addCarwashToCart(false); // normal save
        $('#carwashModal').modal('hide');
    });

    // On Hold
    $('#carwash-hold').on('click', function() {
        addCarwashToCart(true); // add as held
        $('#carwashModal').modal('hide');
    });

    function addCarwashToCart(isHold) {
        const id = $('#carwash-id').val();
        const name = $('#carwash-name').val();
        const price = parseInt($('#carwash-price').val()) || 0;
        const vacuum = $('input[name="vacuum"]:checked').val();
        const vacuumPrice = vacuum === 'yes' ? 5000 : 0;
        const finalPrice = price + vacuumPrice;

        const nopol = $('#nopol').val();
        const service = $('#service').val();
        const ukuran = $('#ukuran').val();

        const cartItem = {
            id,
            name: name,
            qty: 1,
            unitPrice: vacuum ? finalPrice : price,
            finalPrice: finalPrice,
            variantName: $('#carwash-variant').val() || '',
            nopol: nopol,
            service: service,
            ukuran: ukuran,
            vacuum: vacuum,
            cartType: $('#carwash-type').val() === 'detailing' ? 'detailing' : 'carwash'
        };

        if (isHold) {
            cartItem.hold = true;
            cartItem.notes = vacuum === 'yes' ? `Vacuum: Ya` : 'Vacuum: Tidak';
        } else {
            cartItem.hold = false;
            cartItem.notes = `NoPol: ${nopol}, Service: ${service}, Ukuran: ${ukuran}, Vacuum: ${vacuum === 'yes' ? 'Ya' : 'Tidak'}`;
        }

        cart.push(cartItem);
        updateCartDisplay();
    }

    $(document).ready(function() {
        $('.main-footer').remove(); // remove existing footer

        updateCartDisplay();

        // Category filter
        $('#categoryTabs .nav-link').click(function(e) {
            e.preventDefault();
            const category = $(this).data('category');
            $('#categoryTabs .nav-link').removeClass('active');
            $(this).addClass('active');

            $('#product-list > div').each(function() {
                const cat = $(this).data('category');
                if (category == 'all' || category == cat) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Search filter
        $('#product-search').on('keyup', function() {
            const keyword = $(this).val().toLowerCase();
            $('#product-list > div').each(function() {
                const name = $(this).data('name');
                $(this).toggle(name.includes(keyword));
            });
        });
    });
</script>
<style>
@media print {
  body {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    width: 80mm; /* matches 80mm thermal paper */
    margin: 0;
    padding: 0;
  }

  h3, h4, p {
    margin: 0;
    text-align: center;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }

  th, td {
    text-align: left;
    padding: 2px 0;
  }

  th {
    border-bottom: 1px dashed #000;
  }

  tfoot td {
    border-top: 1px dashed #000;
    font-weight: bold;
  }

  .totals {
    margin-top: 5px;
    border-top: 1px dashed #000;
    border-bottom: 1px dashed #000;
    padding: 4px 0;
  }

  @page {
    margin: 5mm;
  }
}
</style>

<script>
$(function () {
  function parseMoney(value) {
    return parseInt(String(value || '').replace(/[^0-9]/g, ''), 10) || 0;
  }

  function formatMoney(value) {
    return `Rp ${Math.max(0, parseInt(value || 0, 10)).toLocaleString('id-ID')}`;
  }


  function parsePercent(value) {
    return Math.min(100, Math.max(0, parseMoney(value)));
  }

  function discountAmount(subtotal, percent) {
    return Math.floor(subtotal * percent / 100);
  }
  function cartSubtotal() {
    return (JSON.parse(localStorage.getItem('cart')) || []).reduce((sum, item) => {
      const price = parseInt(item.finalPrice || item.unitPrice || 0, 10) || 0;
      const qty = parseInt(item.qty || 1, 10) || 1;
      return sum + (price * qty);
    }, 0);
  }

  function isNonCash(method) {
    return method === 'credit_card' || method === 'qris';
  }

  function updatePaymentSummary() {
    const subtotal = cartSubtotal();
    let discountPercent = parsePercent($("#payment-discount").val());
    const method = $("#payment-method").val();

    if (discountPercent > 100) {
      discountPercent = 100;
      $("#payment-discount").val("100");
    }

    const discount = discountAmount(subtotal, discountPercent);
    const grandTotal = Math.max(0, subtotal - discount);
    $("#modal-subtotal").val(formatMoney(subtotal));
    $("#modal-total").val(formatMoney(grandTotal));

    if (isNonCash(method)) {
      $("#customer-pay").val(grandTotal.toLocaleString("id-ID"));
      $("#customer-pay").prop("readonly", true);
      $(".quick-pay").prop("disabled", true);
    } else {
      $("#customer-pay").prop("readonly", false);
      $(".quick-pay").prop("disabled", false);
    }

    let paid = parseMoney($("#customer-pay").val());
    const change = Math.max(0, paid - grandTotal);
    $("#change-amount").val(formatMoney(change));
  }

  $('#payNow').off('click').on('click', function () {
    const orderItems = JSON.parse(localStorage.getItem('cart')) || [];
    if (!orderItems.length) {
      Swal.fire({ icon: 'warning', title: 'Cart Empty', text: 'Please add items before payment.' });
      return;
    }

    const activeTableNumber = localStorage.getItem('activeTableNumber') || '';
    $('#table-number').val(activeTableNumber);
    $('#payment-method').val('cash');
    $('#payment-discount').val('');
    $('#customer-pay').val('');
    $('#change-amount').val('');
    updatePaymentSummary();
    $('#payModal').modal('show');
  });

  $("#payment-discount").off("input").on("input", function () {
    const percent = parsePercent($(this).val());
    $(this).val(percent ? String(percent) : "");
    updatePaymentSummary();
  });

  $("#customer-pay").off("input").on("input", function () {
    if (isNonCash($("#payment-method").val())) return;
    const paid = parseMoney($(this).val());
    $(this).val(paid ? paid.toLocaleString("id-ID") : "");
    updatePaymentSummary();
  });
  $('#payment-method').off('change').on('change', updatePaymentSummary);

  $('.quick-pay').off('click').on('click', function () {
    if (isNonCash($('#payment-method').val())) return;
    const current = parseMoney($('#customer-pay').val());
    const amount = parseInt($(this).data('amount'), 10) || 0;
    $('#customer-pay').val((current + amount).toLocaleString('id-ID'));
    updatePaymentSummary();
  });

  $("#payForm").off("submit").on("submit", async function (e) {
    e.preventDefault();

    const tableNumber = $('#table-number').val().trim();
    const paymentMethod = $('#payment-method').val();
    const subtotal = cartSubtotal();
    const discountPercent = parsePercent($("#payment-discount").val());
    const discount = discountAmount(subtotal, discountPercent);
    const grandTotal = Math.max(0, subtotal - discount);
    let paid = parseMoney($('#customer-pay').val());
    const change = Math.max(0, paid - grandTotal);
    const orderItems = JSON.parse(localStorage.getItem('cart')) || [];

    if (!tableNumber) {
      Swal.fire({ icon: 'warning', title: 'Table Required', text: 'Please enter a table number.' });
      return;
    }

    if (!paymentMethod) {
      Swal.fire({ icon: 'warning', title: 'Payment Required', text: 'Please select a payment method.' });
      return;
    }


    try {
      await connectBluetoothPrinter("cashier");
    } catch (error) {
      Swal.fire({ icon: "warning", title: "Cashier Printer Required", text: error.message || "Please connect and save the Cashier printer before payment." });
      return;
    }
    if (discountPercent > 100) {
      Swal.fire({ icon: 'warning', title: 'Invalid Discount', text: 'Discount percentage cannot be greater than 100%.' });
      return;
    }

    if (isNonCash(paymentMethod)) {
      paid = grandTotal;
      $("#customer-pay").val(grandTotal.toLocaleString("id-ID"));
      updatePaymentSummary();
    }

    if (paymentMethod === 'cash' && paid < grandTotal) {
      Swal.fire({ icon: 'warning', title: 'Insufficient Payment', text: 'Customer pay cannot be lower than grand total.' });
      return;
    }

    $.post('/include/transaksi/save_order', {
      tableNumber,
      paymentMethod,
      subtotal,
      discount,
      discountPercent,
      total: grandTotal,
      paid,
      change,
      items: JSON.stringify(orderItems)
    }, async function (response) {
      if (response && response.status === 'success') {
        await printInvoice(tableNumber, orderItems, subtotal, discountPercent, discount, grandTotal, paid, change, paymentMethod);
        localStorage.removeItem('cart');
        cart = [];
        updateCartDisplay();
        $('#payModal').modal('hide');
      } else {
        Swal.fire({ icon: 'error', title: 'Payment Failed', text: (response && response.message) || 'Unable to save transaction.' });
      }
    }, 'json').fail(function () {
      Swal.fire({ icon: 'error', title: 'Payment Failed', text: 'Unable to save transaction.' });
    });
  });

  if (!window.DejatiBluetoothPrinter) {
    console.error("Bluetooth printer manager is not loaded.");
  }

  function showPrinterSetupError(error) {
    Swal.fire({
      icon: "warning",
      title: "Printer Setting Required",
      text: error.message || "Connect and save the Cashier and Kitchen printers once before printing."
    });
  }

  function requirePrinterManager() {
    if (!window.DejatiBluetoothPrinter) {
      throw new Error("Printer script is not loaded. Refresh the app once and try again.");
    }
    return window.DejatiBluetoothPrinter;
  }

  async function connectBluetoothPrinter(role, forceChooser = false) {
    const manager = requirePrinterManager();
    return forceChooser ? manager.setup(role) : manager.connect(role);
  }

  async function writeEscposToPrinter(role, escpos) {
    await requirePrinterManager().write(role, escpos);
  }

  async function printBluetoothJobsSequentially(jobs) {
    await requirePrinterManager().writeSequential(jobs);
  }

  function waitForPrinter(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  function buildCashierReceipt(tableNumber, items, subtotal, discountPercent, discount, grandTotal, paid, change, method) {
    let escpos = "\x1B\x40\x1B\x61\x01";
    escpos += "Dejati Coffee Garden\nIG: instagram.com/dejati.coffee\nWifi: Dejati\nPassword: dejati37\n";
    escpos += "-----------------------------\n";
    escpos += `CASHIER DEJATI\nInvoice\nTable: ${tableNumber}\n-----------------------------\n`;

    items.forEach(row => {
      const name = row.name || "-";
      const qty = row.qty || 1;
      const price = parseInt(row.finalPrice || row.unitPrice || 0, 10) || 0;
      const totalItem = (price * qty).toLocaleString("id-ID");
      escpos += `${name} x${qty} Rp ${totalItem}\n`;
      if (row.notes) escpos += `  ${row.notes}\n`;
    });

    escpos += "-----------------------------\n";
    escpos += `Subtotal: Rp ${parseInt(subtotal).toLocaleString("id-ID")}\n`;
    escpos += `Discount (${parseInt(discountPercent)}%): Rp ${parseInt(discount).toLocaleString("id-ID")}\n`;
    escpos += `Total: Rp ${parseInt(grandTotal).toLocaleString("id-ID")}\n`;
    escpos += `Bayar: Rp ${parseInt(paid).toLocaleString("id-ID")}\n`;
    escpos += `Kembali: Rp ${parseInt(change).toLocaleString("id-ID")}\n`;
    escpos += `Metode: ${method}\n`;
    escpos += "-----------------------------\n";
    escpos += "Terima kasih atas kunjungannya!\nSilakan datang kembali\n\n\n";
    escpos += "\x1D\x56\x00";
    return escpos;
  }

  function buildChitReceipt(title, tableNumber, items) {
    let escpos = "\x1B\x40\x1B\x61\x01";
    escpos += "Dejati Coffee Garden\n";
    escpos += "-----------------------------\n";
    escpos += `${title}\nTable: ${tableNumber}\n-----------------------------\n`;

    items.forEach(row => {
      const name = row.name || "-";
      const qty = row.qty || 1;
      escpos += `${name} x${qty}\n`;
      if (row.orderType) escpos += `  Type: ${row.orderType}\n`;
      if (row.notes) escpos += `  Notes: ${row.notes}\n`;
    });

    escpos += "-----------------------------\n";
    escpos += "\n\n\n";
    escpos += "\x1D\x56\x00";
    return escpos;
  }

  async function printChitCopies(tableNumber, items) {
    const results = [];

    try {
      await writeEscposToPrinter("cashier", buildChitReceipt("CASHIER CHIT", tableNumber, items));
      results.push("Cashier OK");
    } catch (error) {
      results.push(`Cashier failed: ${error.message || "unknown error"}`);
    }

    await waitForPrinter(1000);

    try {
      await writeEscposToPrinter("kitchen", buildChitReceipt("KITCHEN CHIT", tableNumber, items));
      results.push("Kitchen OK");
    } catch (error) {
      results.push(`Kitchen failed: ${error.message || "unknown error"}`);
    }

    if (results.some(result => result.indexOf("failed") !== -1)) {
      throw new Error(results.join(" | "));
    }

    return results;
  }

  async function printInvoice(tableNumber, items, subtotal, discountPercent, discount, grandTotal, paid, change, method) {
    try {
      await writeEscposToPrinter("cashier", buildCashierReceipt(tableNumber, items, subtotal, discountPercent, discount, grandTotal, paid, change, method));

      const result = await Swal.fire({
        icon: "question",
        title: "Print Chit?",
        text: "Invoice has been sent to cashier. Print chit copies to cashier and kitchen now?",
        showCancelButton: true,
        confirmButtonText: "Print Chit",
        cancelButtonText: "Skip"
      });

      if (result.isConfirmed) {
        await printChitCopies(tableNumber, items);
        Swal.fire({ icon: "success", title: "Chit Printed", text: "Chit sent to cashier and kitchen printers." });
      }
    } catch (error) {
      Swal.fire({ icon: "error", title: "Print Failed", text: error.message || "Unable to print to Bluetooth printer." });
    }
  }


  if (window.DejatiBluetoothPrinter) window.DejatiBluetoothPrinter.refreshStatus();

  function clearCurrentTransaction() {
    localStorage.removeItem('cart');
    localStorage.removeItem('activeTableNumber');
    cart = [];
    $('#order-table tbody').empty();
    $('#total-amount').text('Rp 0');
    $('#table-number').val('');
    $('#payment-method').val('');
    $('#payment-discount').val('');
    $('#customer-pay').val('');
    $('#change-amount').val('');
    $('#open-table-number').val('');
  }

$('#clearCart').on('click', function() {
    if (confirm('Hapus Transaksi Ini?')) {
        clearCurrentTransaction();
    }
  });

  $('#openBill').off('click').on('click', function () {
    $('#open-table-number').val('');
    $('#openBillModal').modal('show');
  });

  $('#openBillForm').off('submit').on('submit', function (e) {
    e.preventDefault();

    const tableNumber = $('#open-table-number').val().trim();
    const subtotal = cartSubtotal();
    const orderItems = JSON.parse(localStorage.getItem('cart')) || [];

    if (!orderItems.length) {
      Swal.fire({ icon: 'warning', title: 'Cart Empty', text: 'Please add items before opening a bill.' });
      return;
    }

    $.post('/include/transaksi/save_order', {
      tableNumber,
      subtotal,
      discount: 0,
      discountPercent: 0,
      paid: 0,
      change: 0,
      total: subtotal,
      items: JSON.stringify(orderItems),
      status: 'open_bill'
    }, function (response) {
      if (response && response.status === 'success') {
        Swal.fire({ icon: 'success', title: 'Bill Opened!', text: `Table ${tableNumber} has been saved.`, confirmButtonColor: '#17a2b8' });
        $('#openBillModal').modal('hide');
        clearCurrentTransaction();
      } else {
        Swal.fire({ icon: 'error', title: 'Failed', text: (response && response.message) || 'Unable to save open bill.' });
      }
    }, 'json').fail(function () {
      Swal.fire({ icon: 'error', title: 'Failed', text: 'Unable to save open bill.' });
    });
  });

  console.log('Cart loaded:', JSON.parse(localStorage.getItem('cart')));
  const activeTableNumber = localStorage.getItem('activeTableNumber') || '';
  if (activeTableNumber) {
    $('#table-number').val(activeTableNumber);
    $('#open-table-number').val(activeTableNumber);
  }
});
</script>
