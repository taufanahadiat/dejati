<?php require_once __DIR__ . '/cafe_image_helper.php'; ?>
<div class="mb-3">
    <a class="btn btn-primary btn-sm" href="main.php?id=cafeData_add">
        <i class="fas fa-plus"></i> Tambah Data Produk
    </a>
</div>

<div class="table-responsive">
    <table id="cafe_dataTable" class="table table-bordered table-striped table-hover" width="100%">
        <thead class="thead-light">
            <tr>
                <th>No</th>
                <th>Foto</th>
                <th>Produk</th>
                <th>Kategori</th>
                <th>Variant</th>
                <th>Harga Produk</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = mysqli_query($conn, "
                SELECT dc.id_prod, dc.nama_prod, dc.variant, dc.nama_var, dc.biaya, dc.biaya_var, dc.foto, cat.name_cat
                FROM tb_datacafe dc
                LEFT JOIN tb_category cat ON dc.id_cat = cat.id_cat
                ORDER BY dc.nama_prod ASC
            ");

            if (mysqli_num_rows($query) > 0) {
                $no = 1;
                while ($row = mysqli_fetch_assoc($query)) {
                    $isVariant = (int)$row['variant'] === 1;
                    $variantNames = $isVariant ? explode(';', $row['nama_var']) : [];
                    $variantPrices = $isVariant ? explode(';', $row['biaya_var']) : [];

                    $imageUrl = cafe_product_image_url($row['foto'] ?? '');
            ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td>
                            <a href="<?= htmlspecialchars($imageUrl) ?>" target="_blank">
                                <div style="width: 80px; height: 80px; overflow: hidden;">
                                    <img src="<?= htmlspecialchars($imageUrl) ?>" alt="Foto Produk"
                                        class="img-thumbnail elevation-2"
                                        style="width: 100%; height: 100%; object-fit: cover; border-radius: 20%;">
                                </div>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($row['nama_prod']); ?></td>
                        <td><?= htmlspecialchars($row['name_cat'] ?? '-'); ?></td>
                        <td>
                            <?php
                            if ($isVariant) {
                                foreach ($variantNames as $v) {
                                    echo htmlspecialchars($v) . '<br>';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($isVariant) {
                                foreach ($variantPrices as $price) {
                                    echo 'Rp ' . number_format((float)$price, 0, ',', '.') . '<br>';
                                }
                            } else {
                                echo $row['biaya'] ? 'Rp ' . number_format($row['biaya'], 0, ',', '.') : '-';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="main.php?id=cafeData_edit&id_produk=<?= $row['id_prod']; ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="/cafe_data_handler?id_produk=<?= $row['id_prod']; ?>"
                                onclick="return confirm('Yakin ingin menghapus produk ini?');"
                                class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
            <?php
                }
            } else {
                echo '<tr><td colspan="7" class="text-center"><b>Tidak ada data yang tersedia.</b></td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>
<script>
    $(function() {
        let table = $("#cafe_dataTable").DataTable({
            responsive: true,
            lengthChange: true,
            autoWidth: true,
            buttons: ["copy", "excel", "pdf", "print", "colvis"],
            paging: true,
            searching: true,
            ordering: true,
            info: true,
            pageLength: 50, // ðŸ‘ˆ default show 50 entries
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ] // optional
        });

        table.buttons().container().appendTo('#cafe_dataTable_wrapper .col-md-6:eq(0)');
    });
</script>
