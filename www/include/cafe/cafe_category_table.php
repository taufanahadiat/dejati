<link rel="stylesheet" href="dist/css/material-symbols.css">
<style>
    /* Raise icon modal above the edit modal */
    #iconModal {
        z-index: 1061 !important;
        /* default modal is 1050, backdrop is 1040 */
    }

    .modal-backdrop.show:nth-of-type(2) {
        z-index: 1060 !important;
    }
</style>


<?php
// Fetch categories
$categories = [];
$sql = "SELECT * FROM tb_category ORDER BY id_cat ASC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>
<div class="row">
    <!-- Left: Add Category Form -->
    <div class="col-md-6">
        <div class="card card-default">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Tambah Group/Kategori</h3>
            </div>
            <div class="card-body">
                <form id="addCategoryForm">
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Nama Grup</label>
                        <div class="col-sm-8">
                            <input type="text" name="name_cat" id="name_cat" class="form-control" placeholder="Masukkan Nama Grup" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Pilih Icon</label>
                        <div class="col-sm-8">
                            <button id="addIconPickerBtn" type="button" class="btn btn-outline-secondary d-inline-flex align-items-center" data-toggle="modal" data-target="#iconModal">
                                <span id="selectedIcon" class="material-symbols-outlined mr-2" style="font-size: 24px;">help</span>
                                <span id="selectedIconLabel">Pilih icon</span>
                            </button>
                            <input type="hidden" name="icon" id="iconInput" value="help">
                        </div>
                    </div>
                    <div class="form-group row mt-2">
                        <div class="col-md-12 text-right">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Simpan Produk
                            </button>
                        </div>
                    </div>
                </form>
                <div id="formResult" class="mt-2 text-success"></div>
            </div>
        </div>
    </div>

    <!-- Right: Category List -->
    <div class="col-md-6">
        <div class="card card-default">
            <div class="card-header">
                <h3 class="card-title">Daftar Kategori</h3>
            </div>
            <div class="card-body p-2" id="categoryListContainer">
                <?php include 'cafe_category_list.php'; ?>
            </div>
        </div>
    </div>

    <!-- Icon Picker Modal -->
    <div class="modal fade" id="iconModal" tabindex="-1" role="dialog" aria-labelledby="iconModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pilih Icon</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="text" id="iconSearch" class="form-control mb-3" placeholder="Cari icon...">
                    <div class="row" id="iconGrid" style="max-height: 400px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const materialIcons = [
            "restaurant", "local_cafe", "store", "category", "shopping_cart", "fastfood",
            "icecream", "emoji_food_beverage", "bakery_dining", "brunch_dining", "coffee",
            "coffee_maker", "kettle", "local_drink", "near_me", "food_bank", "blender",
            "ramen_dining", "takeout_dining", "lunch_dining", "dinner_dining", "local_bar",
            "liquor", "kebab_dining", "wine_bar", "set_meal", "tapas", "egg_alt", "grocery",
            "cake", "cookie", "donut_small", "outdoor_grill", "rice_bowl", "soup_kitchen",
            "nutrition", "emoji_nature", "local_dining", "restaurant_menu", "kitchen",
            "yoshoku", "washoku", "bento", "breakfast_dining", "hot_tub", "spa", "pool", "fitness_center",
            "sports_bar", "cottage", "home", "houseboat", "apartment", "bedroom_baby",
            "bedroom_child", "bedroom_parent", "home_repair_service", "local_florist",
            "local_grocery_store", "local_pharmacy", "local_hospital", "local_library",
            "local_post_office", "local_shipping", "local_taxi", "local_atm", "local_parking",
            "local_printshop", "local_see", "local_activity", "local_airport", "local_pizza"
        ];

        let iconSelectContext = "add";

        function populateIcons(filter = "") {
            const $iconGrid = $("#iconGrid");
            $iconGrid.empty();

            const filtered = materialIcons.filter(icon =>
                icon.toLowerCase().includes(filter.toLowerCase())
            );

            $.each(filtered, function(_, icon) {
                const $col = $(`
                <div class="col-2 text-center mb-3">
                    <div class="border rounded p-2 icon-option" data-icon="${icon}" style="cursor:pointer;">
                        <span class="material-symbols-outlined" style="font-size:24px;">${icon}</span>
                        <div style="font-size:0.75rem;">${icon}</div>
                    </div>
                </div>
            `);
                $iconGrid.append($col);
            });
        }

        $(function() {
            populateIcons();

            $("#addIconPickerBtn").on('click', function() {
                iconSelectContext = "add";
            });

            $("#editIconPickerBtn").on('click', function() {
                iconSelectContext = "edit";
                $("#editCategoryModal").modal("hide");
                setTimeout(() => $("#iconModal").modal("show"), 400);
            });

            $("#iconModal").on("hidden.bs.modal", function() {
                if (iconSelectContext === "edit") {
                    $("#editCategoryModal").modal("show");
                }
            });

            $("#iconSearch").on("input", function() {
                populateIcons($(this).val());
            });

            $("#iconGrid").on("click", ".icon-option", function() {
                const icon = $(this).data("icon");

                if (iconSelectContext === "add") {
                    $("#selectedIcon").text(icon);
                    $("#selectedIconLabel").text(icon);
                    $("#iconInput").val(icon);
                } else {
                    $("#editSelectedIcon").text(icon);
                    $("#editSelectedIconLabel").text(icon);
                    $("#edit_icon").val(icon);
                }

                $("#iconModal").modal("hide");
            });

            $("#addCategoryForm").on("submit", function(e) {
                e.preventDefault();

                $.ajax({
                    url: 'include/cafe/cafe_category_addAct.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        $('#formResult').html('').removeClass("text-danger").addClass("text-success");

                        Swal.fire({
                            toast: true,
                            icon: 'success',
                            title: 'Kategori berhasil ditambahkan',
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500,
                            timerProgressBar: true
                        });

                        $('#addCategoryForm')[0].reset();
                        $('#selectedIcon').text('help');
                        $('#selectedIconLabel').text('Pilih icon');

                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    },
                    error: function() {
                        $('#formResult').html("Terjadi kesalahan saat mengirim data.").addClass("text-danger");
                    }
                });
            });

            $(document).on("click", ".btn-edit", function() {
                const id = $(this).data("id");
                const name = $(this).data("name");
                const icon = $(this).data("icon");

                $("#edit_id_cat").val(id);
                $("#edit_name_cat").val(name);
                $("#edit_icon").val(icon);
                $("#editSelectedIcon").text(icon);
                $("#editSelectedIconLabel").text(icon);

                $("#editCategoryModal").modal("show");
            });

            $("#editCategoryForm").on("submit", function(e) {
                e.preventDefault();

                $.ajax({
                    url: 'include/cafe/cafe_category_editAct.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function() {
                        $('#editCategoryModal').modal('hide');
                        $('#categoryListContainer').load('cafe_category_list.php');
                        Swal.fire({
                            toast: true,
                            icon: 'success',
                            title: 'Kategori diperbarui',
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500,
                            timerProgressBar: true
                        });
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    },
                    error: function() {
                        alert("Gagal menyimpan perubahan.");
                    }
                });
            });

            $(document).on("click", ".btn-delete", function() {
                const id_cat = $(this).data("id");

                Swal.fire({
                    title: 'Hapus Kategori?',
                    text: "Tindakan ini tidak bisa dibatalkan!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, hapus',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'include/cafe/cafe_category_deleteAct.php',
                            method: 'POST',
                            data: {
                                id_cat
                            },
                            success: function() {
                                $('#categoryListContainer').load('cafe_category_list.php');
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil',
                                    text: 'Kategori berhasil dihapus',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            },
                            error: function() {
                                Swal.fire('Gagal', 'Tidak bisa menghapus kategori', 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>