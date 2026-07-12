  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Toggle sidebar">
          <i class="fas fa-bars"></i>
        </a>
      </li>
      <li class="nav-item d-flex align-items-center" id="nav-header"></li>
    </ul>

    <ul class="navbar-nav ml-auto">
      <li class="nav-item d-flex align-items-center mr-2" id="navbarPrinterStatus">
        <span class="badge badge-light border mr-1 small" id="navbarCashierPrinterStatus">Cashier: not connected</span>
        <span class="badge badge-light border small" id="navbarKitchenPrinterStatus">Kitchen: not connected</span>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#" role="button" aria-label="Printer menu" title="Printer">
          <i class="fas fa-print"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
          <span class="dropdown-item dropdown-header">Bluetooth Printers</span>
          <button type="button" class="dropdown-item" id="navbarConnectCashierPrinter">
            <i class="fas fa-cash-register mr-2"></i> Connect Cashier
          </button>
          <button type="button" class="dropdown-item" id="navbarConnectKitchenPrinter">
            <i class="fas fa-utensils mr-2"></i> Connect Kitchen
          </button>
          <div class="dropdown-divider"></div>
          <button type="button" class="dropdown-item" id="navbarTestCashierPrinter">
            <i class="fas fa-receipt mr-2"></i> Test Cashier
          </button>
          <button type="button" class="dropdown-item" id="navbarTestKitchenPrinter">
            <i class="fas fa-receipt mr-2"></i> Test Kitchen
          </button>
        </div>
      </li>
      <li class="nav-item d-flex align-items-center">
        <button
          type="button"
          class="nav-link btn btn-link theme-toggle"
          data-theme-toggle
          aria-label="Aktifkan dark mode"
          aria-pressed="false"
          title="Ubah tema">
          <span class="theme-toggle-track" aria-hidden="true">
            <i class="fas fa-sun theme-toggle-icon theme-toggle-icon-sun"></i>
            <span class="theme-toggle-thumb"></span>
            <i class="fas fa-moon theme-toggle-icon theme-toggle-icon-moon"></i>
          </span>
        </button>
      </li>
      <li class="nav-item">
        <a class="nav-link" data-widget="navbar-search" href="#" role="button">
          <i class="fas fa-search"></i>
        </a>
        <div class="navbar-search-block">
          <form class="form-inline">
            <div class="input-group input-group-sm">
              <input class="form-control form-control-navbar" type="search" placeholder="Search" aria-label="Search">
              <div class="input-group-append">
                <button class="btn btn-navbar" type="submit">
                  <i class="fas fa-search"></i>
                </button>
                <button class="btn btn-navbar" type="button" data-widget="navbar-search">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
          </form>
        </div>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-bell"></i>
          <span class="badge badge-warning navbar-badge">15</span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <span class="dropdown-item dropdown-header">15 Notifications</span>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <i class="fas fa-envelope mr-2"></i> 4 new messages
            <span class="float-right text-muted text-sm">3 mins</span>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <i class="fas fa-users mr-2"></i> 8 friend requests
            <span class="float-right text-muted text-sm">12 hours</span>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <i class="fas fa-file mr-2"></i> 3 new reports
            <span class="float-right text-muted text-sm">2 days</span>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
          <i class="fas fa-expand-arrows-alt"></i>
        </a>
      </li>
      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
          <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
              <i class="fas fa-cog"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
              <li class="user-header bg-dark">
                <img src="dist/img/user-no-image-gray.png" class="img img-circle elevation-2" alt="User Image" style="object-fit: cover; object-position: 100% 0%;">
                <p><?= $_SESSION["nama_user"]; ?></p>
                <p><small class="text-muted" style="margin-top: -10px;"><?= $_SESSION['level'] ?></small></p>
              </li>
              <li class="user-footer d-flex justify-content-between">
                <a href="profile.php" class="btn btn-sm btn-outline-secondary">Profile</a>
                <a href="logout.php" class="btn btn-sm btn-outline-danger">Log Out</a>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </ul>
  </nav>
  <!-- /.navbar -->
