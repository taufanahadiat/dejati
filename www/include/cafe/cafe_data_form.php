<?php
require_once __DIR__ . '/cafe_image_helper.php';
$isEdit = isset($_GET['id_produk']);
$product = null;

if ($isEdit) {
    $id = intval($_GET['id_produk']);
    $stmt = $conn->prepare("SELECT id_prod, nama_prod, id_cat, variant, nama_var, biaya_var, biaya, foto FROM tb_datacafe WHERE id_prod = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $query = $stmt->get_result();
    $product = mysqli_fetch_assoc($query);
}

$breadcrumb = [
    ['label' => 'Daftar Produk Cafe', 'link' => '#'],
    ['label' => 'Produk', 'link' => ''],
    ['label' => $isEdit ? "Edit" : "Tambah", 'link' => '', 'active' => true]
];
?>

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-12 mt-2">
            <div class="card card-dark card-outline">
                <div class="card-header">
                    <h3 class="card-title"><?= $isEdit ? "Edit Produk" : "Tambah Produk Baru"; ?></h3>
                </div>
                <div class="card-body">
                    <button class="btn btn-primary mb-3" onclick="window.location.href='main.php?id=cafeData'">
                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Produk
                    </button>
                    <form class="p-3" id="productForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id_prod" value="<?= $product['id_prod'] ?? '' ?>">

                        <!-- Gambar Produk -->
                        <div class="form-group row" id="imageFormGroup">
                            <label class="col-md-2 col-form-label">Gambar Produk</label>
                            <div class="col-md-6">
                                <?php if ($isEdit && !empty($product['foto'])): ?>
                                    <!-- Show existing image -->
                                    <div id="existingImageWrapper">
                                        <img src="<?= htmlspecialchars(cafe_product_image_url($product['foto'])) ?>" loading="lazy" decoding="async"
                                            alt="Foto Produk"
                                            class="img-thumbnail mb-2"
                                            style="max-height:120px">
                                        <br>
                                        <button type="button" class="btn btn-danger btn-sm" id="removeImageBtn">
                                            <i class="fas fa-trash"></i> Hapus Gambar
                                        </button>
                                    </div>
                                    <input type="hidden" name="foto" id="foto" value="<?= htmlspecialchars($product['foto']) ?>">
                                    <input type="hidden" name="remove_foto" id="remove_foto" value="0">

                                <?php else: ?>
                                    <!-- Dropzone only if no existing image -->
                                    <div id="dropzoneImage" class="dropzone border border-secondary rounded p-2"></div>
                                    <small class="form-text text-muted">Format .JPG, .JPEG, .PNG Ã¢â‚¬â€ Maks. 7 MB</small>
                                    <input type="hidden" name="foto" id="foto">
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Nama Produk -->
                        <div class="form-group row">
                            <label class="col-md-2 col-form-label">Nama Produk<span class="text-danger"> *</span></label>
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="nama_prod"
                                    value="<?= htmlspecialchars($product['nama_prod'] ?? '') ?>"
                                    required>
                            </div>
                        </div>

                        <!-- Kategori -->
                        <div class="form-group row">
                            <label class="col-md-2 col-form-label">Kategori Produk<span class="text-danger"> *</span></label>
                            <div class="col-md-6">
                                <select class="form-control select2bs4" name="id_cat" required>
                                    <option disabled <?= !$isEdit ? 'selected' : '' ?>>Pilih Kategori</option>
                                    <?php
                                    $catQuery = mysqli_query($conn, "SELECT id_cat, name_cat FROM tb_category ORDER BY name_cat ASC");
                                    while ($cat = mysqli_fetch_assoc($catQuery)) {
                                        $selected = ($isEdit && $product['id_cat'] == $cat['id_cat']) ? 'selected' : '';
                                        echo "<option value='{$cat['id_cat']}' $selected>" . htmlspecialchars($cat['name_cat']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!-- Variant -->
                        <div class="form-group row">
                            <label class="col-md-2 col-form-label">Varian</label>
                            <div class="col-md-6">
                                <div class="icheck-success d-inline mr-3">
                                    <input type="radio" name="variant" id="variantYes" value="yes"
                                        <?= $isEdit && $product['variant'] == 1 ? 'checked' : '' ?>>
                                    <label for="variantYes">Ya</label>
                                </div>
                                <div class="icheck-danger d-inline">
                                    <input type="radio" name="variant" id="variantNo" value="no"
                                        <?= $isEdit && $product['variant'] == 0 ? 'checked' : '' ?>>
                                    <label for="variantNo">Tidak</label>
                                </div>
                            </div>
                        </div>

                        <!-- Variant Details -->
                        <?php if ($isEdit && $product['variant'] == 1):
                            $names = explode(";", $product['nama_var']);
                            $prices = explode(";", $product['biaya_var']);
                        endif; ?>
                        <div id="variantDetails" class="<?= $isEdit && $product['variant'] == 1 ? '' : 'd-none' ?>">
                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">Jumlah Varian<span class="text-danger"> *</span></label>
                                <div class="col-md-6">
                                    <input type="number" min="1" class="form-control" id="variantCount"
                                        value="<?= $isEdit && $product['variant'] == 1 ? count($names) : '' ?>"
                                        placeholder="Masukan jumlah varian">
                                </div>
                            </div>
                            <div id="variantInputs">
                                <?php if ($isEdit && $product['variant'] == 1): ?>
                                    <?php foreach ($names as $i => $vName): ?>
                                        <div class="form-group row">
                                            <label class="col-md-2 col-form-label">Variant <?= $i + 1 ?> Name</label>
                                            <div class="col-md-2">
                                                <input type="text" class="form-control" name="variant_name[]"
                                                    value="<?= htmlspecialchars($vName) ?>" required>
                                            </div>
                                            <label class="col-md-2 col-form-label">Price</label>
                                            <div class="col-md-2">
                                                <input type="number" class="form-control" name="variant_price[]"
                                                    value="<?= htmlspecialchars($prices[$i] ?? '') ?>" required>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Harga Non-Variant -->
                        <div id="hargaTokoField" class="<?= $isEdit && $product['variant'] == 0 ? '' : 'd-none' ?>">
                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">Harga Jual di Toko<span class="text-danger"> *</span></label>
                                <div class="col-md-6">
                                    <input type="number" class="form-control" name="harga_toko" id="hargaTokoInput"
                                        value="<?= $isEdit && $product['variant'] == 0 ? htmlspecialchars($product['biaya']) : '' ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="form-group row">
                            <div class="col-md-12 text-right">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> <?= $isEdit ? "Update Produk" : "Simpan Produk" ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- dropzonejs -->
<script src="plugins/dropzone/min/dropzone.min.js"></script>

<script>
    $(function() {
        $("input[data-bootstrap-switch]").each(function() {
            $(this).bootstrapSwitch('state', $(this).prop('checked'));
        });
    });

    Dropzone.autoDiscover = false;

    <?php if (!$isEdit || empty($product['foto'])): ?>
        // Initialize Dropzone only if no existing image
        let imageDropzone = new Dropzone("#dropzoneImage", {
            url: "<?= $isEdit ? '/cafe_data_edit/' : '/cafe_data_handler/' ?>",
            maxFiles: 1,
            maxFilesize: 7,
            acceptedFiles: ".jpg,.jpeg,.png",
            addRemoveLinks: true,
            dictDefaultMessage: "Drop image here or click to upload",
            paramName: "imageFile",
            autoProcessQueue: true,
            parallelUploads: 1,

            init: function() {
                let dz = this;

                dz.on("sending", function(file, xhr, formData) {
                    const uniqueName = "img_" + Date.now() + ".jpg";

                    file.newName = uniqueName;
                    formData.append("foto", uniqueName);
                    formData.append("upload_only", true);
                });

                dz.on("success", function(file, response) {
                    if (response === "success") {
                        $("#foto").val(file.newName);
                    } else {
                        alert("Upload failed: " + response);
                        dz.removeFile(file);
                    }
                });

                dz.on("error", function(file, response) {
                    alert("Upload error: " + response);
                    dz.removeFile(file);
                });

                dz.on("maxfilesexceeded", function(file) {
                    dz.removeAllFiles();
                    dz.addFile(file);
                });
            }
        });
    <?php endif; ?>

    // Handle remove image button in edit mode
    $(document).on("click", "#removeImageBtn", function() {
        if (confirm("Hapus gambar ini?")) {
            $("#existingImageWrapper").remove();
            $("#remove_foto").val("1");
            $("#foto").val(""); // clear db value
            // show dropzone again
            $(".form-group #dropzoneImage").removeClass("d-none");
            if ($("#dropzoneImage").length === 0) {
                $("#imageFormGroup .col-md-6").prepend('<div id="dropzoneImage" class="dropzone border border-secondary rounded p-2"></div>');
                // re-init Dropzone
                new Dropzone("#dropzoneImage", {
                    url: "<?= $isEdit ? '/cafe_data_edit/' : '/cafe_data_handler/' ?>",
                    maxFiles: 1,
                    maxFilesize: 7,
                    acceptedFiles: ".jpg,.jpeg,.png",
                    addRemoveLinks: true,
                    dictDefaultMessage: "Drop image here or click to upload",
                    paramName: "imageFile",
                    autoProcessQueue: true,
                    parallelUploads: 1,
                    init: function() {
                        let dz = this;
                        dz.on("sending", function(file, xhr, formData) {
                            const uniqueName = "img_" + Date.now() + ".jpg";
                            file.newName = uniqueName;
                            formData.append("foto", uniqueName);
                            formData.append("upload_only", true);
                        });
                        dz.on("success", function(file, response) {
                            if (response === "success") {
                                $("#foto").val(file.newName);
                                $("#remove_foto").val("0");
                            }
                        });
                    }
                });
            }
        }
    });

    $(document).ready(function() {
        function toggleVariantFields() {
            if ($("#variantYes").is(":checked")) {
                $("#variantDetails").removeClass("d-none");
                $("#hargaTokoField").addClass("d-none");
                $('#hargaTokoInput').prop('required', false).closest('.form-group').hide();
            } else if ($("#variantNo").is(":checked")) {
                $("#variantDetails").addClass("d-none");
                $("#hargaTokoField").removeClass("d-none");
                $("#variantInputs").empty();
                $("#variantCount").val('');
                $('#hargaTokoInput').prop('required', true).closest('.form-group').show();
            } else {
                $("#variantDetails, #hargaTokoField").addClass("d-none");
                $("#variantInputs").empty();
                $("#variantCount").val('');
            }
        }

        toggleVariantFields();
        $("input[name='variant']").on("change", toggleVariantFields);

        function renderVariantInputs(count, existingNames = [], existingPrices = []) {
            let container = $("#variantInputs");
            container.empty();

            for (let i = 0; i < count; i++) {
                let vName = existingNames[i] ?? '';
                let vPrice = existingPrices[i] ?? '';

                container.append(`
                <div class="form-group row">
                    <label class="col-md-2 col-form-label">Variant ${i + 1} Name</label>
                    <div class="col-md-2">
                        <input type="text" class="form-control" name="variant_name[]" 
                            value="${vName}" required>
                    </div>
                    <label class="col-md-2 col-form-label">Price</label>
                    <div class="col-md-2">
                        <input type="number" class="form-control" name="variant_price[]" 
                            value="${vPrice}" required>
                    </div>
                </div>
            `);
            }
        }

        // When variant count changes
        $("#variantCount").on("input", function() {
            let count = parseInt($(this).val()) || 0;
            renderVariantInputs(count, existingNames, existingPrices);
        });

        // If weÃ¢â‚¬â„¢re in edit mode, restore values from PHP
        let existingNames = [];
        let existingPrices = [];

        <?php if ($isEdit && $product['variant'] == 1): ?>
            existingNames = <?= json_encode($names) ?>;
            existingPrices = <?= json_encode($prices) ?>;
            renderVariantInputs(existingNames.length, existingNames, existingPrices);
        <?php endif; ?>

        $("#productForm").on("submit", function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const variantValue = $("input[name='variant']:checked").val();
            const isEdit = $("input[name='id_prod']").val() !== "";

            // Append the variant value
            if (!variantValue) {
                alert("Please select if the product has variants.");
                return;
            }
            formData.set("variant", variantValue === "yes" ? 1 : 0);

            // Handle variant vs non-variant
            if (variantValue === "yes") {
                const variantNames = $("input[name='variant_name[]']").map(function() {
                    return this.value.trim();
                }).get();

                const variantPrices = $("input[name='variant_price[]']").map(function() {
                    return this.value.trim();
                }).get();

                if (variantNames.includes("") || variantPrices.includes("") || variantNames.length === 0) {
                    alert("Please fill out all variant names and prices.");
                    return;
                }

                formData.append("nama_var", variantNames.join(";"));
                formData.append("biaya_var", variantPrices.join(";"));
            } else {
                const price = $("#hargaTokoField input").val();
                if (!price || parseFloat(price) <= 0) {
                    alert("Please provide a valid store price.");
                    return;
                }
                formData.append("biaya", price);
            }

            // Debug output (optional)
            console.log([...formData.entries()]);

            // Submit via AJAX
            $.ajax({
                url: isEdit ? "/cafe_data_edit/" : "/cafe_data_handler/",
                method: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.trim() === "success") {
                        alert(isEdit ? "Product updated successfully!" : "Product saved successfully!");
                        window.location.href = "main.php?id=cafeData";
                    } else {
                        alert("Server response: " + response);
                    }
                }
            });
        });
    });
</script>
