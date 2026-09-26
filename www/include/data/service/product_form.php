<?php
require_once __DIR__ . '/product_helpers.php';
$table = service_product_table($serviceType);
$label = ucfirst($serviceType);
$page = $serviceType . 'Data';
$isEdit = isset($_GET['id_produk']);
$product = ['produk' => '', 'variant' => 0, 'biaya' => '', 'nama_var' => '', 'biaya_var' => '', 'foto' => ''];
if ($isEdit) {
    $id = (int)$_GET['id_produk'];
    $stmt = $conn->prepare("SELECT * FROM $table WHERE id_produk = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    if (!$product) {
        echo '<div class="alert alert-danger">Produk tidak ditemukan.</div>';
        return;
    }
}
$breadcrumb = [['label' => 'Daftar Produk ' . $label], ['label' => $isEdit ? 'Edit' : 'Tambah', 'active' => true]];
?>
<section class="content">
  <div class="row"><div class="col-12 mt-2"><div class="card card-dark card-outline">
    <div class="card-header"><h3 class="card-title"><?= $isEdit ? 'Edit' : 'Tambah' ?> Produk <?= $label ?></h3></div>
    <div class="card-body">
      <a class="btn btn-primary mb-3" href="/main?id=<?= $page ?>"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Produk</a>
      <form class="p-3" id="serviceProductForm" enctype="multipart/form-data">
        <input type="hidden" name="id_prod" value="<?= (int)($product['id_produk'] ?? 0) ?>">
        <input type="hidden" name="foto" id="servicePhoto" value="<?= htmlspecialchars($product['foto'] ?? '', ENT_QUOTES) ?>">
        <input type="hidden" name="remove_foto" id="serviceRemovePhoto" value="0">
        <div class="form-group row">
          <label class="col-md-2 col-form-label">Gambar Produk</label>
          <div class="col-md-6">
            <?php if (!empty($product['foto'])): ?>
              <div id="serviceExistingPhoto">
                <img class="img-thumbnail mb-2" style="max-height:120px" src="<?= htmlspecialchars(cafe_product_image_url($product['foto']), ENT_QUOTES) ?>" alt="Foto Produk">
                <button class="btn btn-danger btn-sm d-block mb-2" type="button" id="serviceDeletePhoto">Hapus Gambar</button>
              </div>
            <?php endif; ?>
            <div class="dropzone border border-secondary rounded p-2" id="serviceImageDropzone"></div>
            <small class="form-text text-muted">Format JPG, JPEG, PNG — Maks. 7 MB. Unggah gambar baru untuk mengganti gambar lama.</small>
          </div>
        </div>
        <div class="form-group row">
          <label class="col-md-2 col-form-label" for="serviceProductName">Nama Produk <span class="text-danger">*</span></label>
          <div class="col-md-6"><input class="form-control" name="nama_prod" id="serviceProductName" maxlength="100" required value="<?= htmlspecialchars($product['produk'], ENT_QUOTES) ?>"></div>
        </div>
        <div class="form-group row">
          <label class="col-md-2 col-form-label">Kategori Produk</label>
          <div class="col-md-6"><input class="form-control" value="<?= $label ?>" readonly></div>
        </div>
        <div class="form-group row">
          <label class="col-md-2 col-form-label">Varian</label>
          <div class="col-md-6">
            <div class="icheck-success d-inline mr-3"><input type="radio" id="serviceVariantYes" name="variant" value="1" <?= $product['variant'] ? 'checked' : '' ?>><label for="serviceVariantYes">Ya</label></div>
            <div class="icheck-danger d-inline"><input type="radio" id="serviceVariantNo" name="variant" value="0" <?= !$product['variant'] ? 'checked' : '' ?>><label for="serviceVariantNo">Tidak</label></div>
          </div>
        </div>
        <div id="serviceVariantFields">
          <div class="form-group row">
            <label class="col-md-2 col-form-label" for="serviceVariantCount">Jumlah Varian</label>
            <div class="col-md-6"><input class="form-control" type="number" min="1" max="50" id="serviceVariantCount" value="<?= $product['variant'] ? count(explode(';', $product['nama_var'])) : 1 ?>"></div>
          </div>
          <div id="serviceVariantRows"></div>
        </div>
        <div class="form-group row" id="servicePriceField">
          <label class="col-md-2 col-form-label" for="servicePrice">Harga Jual di Toko <span class="text-danger">*</span></label>
          <div class="col-md-6"><input class="form-control" type="number" min="1" step="1" max="2147483647" name="biaya" id="servicePrice" value="<?= htmlspecialchars((string)$product['biaya'], ENT_QUOTES) ?>"></div>
        </div>
        <div class="alert alert-danger d-none" id="serviceProductError" role="alert"></div>
        <div class="form-group row"><div class="col-md-6 offset-md-2"><button class="btn btn-success" id="serviceProductSave" type="submit"><i class="fas fa-save"></i> Simpan Data</button></div></div>
      </form>
    </div>
  </div></div></div>
