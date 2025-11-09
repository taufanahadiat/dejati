<?php
// Minimal sidebar for Kasir with only transaksi, report, utility
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <!-- Brand Logo -->
  <a href="./main.php?id=transaksi" class="brand-link">
    <span class="brand-text font-weight-light">Cafe - Kasir</span>
  </a>

  <div class="sidebar">
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
        <li class="nav-item">
          <a href="./main.php?id=transaksi" class="nav-link <?= (isset($id) && $id === 'transaksi') ? 'active' : '' ?>">
            <i class="nav-icon fas fa-cash-register"></i>
            <p>Transaksi</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="./main.php?id=report" class="nav-link <?= (isset($id) && $id === 'report') ? 'active' : '' ?>">
            <i class="nav-icon fas fa-chart-line"></i>
            <p>Report</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="./main.php?id=utility" class="nav-link <?= (isset($id) && $id === 'utility') ? 'active' : '' ?>">
            <i class="nav-icon fas fa-tools"></i>
            <p>Utility</p>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>