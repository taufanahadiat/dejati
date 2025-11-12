<?php
date_default_timezone_set("Asia/Jakarta");

$breadcrumb = [
    ['label' => 'History Transaksi', 'link' => '#'],
];

// Query orders
$sql = "SELECT * FROM orders ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
?>


<!-- Flatpickr DateTime Picker -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- Content Wrapper -->
<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card card-outline card-dark">
                <div class="card-body">

                    <!-- 🔎 Filters -->
                    <div class="row mb-3 align-items-end">
  <div class="col-md-4">
    <label for="dateRange"><strong>Filter by Date</strong></label>
    <input type="text" id="dateRange" class="form-control" placeholder="Pilih tanggal dan waktu">
  </div>
  <div class="col-md-2">
    <button id="applyDateFilter" class="btn btn-primary btn-block mt-4">
      <i class="fas fa-filter"></i> Apply
    </button>
  </div>
  <div class="col-md-2">
    <button id="resetFilter" class="btn btn-outline-secondary btn-block mt-4">
      <i class="fas fa-undo"></i> Show All
    </button>
  </div>
  <div class="col-md-4 text-right">
    <button id="printClosinganBtn" class="btn btn-info mt-4">
      <i class="fas fa-print"></i> Print Closingan
    </button>
  </div>
</div>



<!-- Modal -->
<div class="modal fade" id="closinganModal" tabindex="-1" role="dialog" aria-labelledby="closinganModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Preview Closingan Hari Ini</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div id="closinganPreview" class="p-2 border rounded bg-light">
          <p>Loading data...</p>
        </div>

        <hr>
        <h6>Apakah ada pengeluaran hari ini?</h6>
        <div id="pengeluaranList"></div>

        <button id="addPengeluaran" class="btn btn-outline-primary btn-sm mt-2">+ Tambah Pengeluaran</button>
      </div>
      <div class="modal-footer">
        <button id="confirmPrintClosingan" class="btn btn-success">Print & Simpan</button>
        <button class="btn btn-secondary" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

                    <!-- 📋 Orders Table -->
                    <table id="ordersTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Table</th>
                                <th>Payment</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Change</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= $row['created_at'] ?></td>
                                    <td><?= $row['table_number'] ?></td>
                                    <td><?= ucfirst($row['payment_method']) ?></td>
                                    <td><?= number_format($row['total_amount'], 0, ",", ".") ?></td>
                                    <td><?= number_format($row['paid_amount'], 0, ",", ".") ?></td>
                                    <td><?= number_format($row['change_amount'], 0, ",", ".") ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info view-details" data-id="<?= $row['id'] ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if ($row['paid_amount'] == 0): ?>
                                            <button id="btnTransact" class="btn btn-sm btn-success transact" data-id="<?= $row['id'] ?>">
                                                <i class="fas fa-cash-register"></i> Transact
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 📦 Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="modalContent">
                <p class="text-center">Loading...</p>
            </div>
        </div>
    </div>
</div>

<!-- 💵 Transact Modal -->
<div class="modal fade" id="transactModal" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header bg-success">
        <h5 class="modal-title">Finalize Transaction</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <form id="transactForm">
          <input type="hidden" name="id" id="transact_id">
          <div class="form-group">
            <label>Total Amount</label>
            <input type="text" class="form-control" id="transact_total" readonly>
          </div>
          <div class="form-group">
            <label>Payment Method</label>
            <select class="form-control" id="transact_method" required>
              <option value="cash">Cash</option>
              <option value="qris">QRIS</option>
              <option value="debit">Debit</option>
            </select>
          </div>
          <div class="form-group">
            <label>Paid Amount</label>
            <input type="number" class="form-control" id="transact_paid" required>
          </div>
          <div class="form-group">
            <label>Change</label>
            <input type="text" class="form-control" id="transact_change" readonly>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" id="confirmTransact">
          <i class="fas fa-check"></i> Confirm & Print
        </button>
      </div>
    </div>
  </div>
</div>



<!-- JS -->
<script src="../../plugins/jquery/jquery.min.js"></script>
<script src="../../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../../plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="../../plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="../../plugins/jszip/jszip.min.js"></script>
<script src="../../plugins/pdfmake/pdfmake.min.js"></script>
<script src="../../plugins/pdfmake/vfs_fonts.js"></script>
<script src="../../plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="../../plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="../../plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<!-- <script src="../../plugins/moment/moment.min.js"></script>
<script src="../../plugins/daterangepicker/daterangepicker.js"></script> -->


