  <!-- jQuery UI 1.11.4 -->
  <script src="plugins/jquery-ui/jquery-ui.min.js"></script>
  <script>
    $.widget.bridge('uibutton', $.ui.button)
  </script>
  <script src="plugins/moment/moment.min.js"></script>
  <script src="plugins/inputmask/jquery.inputmask.min.js"></script>
  <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="plugins/bootstrap-switch/js/bootstrap-switch.min.js"></script>
  <script src="plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
  <script src="plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
  <script src="plugins/datatables/jquery.dataTables.min.js"></script>
  <script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
  <script src="plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
  <script src="plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
  <script src="plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
  <script src="plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
  <script src="plugins/jszip/jszip.min.js"></script>
  <script src="plugins/pdfmake/pdfmake.min.js"></script>
  <script src="plugins/pdfmake/vfs_fonts.js"></script>
  <script src="plugins/datatables-buttons/js/buttons.html5.min.js"></script>
  <script src="plugins/datatables-buttons/js/buttons.print.min.js"></script>
  <script src="plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
  <script src="dist/js/exceljs.min.js"></script>
  <script src="dist/js/adminlte.js"></script>
  <script src="dist/js/theme.js"></script>
  <script src="plugins/select2/js/select2.full.min.js"></script>
  <script src="plugins/uploadify/swfobject.js"></script>
  <script src="plugins/uploadify/jquery.uploadify.v2.1.4.min.js"></script>
  <script src="plugins/uploadify/jquery.jgrowl_minimized.js"></script>
  <script src="plugins/autocomplete/autocomp.js"></script>
  <script src="plugins/sweetalert2/sweetalert2.all.min.js"></script>
  <script src="plugins/toastr/toastr.min.js"></script>
  <script>
    $(function() {
      $(".select2").select2({ width: '100%' });
      $(".select2bs4").select2({
        theme: "bootstrap4",
        width: '100%'
      });

      if (window.AdminLTETheme) {
        window.AdminLTETheme.refresh();
      }

      if (window.DejatiBluetoothPrinter) {
        window.DejatiBluetoothPrinter.bindStatus({
          cashier: "#navbarCashierPrinterStatus",
          kitchen: "#navbarKitchenPrinterStatus"
        });

        const printerTestText = role => {
          const label = role === "kitchen" ? "KITCHEN" : "CASHIER";
          return `\x1B\x40\x1B\x61\x01${label} PRINTER TEST\nDejati Coffee Garden\n\n\n\x1D\x56\x00`;
        };

        const connectPrinter = async role => {
          const label = role === "kitchen" ? "Kitchen" : "Cashier";
          try {
            await window.DejatiBluetoothPrinter.setup(role);
            Swal.fire({ icon: "success", title: "Printer Saved", text: `${label} printer saved.` });
          } catch (error) {
            Swal.fire({ icon: "error", title: `${label} Printer Failed`, text: error.message || `Unable to connect ${label.toLowerCase()} printer.` });
          }
        };

        const testPrinter = async role => {
          const label = role === "kitchen" ? "Kitchen" : "Cashier";
          try {
            await window.DejatiBluetoothPrinter.write(role, printerTestText(role));
            Swal.fire({ icon: "success", title: `${label} Printed`, text: `Test print sent to ${label.toLowerCase()} printer.` });
          } catch (error) {
            Swal.fire({ icon: "error", title: `${label} Printer Failed`, text: error.message || `Unable to print to ${label.toLowerCase()} printer.` });
          }
        };

        $("#navbarConnectCashierPrinter").off("click").on("click", () => connectPrinter("cashier"));
        $("#navbarConnectKitchenPrinter").off("click").on("click", () => connectPrinter("kitchen"));
        $("#navbarTestCashierPrinter").off("click").on("click", () => testPrinter("cashier"));
        $("#navbarTestKitchenPrinter").off("click").on("click", () => testPrinter("kitchen"));
      } else {
        $("#navbarCashierPrinterStatus").text("Cashier: printer script not loaded");
        $("#navbarKitchenPrinterStatus").text("Kitchen: printer script not loaded");
      }
    });
  </script>
