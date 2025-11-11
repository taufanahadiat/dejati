<?php
$message = "";

// === Handle Add/Edit/Delete (POST only) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD
    if ($action === 'add') {
        $name_cat = trim($_POST['name_cat']);
        $icon = trim($_POST['icon']);
        if ($name_cat && $icon) {
            $stmt = $conn->prepare("INSERT INTO tb_category (name_cat, icon) VALUES (?, ?)");
            $stmt->bind_param("ss", $name_cat, $icon);
            $stmt->execute();
            $message = "<div class='alert alert-success'>✅ Kategori berhasil disimpan!</div>";
        } else {
            $message = "<div class='alert alert-warning'>⚠️ Isi semua field!</div>";
        }
    }

    // EDIT
    if ($action === 'edit') {
        $id = (int) $_POST['id_cat'];
        $name_cat = trim($_POST['name_cat']);
        $icon = trim($_POST['icon']);
        $stmt = $conn->prepare("UPDATE tb_category SET name_cat=?, icon=? WHERE id_cat=?");
        $stmt->bind_param("ssi", $name_cat, $icon, $id);
        $stmt->execute();
        $message = "<div class='alert alert-success'>✏️ Kategori berhasil diperbarui!</div>";
    }

    // DELETE
    if ($action === 'delete') {
        $id = (int) $_POST['id_cat'];
        $stmt = $conn->prepare("DELETE FROM tb_category WHERE id_cat=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $message = "<div class='alert alert-info'>🗑️ Kategori berhasil dihapus.</div>";
    }
}

// === Fetch Categories ===
$categories = [];
$sql = "SELECT * FROM tb_category ORDER BY id_cat ASC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>

<link rel="stylesheet" href="dist/css/material-symbols.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

<style>
#iconModal {
    z-index: 1061 !important;
}
.modal-backdrop.show:nth-of-type(2) {
    z-index: 1060 !important;
}
</style>

<div class="container-fluid mt-3">
    <?= $message ?>
    <div class="row">
        <!-- Left: Add Category -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Tambah Group/Kategori</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Nama Grup</label>
                            <div class="col-sm-8">
                                <input type="text" name="name_cat" id="name_cat" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Pilih Icon</label>
                            <div class="col-sm-8">
                                <button id="addIconPickerBtn" type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#iconModal">
                                    <span id="selectedIcon" class="material-symbols-outlined mr-2" style="font-size:24px;">help</span>
                                    <span id="selectedIconLabel">Pilih icon</span>
                                </button>
                                <input type="hidden" name="icon" id="iconInput" value="help">
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Category List -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Daftar Kategori</div>
                <div class="card-body p-2">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Icon</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($categories)): ?>
                            <tr><td colspan="4" class="text-center text-muted">Belum ada kategori</td></tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= $cat['id_cat'] ?></td>
                                <td><?= htmlspecialchars($cat['name_cat']) ?></td>
                                <td><span class="material-symbols-outlined"><?= htmlspecialchars($cat['icon']) ?></span></td>
                                <td>
                                    <!-- Edit -->
                                    <button type="button" class="btn btn-warning btn-sm"
                                        onclick="openEditModal(<?= $cat['id_cat'] ?>, '<?= htmlspecialchars($cat['name_cat']) ?>', '<?= htmlspecialchars($cat['icon']) ?>')">Edit</button>
                                    
                                    <!-- Delete -->
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus kategori ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id_cat" value="<?= $cat['id_cat'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id_cat" id="edit_id_cat">
      <div class="modal-header">
        <h5 class="modal-title">Edit Kategori</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Nama Grup</label>
          <input type="text" name="name_cat" id="edit_name_cat" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Pilih Icon</label>
          <button id="editIconPickerBtn" type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#iconModal">
              <span id="editSelectedIcon" class="material-symbols-outlined mr-2" style="font-size:24px;">help</span>
              <span id="editSelectedIconLabel">Pilih icon</span>
          </button>
          <input type="hidden" name="icon" id="edit_icon" value="help">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Simpan</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- Icon Picker Modal -->
<div class="modal fade" id="iconModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pilih Icon</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <input type="text" id="iconSearch" class="form-control mb-3" placeholder="Cari icon...">
        <div class="row" id="iconGrid" style="max-height:400px;overflow-y:auto;"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
const materialIcons = [
    "restaurant", "local_cafe", "store", "category", "shopping_cart", "fastfood",
    "icecream", "emoji_food_beverage", "bakery_dining", "brunch_dining", "coffee",
    "coffee_maker", "kettle", "local_drink", "near_me", "food_bank", "blender",
    "ramen_dining", "takeout_dining", "lunch_dining", "dinner_dining", "local_bar",
    "liquor", "wine_bar", "set_meal", "tapas", "egg_alt", "cake", "cookie",
    "donut_small", "outdoor_grill", "rice_bowl", "soup_kitchen", "nutrition",
    "restaurant_menu", "home", "cottage", "apartment", "local_pharmacy"
];
let iconSelectContext = "add";

function populateIcons(filter = "") {
  const $grid = $("#iconGrid").empty();
  const filtered = materialIcons.filter(i => i.toLowerCase().includes(filter.toLowerCase()));
  filtered.forEach(icon => {
    const item = $(`
      <div class="col-2 text-center mb-3">
        <div class="border rounded p-2 icon-option" data-icon="${icon}" style="cursor:pointer;">
          <span class="material-symbols-outlined" style="font-size:24px;">${icon}</span>
          <div style="font-size:0.75rem;">${icon}</div>
        </div>
      </div>`);
    $grid.append(item);
  });
}

$(function(){
  populateIcons();
  $("#iconSearch").on("input", function(){ populateIcons($(this).val()); });

  $("#addIconPickerBtn").click(() => iconSelectContext = "add");
  $("#editIconPickerBtn").click(() => iconSelectContext = "edit");

  $("#iconGrid").on("click", ".icon-option", function(){
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
});

function openEditModal(id, name, icon){
  $("#edit_id_cat").val(id);
  $("#edit_name_cat").val(name);
  $("#edit_icon").val(icon);
  $("#editSelectedIcon").text(icon);
  $("#editSelectedIconLabel").text(icon);
  $("#editModal").modal("show");
}
</script>