<script>
$(function () {
  // === PRINT CLOSINGAN BUTTON ===
  $('#printClosinganBtn').on('click', function () {
    $('#closinganModal').modal('show');
    loadClosinganData();
  });

  // === LOAD DATA PREVIEW ===
  function loadClosinganData() {
    $('#closinganPreview').html('<p>Loading...</p>');
    $.get('/closingan/', function (data) {
      $('#closinganPreview').html(data);
    });
  }

  // === ADD PENGELUARAN INPUT FIELD ===
  $('#addPengeluaran').on('click', function () {
    $('#pengeluaranList').append(`
      <div class="input-group mb-2 pengeluaran-item">
        <input type="text" class="form-control keterangan" placeholder="Keterangan">
        <input type="number" class="form-control total" placeholder="Total (Rp)">
        <div class="input-group-append">
          <button class="btn btn-danger remove-pengeluaran" type="button">&times;</button>
        </div>
      </div>
    `);
  });

  // === REMOVE PENGELUARAN ROW ===
  $(document).on('click', '.remove-pengeluaran', function () {
    $(this).closest('.pengeluaran-item').remove();
  });

  // === PRINT & SAVE CLOSINGAN ===
  $('#confirmPrintClosingan').on('click', function () {
    const pengeluaran = [];

    $('.pengeluaran-item').each(function () {
      const keterangan = $(this).find('.keterangan').val();
      const total = $(this).find('.total').val();
      if (keterangan && total) {
        pengeluaran.push({ keterangan, total });
      }
    });

    $.post('/pengeluaran/', { data: JSON.stringify(pengeluaran) }, function (res) {
      console.log('Pengeluaran saved:', res);

      // After pengeluaran saved, get closingan data again and print
      $.get('/closingan?print=true', function (escpos) {
        const base64Data = btoa(unescape(encodeURIComponent(escpos)));
        const rawbtUrl = `rawbt:base64,${base64Data}`;
        window.location.href = rawbtUrl;
        $('#closinganModal').modal('hide');
      });
    });
  });
});
</script>


<script>
    $(function() {        
$(function(){
  // Delegate click: each row's .transact has data-id
  $(document).on('click', '.transact', function (e) {
    e.preventDefault();
    const orderId = $(this).data('id');
    if (!orderId) return alert('No order id');

    // Fetch JSON from server endpoint
    $.get('/order_get_json/', { id: orderId })
      .done(function (data) {
        // create a form and POST JSON to the transaksi page (browser navigation)
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/main?id=transaksi';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'order_details';
        input.value = JSON.stringify(data);
        form.appendChild(input);

        // also send order id and optionally totals if you want
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'order_id';
        idInput.value = orderId;
        form.appendChild(idInput);

        document.body.appendChild(form);
        form.submit();
      });
  });
});

        // DataTable
        let table = $("#ordersTable").DataTable({
  "responsive": true,
  "lengthChange": true,
  "autoWidth": false,
  "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"],
  "order": [[0, "desc"]] // ✅ Sort by Date (first column) descending
});

        table.buttons().container().appendTo('#ordersTable_wrapper .col-md-6:eq(0)');

// === FLATPICKR DATE RANGE (DATE ONLY) ===
// Force local (Asia/Jakarta) date, independent of device/browser quirks
// === FLATPICKR DATE RANGE (DATE ONLY) ===
// Force local (Asia/Jakarta) date handling
function getLocalDate(offsetDays = 0) {
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  d.setDate(d.getDate() + offsetDays);
  return d.toISOString().split('T')[0]; // "YYYY-MM-DD"
}

const yesterday = getLocalDate(-1);
const today = getLocalDate(0);

const datePicker = flatpickr("#dateRange", {
  mode: "range",
  dateFormat: "Y-m-d",
  defaultDate: [yesterday, today], // ✅ preselect yesterday → today
  locale: { firstDayOfWeek: 1 },
  onReady: function(selectedDates, dateStr, instance) {
    $('#dateRange').val(`${yesterday} to ${today}`);
    applyDateFilter(yesterday, today); // ✅ auto apply on load
  }
});

// === APPLY FILTER BUTTON ===
$('#applyDateFilter').on('click', function() {
  const range = $('#dateRange').val();
  if (!range.includes(' to ')) {
    alert('Please select a valid date range.');
    return;
  }

  const [start, end] = range.split(' to ');
  applyDateFilter(start, end);
});

// === RESET FILTER BUTTON ===
$('#resetFilter').on('click', function() {
  $('#dateRange').val('');
  table.rows().every(function() {
    $(this.node()).show();
  });
});


// === FILTER FUNCTION ===
function applyDateFilter(start, end) {
  const startDate = new Date(start);
  const endDate = new Date(end);
  endDate.setHours(23, 59, 59, 999); // include full day

  table.rows().every(function() {
    const dateStr = this.data()[0]; // first column = created_at
    const rowDate = new Date(dateStr);

    if (rowDate >= startDate && rowDate <= endDate) {
      $(this.node()).show();
    } else {
      $(this.node()).hide();
    }
  });
}


        // Modal Details Loader
        // ✅ Event delegation to support new DataTable rows
        $(document).on("click", ".view-details", function() {
            let orderId = $(this).data("id");
            $("#modalContent").html("<p class='text-center'>Loading...</p>");
            $("#detailsModal").modal("show");

            $.get("/order_get/", { id: orderId }, function(data) {
                $("#modalContent").html(data);
            });
        });
    });
</script>
</body>

</html>