<?php
$breadcrumb = [
    ['label' => 'Daftar Produk Cafe', 'link' => '#'],
    ['label' => 'Produk', 'link' => '', 'active' => true]
];
?>

<!-- Main content -->
<section class="content">
    <?php if (isset($_SESSION['success'])): ?>
        <?php
        $success = $_SESSION['success'];
        if ($success):
        ?>
            <div id="successMessage" class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i>&nbsp;<?= $success; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card card-dark card-outline mt-2">
                <div class="card-header p-0 border-bottom-0">
                    <ul class="nav nav-tabs" id="produkTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active pl-5 pr-5" id="produk-tab" data-breadcrumb='["Daftar Produk Cafe", "Produk"]' data-toggle="pill" href="#produk" role="tab" aria-controls="produk" aria-selected="true">Produk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link pl-5 pr-5" id="kategori-tab" data-breadcrumb='["Daftar Produk Cafe", "Kategori"]' data-toggle="pill" href="#kategori" role="tab" aria-controls="kategori" aria-selected="false">Kategori</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="produkTabContent">
                        <!-- Produk Tab -->
                        <div class="tab-pane fade show active" id="produk" role="tabpanel" aria-labelledby="produk-tab">
                            <?php include 'cafe_data_table.php'; ?>
                        </div>

                        <!-- Kategori Tab -->
                        <div class="tab-pane fade" id="kategori" role="tabpanel" aria-labelledby="kategori-tab">
                            <?php include 'cafe_category_table.php'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- /.content -->
<script>
    function confirmDialog() {
        return confirm("Data yang dihapus tidak akan bisa dikembalikan. Apakah Anda yakin akan menghapus data ini?");
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Retrieve the last active tab from localStorage
        const lastTab = localStorage.getItem('activeCafeTab');

        // If there is a stored tab, activate it
        if (lastTab === 'kategori') {
            $('#kategori-tab').tab('show');
        } else {
            $('#produk-tab').tab('show');
        }

        // Add event listeners to store the clicked tab
        document.getElementById('produk-tab').addEventListener('click', function() {
            localStorage.setItem('activeCafeTab', 'produk');
        });

        document.getElementById('kategori-tab').addEventListener('click', function() {
            localStorage.setItem('activeCafeTab', 'kategori');
        });
    });
</script>