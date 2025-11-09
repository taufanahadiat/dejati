<?php
//cek session
session_start();

require_once 'config/config.php';

if (empty($_SESSION['loggedin'])) {
  header("Location: ./");
  die();
} 

$role = $_SESSION['level'] ?? '';

if ($role === 'Kasir') {
  $id = isset($_GET['id']) ? $_GET['id'] : 'transaksi';
  $allowed_pages = ['transaksi', 'report', 'utility'];
  if (!in_array($id, $allowed_pages, true)) {
    $id = 'transaksi';
  }
  $sidebar_partial = '_partials/sidebar_kasir.php';
} elseif ($role === 'Karyawan') {
  $id = isset($_GET['id']) ? $_GET['id'] : 'utility';
  $sidebar_partial = '_partials/sidebar_karyawan.php';
} else {
  $id = isset($_GET['id']) ? $_GET['id'] : 'dashboard';
  $sidebar_partial = '_partials/sidebar.php';
}

?>

  <!DOCTYPE html>
  <html lang="en">

  <?php include('_partials/head.php'); ?>

  <body class="hold-transition sidebar-mini sidebar-collapse layout-fixed layout-navbar-fixed">

    <div class="wrapper">

      <?php include('_partials/navbar.php'); ?>

      <?php include($sidebar_partial); ?>

      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <?php
        if ($id == "dashboard") {
          include_once('include/dashboard.php');
        } elseif ($id == "carwashData") {
          include_once('include/carwash/carwash_data.php');
        } elseif ($id == "cafeData") {
          include_once('include/cafe/cafe_data.php');
        } elseif ($id == "cafeData_add" || $id == "cafeData_edit") {
          include_once('include/cafe/cafe_data_form.php');
        } elseif ($id == "carwashData_add") {
          include_once('include/carwash/carwash_data_add.php');
        } elseif ($id == "report") {
          include_once('include/transaksi/report.php');
        } elseif ($id == "transaksi") {
          include_once('include/transaksi/index.php'); 
        } elseif ($id == "utility") {
          include_once('include/utility/index.php');
        }  else {
          echo "<h1>Page not found!</h1>";
        }
        ?>
      </div>
      <!-- /.content-wrapper -->

      <?php include('_partials/footer.php'); ?>

    </div>
    <!-- ./wrapper -->

    <?php include('_partials/modal.php'); ?>

    <?php include('_partials/js.php'); ?>
    <script>
  $(document).ready(function() {
    const navHeader = document.getElementById('nav-header');
    if (!navHeader) return;

    function renderBreadcrumb(arr) {
      navHeader.innerHTML = '';
      const breadcrumbEl = document.createElement('ol');
      breadcrumbEl.className = 'breadcrumb bg-transparent mb-0 pl-2 p-0 d-flex align-items-center';

      // 🟩 Add the sidebar toggle button FIRST
      const toggleLi = document.createElement('li');
      toggleLi.className = 'nav-item mr-2';
      toggleLi.innerHTML = `
        <a class="nav-link" data-widget="pushmenu" href="#" role="button">
          <i class="fas fa-bars"></i>
        </a>`;
      breadcrumbEl.appendChild(toggleLi);

      // 🟦 Then, loop through breadcrumb items
      arr.forEach((label, index) => {
        const li = document.createElement('li');
        li.classList.add('breadcrumb-item');

        if (index === 0) {
          li.innerHTML = `<span class="h5 mb-0">${label}</span>`;
        } else if (index === arr.length - 1) {
          li.classList.add('active');
          li.innerHTML = `<span>${label}</span>`;
        } else {
          li.innerHTML = `<span>${label}</span>`;
        }

        breadcrumbEl.appendChild(li);
      });

      navHeader.appendChild(breadcrumbEl);
    }

    // Initial render from PHP
    const breadcrumb = <?= json_encode($breadcrumb ?? []) ?>;
    if (breadcrumb.length > 0) {
      const labels = breadcrumb.map(item => item.label);
      renderBreadcrumb(labels);
    }

    // ✅ jQuery way for Bootstrap 4 tabs
    $('[data-toggle="pill"][data-breadcrumb]').on('shown.bs.tab', function(e) {
      try {
        const breadcrumbData = JSON.parse(this.dataset.breadcrumb);
        renderBreadcrumb(breadcrumbData);
      } catch (err) {
        console.error('Invalid breadcrumb JSON', err);
      }
    });
  });
  </script>

  </body>

  </html>

