<?php
date_default_timezone_set("Asia/Jakarta");

$breadcrumb = [
    ['label' => 'History Transaksi', 'link' => '#'],
];

// Query orders
$sql = "SELECT * FROM orders ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!-- Content Wrapper -->
<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card card-outline card-dark">
                <div class="card-body">

                    <!-- 🔎 Filters -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <input type="text" id="dateRange" class="form-control" placeholder="Select Date Range">
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
<script src="../../plugins/moment/moment.min.js"></script>
<script src="../../plugins/daterangepicker/daterangepicker.js"></script>

<script>
    $(function() {        
$(function(){
  // Delegate click: each row's .transact has data-id
  $(document).on('click', '.transact', function (e) {
    e.preventDefault();
    const orderId = $(this).data('id');
    if (!orderId) return alert('No order id');

    // Fetch JSON from server endpoint
    $.get('/order_get_json.php', { id: orderId })
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
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        });
        table.buttons().container().appendTo('#ordersTable_wrapper .col-md-6:eq(0)');

        // Date Range Filter
        $('#dateRange').daterangepicker({
            locale: {
                format: 'YYYY-MM-DD'
            }
        });

        // Filters
        $('#dateRange').on('change', function() {
            let dateRange = $('#dateRange').val();
            table.columns(1).search(dateRange);
            table.draw();
        });

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