<!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <div class="brand-link d-flex align-items-center">
      <img src="<?= CDN_BASE ?>/img/logo-only-white.png" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>De'</b>Jati</span>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <!--<img src="<? //= $userPic; 
                        ?>" class="img img-circle elevation-2" alt="User Image" style="<? //= file_exists($imagePath) ? 'width: 52px; height: 64px; border-radius: 30%;' : 'width: 52px; height: 52px;'; 
                                                                                        ?>">-->
          <img src="<?= CDN_BASE ?>user-no-image-gray.png" class="img img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <a href="#" class="d-block"><?= $_SESSION["nama_user"]; ?></a>
        </div>
      </div>

      <!-- SidebarSearch Form -->
      <div class="form-inline">
        <div class="input-group" data-widget="sidebar-search">
          <input class="form-control form-control-sidebar" type="search" placeholder="Search" aria-label="Search">
          <div class="input-group-append">
            <button class="btn btn-sidebar">
              <i class="fas fa-search fa-fw"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar nav-legacy nav-flat nav-child-indent flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <!-- Add icons to the links using the .nav-icon class
               with font-awesome or any other icon font library -->
          <li class="nav-item">
            <a href="index.php?id=dashboard" class="nav-link">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>
                Dashboard
              </p>
            </a>
          </li>

          <li class="nav-item">
            <a href="#" class="nav-link">
              <i class="nav-icon fas fa-database"></i>
              <p>
                Data Master
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview nav-child-indent">
              <li class="nav-item">
                <a href="main.php?id=cafeData" class="nav-link">
                  <i class="fa fa-utensils nav-icon"></i>
                  <p>Produk Cafe</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="main.php?id=carwashData" class="nav-link">
                  <i class="fa fa-car nav-icon"></i>
                  <p>Produk Carwash</p>
                </a>
              </li>
            </ul>
          </li>
          <li class="nav-item">
            <a href="main.php?id=transaksi" class="nav-link">
              <i class="nav-icon fas fa-cash-register"></i>
              <p>
                Transaksi
              </p>
            </a>
          </li>
          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon fas fa-toilet-paper"></i>
              <p>
                Report
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="main.php?id=report" class="nav-link">
                  <i class="nav-icon fa fa-pen-to-square"></i>
                  <p>History</p>
                </a>
              </li>
            </ul>
          </li>
          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon fas fa-warehouse"></i>
              <p>
                Utility
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="main.php?id=utility" class="nav-link">
                  <i class="nav-icon fa fa-list-check"></i>
                  <p>Utility Management</p>
                </a>
              </li>
            </ul>
          </li>
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const urlParams = new URLSearchParams(window.location.search);
      const currentId = urlParams.get('id');

      // Loop through all nav links
      document.querySelectorAll('.nav-link').forEach(link => {
        const linkHref = link.getAttribute('href');

        if (linkHref && linkHref.includes(`id=${currentId}`)) {
          link.classList.add('active');

          // Also expand parent menu if it's inside a treeview
          let parent = link.closest('.nav-treeview');
          if (parent) {
            parent.style.display = 'block';
            let parentLink = parent.previousElementSibling;
            if (parentLink && parentLink.classList.contains('nav-link')) {
              parentLink.classList.add('active');
            }
          }
        } else {
          link.classList.remove('active'); // Optional: ensure only one is active
        }
      });
    });
  </script>