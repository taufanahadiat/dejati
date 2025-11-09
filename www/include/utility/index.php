<?php

$filter = $_GET['filter'] ?? '';

// Handle Add
if (isset($_POST['add'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $stmt = $conn->prepare("INSERT INTO tb_utility (name, category) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $category);
    $stmt->execute();
    echo '<script>window.location.href="main.php?id=utility";</script>';
    exit;
}

// Handle Edit
if (isset($_POST['edit'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $category = $_POST['category'];
    $stmt = $conn->prepare("UPDATE tb_utility SET name=?, category=? WHERE id=?");
    $stmt->bind_param("ssi", $name, $category, $id);
    $stmt->execute();
    echo '<script>window.location.href="main.php?id=utility";</script>';
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM tb_utility WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo '<script>window.location.href="main.php?id=utility";</script>';
    exit;
}

// Handle Stock Update
if (isset($_POST['update_stock'])) {
    $stock_id = $_POST['stock_id'];
    $item_number = $_POST['item_number'];
    $stmt = $conn->prepare("UPDATE tb_stock SET item_number=? WHERE id=?");
    $stmt->bind_param("ii", $item_number, $stock_id);
    $stmt->execute();
    echo '<script>window.location.href="main.php?id=utility";</script>';
    exit;
}

// Fetch utilities, sorted by category then name, with filter
if ($filter === 'so-kitchen' || $filter === 'so-bar') {
    $stmt = $conn->prepare("SELECT * FROM tb_utility WHERE category=? ORDER BY name ASC");
    $stmt->bind_param("s", $filter);
    $stmt->execute();
    $utilities = $stmt->get_result();
} else {
    $utilities = $conn->query("SELECT * FROM tb_utility ORDER BY FIELD(category, 'so-kitchen', 'so-bar'), name ASC");
}

// Fetch stock items
$stocks = $conn->query("SELECT * FROM tb_stock ORDER BY name ASC");

// For edit form
$editData = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM tb_utility WHERE id=$id");
    $editData = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Utility Management</title>
    <link rel="stylesheet" href="../../dist/css/material-symbols.css">
    <link rel="stylesheet" href="custom.css">
    <style>
        .filter-btns { display:inline-block; margin-left:10px; }
        .filter-btns a { margin-right:5px; }
        /* Simple modal styles */
        .modal { display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; overflow:auto; background:rgba(0,0,0,0.4);}
        .modal-content { background:#fff; margin:10% auto; padding:20px; border-radius:8px; width:350px; position:relative;}
        .close { position:absolute; right:12px; top:8px; font-size:22px; cursor:pointer;}
    </style>
</head>
<body>
<div class="container">
    <!-- Utility Table -->
    <div class="card">
        <div class="card-header">
            <h3>Utility Management</h3>
        </div>
        <div class="card-body">
            <!-- Add Form and Filter Buttons -->
            <div style="display:flex; align-items:center;">
                <form method="post" action="main.php?id=utility" class="form-inline mb-3" style="flex:1;">
                    <input type="hidden" name="id" value="">
                    <input type="text" name="name" class="form-control" placeholder="Name" required>
                    <select name="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <option value="so-kitchen">so-kitchen</option>
                        <option value="so-bar">so-bar</option>
                    </select>
                    <button type="submit" name="add" class="btn btn-success">Add</button>
                </form>
                <div class="filter-btns">
                    <a href="main.php?id=utility" class="btn btn-secondary<?= $filter=='' ? ' active' : '' ?>">All</a>
                    <a href="main.php?id=utility&filter=so-kitchen" class="btn btn-primary<?= $filter=='so-kitchen' ? ' active' : '' ?>">so-kitchen</a>
                    <a href="main.php?id=utility&filter=so-bar" class="btn btn-primary<?= $filter=='so-bar' ? ' active' : '' ?>">so-bar</a>
                </div>
            </div>

            <!-- Table View -->
            <div class="table-responsive" style="min-width:700px;">
                <table class="table table-striped table-bordered" style="width:100%;">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width:60px;">No</th>
                            <th>Name</th>
                            <th>Category &#8595;</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $utility_no = 1; while ($row = $utilities->fetch_assoc()): ?>
                        <tr>
                            <td><?= $utility_no++ ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td>
                                <button 
                                    class="btn btn-sm btn-primary edit-btn"
                                    data-id="<?= $row['id'] ?>"
                                    data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>"
                                    data-category="<?= htmlspecialchars($row['category'], ENT_QUOTES) ?>"
                                    title="Edit"
                                >
                                    <span class="material-symbols-rounded">edit</span>
                                </button>
                                <a href="main.php?id=utility&delete=<?= $row['id'] ?><?= $filter ? '&filter='.$filter : '' ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this utility?')">
                                    <span class="material-symbols-rounded">delete</span>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h4>Edit Utility</h4>
            <form method="post" action="main.php?id=utility<?= $filter ? '&filter='.$filter : '' ?>" id="editForm">
                <input type="hidden" name="id" id="edit-id">
                <div>
                    <label>Name:</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>
                <div>
                    <label>Category:</label>
                    <select name="category" id="edit-category" class="form-control" required>
                        <option value="so-kitchen">so-kitchen</option>
                        <option value="so-bar">so-bar</option>
                    </select>
                </div>
                <div style="margin-top:12px;">
                    <button type="submit" name="edit" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Management Table -->
    <div class="card" style="margin-top:32px;">
        <div class="card-header">
            <h3>Stock Management</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive" style="min-width:700px;">
                <table class="table table-striped table-bordered" style="width:100%;">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width:60px;">No</th>
                            <th>Name</th>
                            <th>Item Number</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $stock_no = 1; while ($row = $stocks->fetch_assoc()): ?>
                        <tr>
                            <td><?= $stock_no++ ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['item_number']) ?></td>
                            <td>
                                <button 
                                    class="btn btn-sm btn-primary stock-edit-btn"
                                    data-id="<?= $row['id'] ?>"
                                    data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>"
                                    data-item="<?= htmlspecialchars($row['item_number'], ENT_QUOTES) ?>"
                                    title="Update Item Number"
                                >
                                    <span class="material-symbols-rounded">edit</span>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h4>Edit Utility</h4>
        <form method="post" action="main.php?id=utility<?= $filter ? '&filter='.$filter : '' ?>" id="editForm">
            <input type="hidden" name="id" id="edit-id">
            <div>
                <label>Name:</label>
                <input type="text" name="name" id="edit-name" class="form-control" required>
            </div>
            <div>
                <label>Category:</label>
                <select name="category" id="edit-category" class="form-control" required>
                    <option value="so-kitchen">so-kitchen</option>
                    <option value="so-bar">so-bar</option>
                </select>
            </div>
            <div style="margin-top:12px;">
                <button type="submit" name="edit" class="btn btn-primary">Update</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>



<!-- Stock Edit Modal -->
<div id="stockEditModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeStockModal()">&times;</span>
        <h4>Update Item Number</h4>
        <form method="post" action="main.php?id=utility" id="stockEditForm">
            <input type="hidden" name="stock_id" id="stock-edit-id">
            <div>
                <label>Name:</label>
                <input type="text" id="stock-edit-name" class="form-control" readonly>
            </div>
            <div>
                <label>Item Number:</label>
                <input type="number" name="item_number" id="stock-edit-item" class="form-control" required min="0">
            </div>
            <div style="margin-top:12px;">
                <button type="submit" name="update_stock" class="btn btn-primary">Update</button>
                <button type="button" class="btn btn-secondary" onclick="closeStockModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}
document.querySelectorAll('.edit-btn').forEach(function(btn) {
    btn.onclick = function(e) {
        e.preventDefault();
        document.getElementById('edit-id').value = btn.getAttribute('data-id');
        document.getElementById('edit-name').value = btn.getAttribute('data-name');
        document.getElementById('edit-category').value = btn.getAttribute('data-category');
        document.getElementById('editModal').style.display = 'block';
    };
});
window.onclick = function(event) {
    var modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeModal();
    }
};

function closeStockModal() {
    document.getElementById('stockEditModal').style.display = 'none';
}
document.querySelectorAll('.stock-edit-btn').forEach(function(btn) {
    btn.onclick = function(e) {
        e.preventDefault();
        document.getElementById('stock-edit-id').value = btn.getAttribute('data-id');
        document.getElementById('stock-edit-name').value = btn.getAttribute('data-name');
        document.getElementById('stock-edit-item').value = btn.getAttribute('data-item');
        document.getElementById('stockEditModal').style.display = 'block';
    };
});
window.onclick = function(event) {
    var modal = document.getElementById('stockEditModal');
    if (event.target == modal) {
        closeStockModal();
    }
};
</script>
</body>
</html>