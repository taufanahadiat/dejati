<?php
$sql = "SELECT * FROM tb_category ORDER BY id_cat ASC";
$result = $conn->query($sql);

if ($result->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Icon</th>
                    <th>Nama</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($cat = $result->fetch_assoc()): ?>
                    <tr>
                        <td><span class="material-symbols-outlined"><?= htmlspecialchars($cat['icon']) ?></span></td>
                        <td><?= htmlspecialchars($cat['name_cat']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning btn-edit"
                                data-id="<?= $cat['id_cat'] ?>"
                                data-name="<?= htmlspecialchars($cat['name_cat']) ?>"
                                data-icon="<?= htmlspecialchars($cat['icon']) ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-delete"
                                data-id="<?= $cat['id_cat'] ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p>Belum ada kategori.</p>
<?php endif;
$conn->close();
?>
<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form id="editCategoryForm" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Kategori</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_cat" id="edit_id_cat">
                <div class="form-group">
                    <label>Nama Grup</label>
                    <input type="text" name="name_cat" id="edit_name_cat" class="form-control" required>
                </div>
                <div class="form-group row">
                    <label class="col-sm-4 col-form-label">Pilih Icon</label>
                    <div class="col-sm-8">
                        <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center" data-toggle="modal" data-target="#iconModal" id="editIconPickerBtn">
                            <span id="editSelectedIcon" class="material-symbols-outlined mr-2" style="font-size: 24px;">help</span>
                            <span id="editSelectedIconLabel">Pilih icon</span>
                        </button>
                        <input type="hidden" name="icon" id="edit_icon" value="help">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Simpan Perubahan</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
            </div>
        </form>
    </div>
</div>