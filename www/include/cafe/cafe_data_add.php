<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Daftar Produk Cafe</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="main.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="main.php?id=cafeData">Produk Cafe</a></li>
                    <li class="breadcrumb-item active">Tambah Produk</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card card-dark card-outline">
                <div class="card-header">
                    <h3 class="card-title">Tambah Produk Baru</h3>
                </div>
                <div class="card-body">
                    <button class="btn btn-primary mb-3" onclick="window.location.href='main.php?id=cafeData'">
                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Produk
                    </button>
                    <form class="p-3" id="productForm" method="POST" enctype="multipart/form-data">
                        <!-- Gambar Produk -->
                        <div class="form-group row">
                            <label class="col-md-2 col-form-label">Gambar Produk</label>
                            <div class="col-md-6">
                                <div id="dropzoneImage" class="dropzone border border-secondary rounded p-2"></div>
                                <small class="form-text text-muted">Format .JPG, .JPEG, .PNG — Maks. 7 MB</small>
                                <input type="hidden" name="foto" id="foto">
                            </div>
                        </div>
                        <!-- Nama Produk -->
                        <div class="form-group row">
                            <label class="col-md-2 col-form-label">Nama Produk<span class="text-danger"> *</span></label>
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="nama_prod" placeholder="Tulis nama produk sesuai jenis, merek, dan rincian produk" required>
                            </div>
                        </div>
                        <!-- Kategori Produk -->
                        <div class="form-group row">
                            <label class="col-md-2 col-form-label">Kategori Produk<span class="text-danger"> *</span></label>
                            <div class="col-md-6">
                                <select class="form-control select2bs4" name="id_cat" required>
                                    <option disabled selected>Pilih Kategori</option>
                                    <?php
                                    $query = "SELECT id_cat, name_cat FROM tb_category ORDER BY name_cat ASC";
                                    $result = mysqli_query($conn, $query);
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        echo "<option value='" . $row['id_cat'] . "'>" . htmlspecialchars($row['name_cat']) . "</option>";
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
                                    <input type="radio" name="variant" id="variantYes" value="yes">
                                    <label for="variantYes">Ya</label>
                                </div>
                                <div class="icheck-danger d-inline">
                                    <input type="radio" name="variant" id="variantNo" value="no">
                                    <label for="variantNo">Tidak</label>
                                </div>
                            </div>
                        </div>

                        <!-- Variant Details (shown if "Ya") -->
                        <div id="variantDetails" class="d-none">
                            <!-- Number of Variants -->
                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">Jumlah Varian<span class="text-danger"> *</span></label>
                                <div class="col-md-6">
                                    <input type="number" min="1" class="form-control" id="variantCount" placeholder="Masukan jumlah varian">
                                </div>
                            </div>

                            <!-- Dynamic Variant Inputs -->
                            <div id="variantInputs"></div>
                        </div>

                        <!-- Harga Jual di Toko (shown if "Tidak") -->
                        <div id="hargaTokoField" class="d-none">
                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">Harga Jual di Toko<span class="text-danger"> *</span></label>
                                <div class="col-md-6">
                                    <input type="number" class="form-control" name="harga_toko" placeholder="Masukkan harga jual di toko" inputmode="decimal" id="hargaTokoInput">
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-group row">
                            <div class="col-md-12 text-right">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Simpan Produk
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php echo $_SESSION['id_user']; ?>
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

    let imageDropzone = new Dropzone("#dropzoneImage", {
        url: "include/cafe/cafe_data_addAct.php",
        maxFiles: 1,
        maxFilesize: 7, // in MB
        acceptedFiles: ".jpg,.jpeg,.png",
        addRemoveLinks: true,
        dictDefaultMessage: "Drop image here or click to upload",
        paramName: "imageFile", // must match $_FILES key in PHP
        autoProcessQueue: true,
        parallelUploads: 1,

        init: function() {
            this.on("sending", function(file, xhr, formData) {
                // Send unique name and prevent full form data duplication
                const ext = file.name.split('.').pop().toLowerCase();
                const uniqueName = "img_" + Date.now() + "." + ext;

                file.newName = uniqueName;
                formData.append("foto", uniqueName);
                formData.append("upload_only", true); // distinguish upload-only from full form
            });

            this.on("success", function(file, response) {
                if (response === "success") {
                    $("#foto").val(file.newName);
                } else {
                    alert("Upload failed: " + response);
                    this.removeFile(file);
                }
            });

            this.on("error", function(file, response) {
                alert("Upload error: " + response);
                this.removeFile(file);
            });

            this.on("maxfilesexceeded", function(file) {
                this.removeAllFiles();
                this.addFile(file);
            });
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

        $("#variantCount").on("input", function() {
            let count = parseInt($(this).val()) || 0;
            let html = '';
            for (let i = 1; i <= count; i++) {
                html += `
                <div class="form-group row">
                    <label class="col-md-2 col-form-label">Variant ${i} Name</label>
                    <div class="col-md-2">
                        <input type="text" class="form-control" name="variant_name[]" required>
                    </div>
                    <label class="col-md-2 col-form-label">Price</label>
                    <div class="col-md-2">
                        <input type="number" class="form-control" name="variant_price[]" required>
                    </div>
                </div>`;
            }
            $("#variantInputs").html(html);
        });

        $("#productForm").on("submit", function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const variantValue = $("input[name='variant']:checked").val();
            //const filename = $("#foto").val(); // Already set by Dropzone

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
                url: "/cafe_data_handler/",
                method: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.trim() === "success") {
                        alert("Product saved successfully!");
                        window.location.href = "main.php?id=cafeData";
                    } else {
                        alert("Server response: " + response);
                    }
                },
                error: function(xhr, status, error) {
                    alert("AJAX error: " + error);
                }
            });
        });
    });
</script>