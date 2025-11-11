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

        // Listen to Discount Switch
        $('#discountPercent').on('switchChange.bootstrapSwitch', function(event, state) {
            if (state) {
                $('#discountPercent').val('percent');
            } else {
                $('#discountPercent').val('rp');
            }
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
            let rowTotal = item.finalPrice * item.qty;
            total += rowTotal;

            // Base price
            let priceHtml = `<div>${item.unitPrice.toLocaleString('id-ID')}</div>`;

            // Show discount text if applied
            if (item.discountValue > 0) {
                if (item.discountType === 'percent') {
                    priceHtml += `
                    <div style="font-size:11px; color:gray;">
                        Disc ${item.discountValue}%<br>${item.finalPrice.toLocaleString('id-ID')}
                    </div>`;
                } else {
                    priceHtml += `
                    <div style="font-size:11px; color:gray;">
                        Disc ${item.discountValue.toLocaleString('id-ID')}<br>${item.finalPrice.toLocaleString('id-ID')}
                    </div>`;
                }
            }

            let itemName = item.name;

            if (item.cartType === 'carwash' && item.hold) {
                itemName = `<span class="text-danger">${itemName}</span>`;
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
        $('#orderTypeSwitch').val(item.orderType || 'dine-in');

        // restore discount if available
        if (item.discountValue && item.discountValue > 0) {
            $('#discountValue').val(item.discountValue);

            if (item.discountType === 'percent') {
                $('#discountPercent').bootstrapSwitch('state', true); // %
            } else {
                $('#discountPercent').bootstrapSwitch('state', false); // fixed Rp
            }
        } else {
            $('#discountValue').val('');
            $('#discountPercent').bootstrapSwitch('state', false);
        }

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
        $('#discountValue').val('');
        $('#buyNotes').val('');
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
        let qty = parseInt($('#buyQty').val()) || 1;
        let discountValue = parseInt($('#discountValue').val()) || 0;
        let discountType = $('input[name="discountType"]:checked').val(); // use icheck radio

        let basePrice = currentProduct.price * qty;
        let finalPrice = basePrice;

        if (discountValue > 0) {
            if (discountType === 'percent') {
                finalPrice -= (basePrice * discountValue / 100);
            } else {
                finalPrice -= discountValue;
            }

            $('#buyQueryPrice').html(`
            <span style="text-decoration: line-through; color: gray; font-size:14px;">
                ${(basePrice).toLocaleString('id-ID')}
            </span>
            <span style="color:#000; font-weight:bold; font-size:16px;">
                ${(finalPrice.toLocaleString('id-ID'))}
            </span>
        `);
        } else {
            $('#buyQueryPrice').html(`
            <span style="color:#000; font-weight:bold; font-size:16px;">
                ${(basePrice).toLocaleString('id-ID')}
            </span>
        `);
        }
    }
    // Trigger recalculation
    $('#buyQty, #discountValue, #discountType').on('input change', updateBuyQueryPrice);

    function changeBuyQty(change) {
        let qty = parseInt($('#buyQty').val()) || 1;
        qty = Math.max(1, qty + change);
        $('#buyQty').val(qty);
        updateBuyQueryPrice();
    }


    function saveBuyQuery() {
        let qty = parseInt($('#buyQty').val());
        let discountValue = parseInt($('#discountValue').val()) || 0;
        let discountType = $('#discountPercent').val();
        let notes = $('#buyNotes').val();
        let orderType = $('#orderTypeSwitch').val();
        let basePrice = currentProduct.price; // original price
        let finalPrice = basePrice; // will adjust if discount applied

        if (discountValue > 0) {
            if (discountType === 'percent') {
                finalPrice -= (basePrice * discountValue / 100);
            } else {
                finalPrice -= discountValue;
            }
        }

        let editIndex = $('#buyQueryModal').data('edit-index');
        let cartItem = {
            id: currentProduct.id,
            name: currentProduct.name,
            unitPrice: basePrice, // original price
            finalPrice: finalPrice, // after discount (per unit)
            discountValue: discountValue, // number only
            discountType: discountType, // "percent" or "amount"
            qty: qty,
            orderType: orderType,
            cartType: 'product', // default type
            notes: notes
        };

        if (editIndex !== undefined && editIndex !== null) {
            // Update existing item
            cart[editIndex] = cartItem;
        } else {
            // Check if same item already exists (same name, unitPrice, discount, orderType, notes)
            let existingIndex = cart.findIndex(item =>
                item.id === cartItem.id &&
                item.name === cartItem.name &&
                item.unitPrice === cartItem.unitPrice &&
                item.finalPrice === cartItem.finalPrice &&
                item.discountValue === cartItem.discountValue &&
                item.discountType === cartItem.discountType &&
                item.orderType === cartItem.orderType &&
                item.notes === cartItem.notes
            );

            if (existingIndex > -1) {
                // Just increase quantity
                cart[existingIndex].qty += qty;
            } else {
                // Push as new item
                cart.push(cartItem);
            }
        }

        $('#buyQueryModal').removeData('edit-index'); // clear edit mode
        $('#buyQueryModal').modal('hide');
        updateCartDisplay();
    }

    // ketika pilih varian → lanjut ke buy query modal
    function addVariantToCart(id, name, variant, price) {
        const displayName = `${name} (${variant})`;
        $('#variantModal').modal('hide');
        showBuyQueryModal(id, displayName, price);
    }


    function showVariantModal(id, name, namaVar, biayaVar) {
        const varNames = namaVar.split(';');
        const varPrices = biayaVar.split(';');

        let html = '';
        for (let i = 0; i < varNames.length; i++) {
            const varName = varNames[i].trim();
            const price = parseInt(varPrices[i].trim());
            html += `
            <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                onclick="addVariantToCart('${id}', '${name}', '${varName}', ${price}); $('#variantModal').modal('hide');">
                <span>${name} - ${varName}</span>
                <strong>${rupiah(price)}</strong>
            </button>`;
        }

        $('#variant-options').html(html);
        $('#variantModal').modal('show');
    }

    function showCarwashModal(id, name, price) {
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
            nopol: nopol,
            service: service,
            ukuran: ukuran,
            vacuum: vacuum,
            cartType: 'carwash'
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
  // === PAY NOW BUTTON ===
  $('#payNow').off('click').on('click', function () {
    const totalText = $('#total-amount').text().replace(/[^0-9]/g, '');
    $('#modal-total').val(`Rp ${parseInt(totalText || 0).toLocaleString('id-ID')}`);
    $('#customer-pay').val('');
    $('#change-amount').val('');
    $('#payModal').modal('show');
  });

  // === CALCULATE CHANGE ===
  $('#customer-pay').off('input').on('input', function () {
    let total = parseInt($('#total-amount').text().replace(/[^0-9]/g, '')) || 0;
    let paid = parseInt($(this).val()) || 0;
    let change = paid - total;
    $('#change-amount').val(`Rp ${change > 0 ? change.toLocaleString('id-ID') : 0}`);
  });

  // === SUBMIT PAYMENT FORM ===
  $('#payForm').off('submit').on('submit', function (e) {
    e.preventDefault();

    const tableNumber = $('#table-number').val();
    const paymentMethod = $('#payment-method').val();
    const total = $('#total-amount').text().replace(/[^0-9]/g, '');
    const paid = $('#customer-pay').val().replace(/[^0-9]/g, '');
    const change = $('#change-amount').val();
    const orderItems = JSON.parse(localStorage.getItem('cart')) || [];

    // Send to server
    $.post('/transaksi_post/', {
      tableNumber,
      paymentMethod,
      paid,
      change,
      total,
      items: JSON.stringify(orderItems)
    }, function (response) {
      console.log('Order saved:', response);
    });

    // Print invoice
    printInvoice(tableNumber, orderItems, total, paid, change, paymentMethod);

    // Close modal
    $('#payModal').modal('hide');
  });

  // === PRINT FUNCTION ===
  function printInvoice(tableNumber, items, total, paid, change, method) {
    let escpos = '\x1B\x40\x1B\x61\x01';
    escpos += 'Dejati Carwash\nJl. Contoh No.123\nTelp: 0812-xxxx-xxxx\n';
    escpos += '-----------------------------\n';
    escpos += `Invoice\nTable: ${tableNumber}\n-----------------------------\n`;

    items.forEach(row => {
      const name = row.name || '-';
      const qty = row.qty || 1;
      const totalItem = (row.finalPrice * row.qty).toLocaleString('id-ID');
      escpos += `${name} x${qty} Rp ${totalItem}\n`;
    });

    escpos += '-----------------------------\n';
    escpos += `Total: Rp ${parseInt(total).toLocaleString('id-ID')}\n`;
    escpos += `Bayar: Rp ${parseInt(paid).toLocaleString('id-ID')}\n`;
    escpos += `Kembali: ${change}\n`;
    escpos += `Metode: ${method}\n`;
    escpos += '-----------------------------\n';
    escpos += 'Terima kasih atas kunjungannya!\nSilakan datang kembali\n\n\n';
    escpos += '\x1D\x56\x00';

    const base64Data = btoa(unescape(encodeURIComponent(escpos)));
    const rawbtUrl = `rawbt:base64,${base64Data}`;
    window.location.href = rawbtUrl;
  }
    //  === CLEAR CART BUTTON ===
  $('#clearCart').on('click', function() {
    if (confirm('Hapus Transaksi Ini?')) {
        localStorage.removeItem('cart'); // remove cart from localStorage
        cart = []; // clear global cart
        $('#order-table tbody').empty(); // clear table rows
        $('#total-amount').text('Rp 0'); // reset total
        $('#table-number').val('');
        $('#payment-method').val('');
        $('#customer-pay').val('');
        $('#change-amount').val('');
        console.log('✅ Cart cleared');
    }
});

// === OPEN BILL HANDLER ===
$('#openBill').off('click').on('click', function () {
  $('#open-table-number').val('');
  $('#open-payment-method').val('Cash');
  $('#openBillModal').modal('show');
});

$('#openBillForm').off('submit').on('submit', function (e) {
  e.preventDefault();

  const tableNumber = $('#open-table-number').val();
  const paymentMethod = 'cash'; // always cash
  const total = $('#total-amount').text().replace(/[^0-9]/g, '');
  const orderItems = JSON.parse(localStorage.getItem('cart')) || [];

  if (!orderItems.length) {
    Swal.fire({
      icon: 'warning',
      title: 'Cart Empty',
      text: 'Please add items before opening a bill.'
    });
    return;
  }

  $.post('/transaksi_post/', {
    tableNumber,
    paymentMethod,
    paid: 0,
    change: 0,
    total,
    items: JSON.stringify(orderItems),
    status: 'open_bill'
  }, function (response) {
    console.log('✅ Open bill saved:', response);
    Swal.fire({
      icon: 'success',
      title: 'Bill Opened!',
      text: `Table ${tableNumber} has been saved.`,
      confirmButtonColor: '#17a2b8'
    });
  }).fail(function (err) {
    console.error('❌ Error:', err);
    Swal.fire({
      icon: 'error',
      title: 'Failed',
      text: 'Unable to save open bill.'
    });
  });

  $('#openBillModal').modal('hide');
});


  // === Just log for debugging ===
  console.log('Cart loaded:', JSON.parse(localStorage.getItem('cart')));
});

// ADDEDD

</script>

