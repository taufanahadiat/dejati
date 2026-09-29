<?php
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <div class="brand-link d-flex align-items-center">
    <img src="dist/img/logo-only-white.png" class="brand-image img-circle elevation-3" style="opacity: .8">
    <span class="brand-text font-weight-light"><b>De'</b>Jati</span>
  </div>

  <div class="sidebar">
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="image">
        <img src="dist/img/user-no-image-gray.png" class="img img-circle elevation-2" alt="User Image">
      </div>
      <div class="info">
        <a href="#" class="d-block"><?= $_SESSION["nama_user"]; ?></a>
      </div>
    </div>

    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar nav-legacy nav-flat nav-child-indent flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <li class="nav-item">
          <a href="main.php?id=transaksi" class="nav-link <?= (isset($id) && $id === 'transaksi') ? 'active' : '' ?>">
            <i class="nav-icon fas fa-cash-register"></i>
            <p>Transaksi</p>
          </a>
        </li>
        <li class="nav-item has-treeview <?= (isset($id) && in_array($id, ['report', 'salesReport', 'activityLog'], true)) ? 'menu-open' : '' ?>">
          <a href="#" class="nav-link <?= (isset($id) && in_array($id, ['report', 'salesReport', 'activityLog'], true)) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-receipt"></i>
            <p>Report<i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="main.php?id=report" class="nav-link <?= (isset($id) && $id === 'report') ? 'active' : '' ?>"><i class="nav-icon fas fa-history"></i><p>History Transaksi</p></a></li>
            <li class="nav-item"><a href="main.php?id=salesReport" class="nav-link <?= (isset($id) && $id === 'salesReport') ? 'active' : '' ?>"><i class="nav-icon fas fa-chart-line"></i><p>Laporan Penjualan</p></a></li>
            <li class="nav-item"><a href="main.php?id=activityLog" class="nav-link <?= (isset($id) && $id === 'activityLog') ? 'active' : '' ?>"><i class="nav-icon fas fa-clipboard-list"></i><p>Log Aktivitas</p></a></li>
          </ul>
        </li>
      </ul>
    </nav>
  </div>
</aside>