</section>
<link rel="stylesheet" href="/plugins/dropzone/min/dropzone.min.css">
<script src="/plugins/dropzone/min/dropzone.min.js"></script>
<script>
Dropzone.autoDiscover = false;
$(function () {
  const endpoint = <?= json_encode('/include/data/' . $serviceType . '/product_save') ?>;
  const listUrl = <?= json_encode('/main?id=' . $page) ?>;
  const names = <?= json_encode($product['variant'] ? explode(';', $product['nama_var']) : [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const prices = <?= json_encode($product['variant'] ? explode(';', $product['biaya_var']) : []) ?>;
  let pendingUploads = 0;
  let saving = false;
  let uploadFailed = false;
  function error(message) { $('#serviceProductError').text(message).removeClass('d-none'); }
  function buttons() { $('#serviceProductSave').prop('disabled', saving || pendingUploads > 0 || uploadFailed); }
  function renderVariants() {
    $('#serviceVariantRows input[name="variant_name[]"]').each(function (i) { names[i] = this.value; });
    $('#serviceVariantRows input[name="variant_price[]"]').each(function (i) { prices[i] = this.value; });
    const count = Math.min(50, Math.max(1, parseInt($('#serviceVariantCount').val(), 10) || 1));
    const rows = $('#serviceVariantRows').empty();
    for (let i = 0; i < count; i++) {
      const row = $('<div class="form-group row">');
      row.append($('<label class="col-md-2 col-form-label">').text('Nama Varian ' + (i + 1)));
      row.append($('<div class="col-md-2">').append($('<input class="form-control" name="variant_name[]" maxlength="100" required>').val(names[i] || '')));
      row.append('<label class="col-md-2 col-form-label">Harga</label>');
      row.append($('<div class="col-md-2">').append($('<input class="form-control" type="number" name="variant_price[]" min="1" max="2147483647" step="1" required>').val(prices[i] || '')));
      rows.append(row);
    }
    toggleVariant();
  }
  function toggleVariant() {
    const enabled = $('#serviceVariantYes').prop('checked');
    $('#serviceVariantFields').toggle(enabled).find('input').prop('disabled', !enabled);
    $('#servicePriceField').toggle(!enabled).find('input').prop('disabled', enabled).prop('required', !enabled);
  }
  $('#serviceVariantCount').on('input', renderVariants);
  $('input[name="variant"]').on('change', toggleVariant);
  renderVariants();
  $('#serviceDeletePhoto').on('click', function () {
    $('#servicePhoto').val(''); $('#serviceRemovePhoto').val('1'); $('#serviceExistingPhoto').hide();
  });
  new Dropzone('#serviceImageDropzone', {
    url: endpoint, paramName: 'imageFile', maxFilesize: 7, maxFiles: 1,
    acceptedFiles: '.jpg,.jpeg,.png', addRemoveLinks: true,
    dictDefaultMessage: 'Tarik gambar ke sini atau klik untuk mengunggah',
    init: function () {
      this.on('sending', function (file, xhr, data) { pendingUploads++; uploadFailed = false; buttons(); data.append('upload_only', '1'); });
      this.on('success', function (file, response) {
        if (response.status !== 'success' || !response.foto) { uploadFailed = true; error(response.message || 'Unggah gambar gagal.'); return; }
        file.savedPhoto = response.foto;
        $('#servicePhoto').val(response.foto); $('#serviceRemovePhoto').val('0');
        $('#serviceExistingPhoto').hide(); $('#serviceProductError').addClass('d-none'); uploadFailed = false;
      });
      this.on('error', function (file, response) { uploadFailed = true; error(response.message || String(response)); buttons(); });
      this.on('complete', function () { pendingUploads = Math.max(0, pendingUploads - 1); buttons(); });
      this.on('removedfile', function (file) {
        if (file.savedPhoto && $('#servicePhoto').val() === file.savedPhoto) { $('#servicePhoto').val(''); $('#serviceRemovePhoto').val('1'); }
        uploadFailed = this.files.some(file => file.status === Dropzone.ERROR); buttons();
      });
    }
  });
  $('#serviceProductForm').on('submit', function (event) {
    event.preventDefault();
    if (saving || pendingUploads || uploadFailed || !this.reportValidity()) return;
    saving = true; buttons(); $('#serviceProductError').addClass('d-none');
    $.ajax({ url: endpoint, method: 'POST', data: new FormData(this), processData: false, contentType: false, dataType: 'json' })
      .done(function (response) { if (response.status === 'success') window.location.href = listUrl; else error(response.message || 'Gagal menyimpan produk.'); })
      .fail(function (xhr) { error(xhr.responseJSON?.message || 'Gagal menyimpan produk. Silakan coba kembali.'); })
      .always(function () { saving = false; buttons(); });
  });
});
</script>
