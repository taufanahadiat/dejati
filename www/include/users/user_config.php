<?php
$breadcrumb = [
  ['label' => 'Config User', 'link' => '#'],
];

if (($_SESSION['level'] ?? '') !== 'Administrator') {
  echo '<section class="content pt-3"><div class="container-fluid"><div class="alert alert-danger mb-0">Akses ditolak. Halaman ini hanya untuk Administrator.</div></div></section>';
  return;
}

if (empty($_SESSION['user_config_token'])) {
  $_SESSION['user_config_token'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['user_config_token'];
$levels = ['Administrator', 'Kasir', 'Karyawan'];
$statuses = ['Aktif', 'Tidak Aktif'];
$errors = [];
$success = '';

function user_config_h($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function user_config_columns($conn)
{
  static $columns = null;

  if ($columns !== null) {
    return $columns;
  }

  $columns = [];
  $result = mysqli_query($conn, "SHOW COLUMNS FROM tb_user");
  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $columns[$row['Field']] = true;
    }
  }

  return $columns;
}

function user_config_has_column($conn, $column)
{
  $columns = user_config_columns($conn);
  return isset($columns[$column]);
}

function user_config_admin_count($conn, $excludeId = null)
{
  $sql = "SELECT COUNT(*) AS total FROM tb_user WHERE level = 'Administrator' AND (status IS NULL OR LOWER(status) = 'aktif')";
  $params = [];
  $types = '';

  if ($excludeId !== null) {
    $sql .= " AND id_user <> ?";
    $params[] = (int) $excludeId;
    $types .= 'i';
  }

  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    return 0;
  }

  if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
  }

  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $row = $result ? mysqli_fetch_assoc($result) : ['total' => 0];
  mysqli_stmt_close($stmt);

  return (int) ($row['total'] ?? 0);
}

function user_config_find_user($conn, $id)
{
  $stmt = mysqli_prepare($conn, "SELECT * FROM tb_user WHERE id_user = ? LIMIT 1");
  if (!$stmt) {
    return null;
  }

  mysqli_stmt_bind_param($stmt, 'i', $id);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $user = $result ? mysqli_fetch_assoc($result) : null;
  mysqli_stmt_close($stmt);

  return $user ?: null;
}

function user_config_redirect()
{
  echo '<script>window.location.href="main?id=userConfig";</script>';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = $_POST['csrf_token'] ?? '';

  if (!hash_equals($token, $postedToken)) {
    $errors[] = 'Sesi form tidak valid. Silahkan muat ulang halaman.';
  } else {
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $namaUser = trim($_POST['nama_user'] ?? '');
    $level = $_POST['level'] ?? '';
    $status = $_POST['status'] ?? 'Aktif';
    $password = $_POST['password'] ?? '';

    if ($action === 'delete') {
      $id = (int) ($_POST['id_user'] ?? 0);
      $user = user_config_find_user($conn, $id);

      if (!$user) {
        $_SESSION['user_config_error'] = 'User tidak ditemukan.';
        user_config_redirect();
      }

      if ((int) ($_SESSION['id_user'] ?? 0) === $id) {
        $_SESSION['user_config_error'] = 'User yang sedang login tidak bisa dihapus.';
        user_config_redirect();
      }

      if (($user['level'] ?? '') === 'Administrator' && user_config_admin_count($conn, $id) < 1) {
        $_SESSION['user_config_error'] = 'Minimal harus ada satu Administrator aktif.';
        user_config_redirect();
      }

      $stmt = mysqli_prepare($conn, "DELETE FROM tb_user WHERE id_user = ?");
      if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['user_config_success'] = 'User berhasil dihapus.';
      } else {
        $_SESSION['user_config_error'] = 'User gagal dihapus.';
      }

      user_config_redirect();
    }

    if ($username === '') {
      $errors[] = 'Username wajib diisi.';
    }

    if ($namaUser === '') {
      $errors[] = 'Nama user wajib diisi.';
    }

    if (!in_array($level, $levels, true)) {
      $errors[] = 'Level user tidak valid.';
    }

    if (!in_array($status, $statuses, true)) {
      $errors[] = 'Status user tidak valid.';
    }

    if ($action === 'add' && strlen($password) < 6) {
      $errors[] = 'Password minimal 6 karakter.';
    }

    if ($action === 'edit') {
      $id = (int) ($_POST['id_user'] ?? 0);
      $currentUser = user_config_find_user($conn, $id);

      if (!$currentUser) {
        $errors[] = 'User tidak ditemukan.';
      } elseif (($currentUser['level'] ?? '') === 'Administrator' && ($level !== 'Administrator' || $status !== 'Aktif') && user_config_admin_count($conn, $id) < 1) {
        $errors[] = 'Minimal harus ada satu Administrator aktif.';
      }

      if ((int) ($_SESSION['id_user'] ?? 0) === $id && $status !== 'Aktif') {
        $errors[] = 'User yang sedang login tidak bisa dinonaktifkan.';
      }
    }

    if (!$errors) {
      if ($action === 'add') {
        $check = mysqli_prepare($conn, "SELECT id_user FROM tb_user WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($check, 's', $username);
        mysqli_stmt_execute($check);
        $exists = mysqli_stmt_get_result($check);
        mysqli_stmt_close($check);

        if ($exists && mysqli_num_rows($exists) > 0) {
          $errors[] = 'Username sudah digunakan.';
        } else {
          $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
          $columns = ['username', 'password', 'nama_user', 'level', 'status'];
          $values = [$username, $hashedPassword, $namaUser, $level, $status];
          $types = 'sssss';

          if (user_config_has_column($conn, 'created_at')) {
            $columns[] = 'created_at';
            $values[] = date('Y-m-d H:i:s');
            $types .= 's';
          }

          $placeholders = implode(', ', array_fill(0, count($columns), '?'));
          $sql = 'INSERT INTO tb_user (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
          $stmt = mysqli_prepare($conn, $sql);

          if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$values);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['user_config_success'] = 'User berhasil ditambahkan.';
            user_config_redirect();
          }

          $errors[] = 'User gagal ditambahkan.';
        }
      } elseif ($action === 'edit') {
        $id = (int) ($_POST['id_user'] ?? 0);

        $check = mysqli_prepare($conn, "SELECT id_user FROM tb_user WHERE username = ? AND id_user <> ? LIMIT 1");
        mysqli_stmt_bind_param($check, 'si', $username, $id);
        mysqli_stmt_execute($check);
        $exists = mysqli_stmt_get_result($check);
        mysqli_stmt_close($check);

        if ($exists && mysqli_num_rows($exists) > 0) {
          $errors[] = 'Username sudah digunakan oleh user lain.';
        } else {
          $setParts = ['username = ?', 'nama_user = ?', 'level = ?', 'status = ?'];
          $values = [$username, $namaUser, $level, $status];
          $types = 'ssss';

          if ($password !== '') {
            if (strlen($password) < 6) {
              $errors[] = 'Password baru minimal 6 karakter.';
            } else {
              $setParts[] = 'password = ?';
              $values[] = password_hash($password, PASSWORD_DEFAULT);
              $types .= 's';
            }
          }

          if (!$errors) {
            $values[] = $id;
            $types .= 'i';
            $stmt = mysqli_prepare($conn, 'UPDATE tb_user SET ' . implode(', ', $setParts) . ' WHERE id_user = ?');

            if ($stmt) {
              mysqli_stmt_bind_param($stmt, $types, ...$values);
              $updated = mysqli_stmt_execute($stmt);
              mysqli_stmt_close($stmt);

              if ($updated && (int) ($_SESSION['id_user'] ?? 0) === $id) {
                $_SESSION['username'] = $username;
                $_SESSION['nama_user'] = $namaUser;
                $_SESSION['level'] = $level;
                $_SESSION['status'] = $status;
              }

              if ($updated) {
                $_SESSION['user_config_success'] = 'User berhasil diperbarui.';
                user_config_redirect();
              }
            }

            $errors[] = 'User gagal diperbarui.';
          }
        }
      }
    }
  }
}

if (isset($_SESSION['user_config_success'])) {
  $success = $_SESSION['user_config_success'];
  unset($_SESSION['user_config_success']);
}

if (isset($_SESSION['user_config_error'])) {
  $errors[] = $_SESSION['user_config_error'];
  unset($_SESSION['user_config_error']);
}

$users = mysqli_query($conn, "SELECT * FROM tb_user ORDER BY FIELD(level, 'Administrator', 'Kasir', 'Karyawan'), nama_user ASC, username ASC");
?>

<section class="content pt-3 user-config-page">
  <div class="container-fluid">
    <?php if ($success): ?>
      <div id="successMessage" class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle mr-1"></i><?= user_config_h($success); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div id="errorMessage" class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle mr-1"></i><?= user_config_h(implode(' ', $errors)); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
    <?php endif; ?>

    <div class="row">
      <div class="col-lg-4">
        <div class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-user-plus mr-2"></i>Tambah User</h3>
          </div>
          <form method="post" action="main?id=userConfig" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= user_config_h($token); ?>">
            <input type="hidden" name="action" value="add">
            <div class="card-body">
              <div class="form-group">
                <label for="add-username">Username</label>
                <input type="text" class="form-control" id="add-username" name="username" maxlength="80" required>
              </div>
              <div class="form-group">
                <label for="add-nama-user">Nama User</label>
                <input type="text" class="form-control" id="add-nama-user" name="nama_user" maxlength="120" required>
              </div>
              <div class="form-group">
                <label for="add-password">Password</label>
                <input type="password" class="form-control" id="add-password" name="password" minlength="6" required autocomplete="new-password">
              </div>
              <div class="form-row">
                <div class="form-group col-md-6">
                  <label for="add-level">Level</label>
                  <select class="custom-select" id="add-level" name="level" required>
                    <?php foreach ($levels as $item): ?>
                      <option value="<?= user_config_h($item); ?>"><?= user_config_h($item); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-md-6">
                  <label for="add-status">Status</label>
                  <select class="custom-select" id="add-status" name="status" required>
                    <?php foreach ($statuses as $item): ?>
                      <option value="<?= user_config_h($item); ?>"><?= user_config_h($item); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="card-footer text-right">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i>Simpan
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card card-dark card-outline">
          <div class="card-header d-flex align-items-center">
            <h3 class="card-title mb-0"><i class="fas fa-users-cog mr-2"></i>Daftar User</h3>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="userConfigTable" class="table table-bordered table-striped table-hover mb-0">
                <thead class="thead-light">
                  <tr>
                    <th style="width: 48px;">No</th>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>Level</th>
                    <th>Status</th>
                    <th>Login Terakhir</th>
                    <th style="width: 116px;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; ?>
                  <?php while ($row = $users ? mysqli_fetch_assoc($users) : null): ?>
                    <?php
                    $rowStatus = $row['status'] ?? 'Aktif';
                    $statusClass = strtolower((string) $rowStatus) === 'aktif' ? 'badge-success' : 'badge-secondary';
                    ?>
                    <tr>
                      <td><?= $no++; ?></td>
                      <td><?= user_config_h($row['username'] ?? ''); ?></td>
                      <td><?= user_config_h($row['nama_user'] ?? ''); ?></td>
                      <td><span class="badge badge-info"><?= user_config_h($row['level'] ?? ''); ?></span></td>
                      <td><span class="badge <?= $statusClass; ?>"><?= user_config_h($rowStatus); ?></span></td>
                      <td>
                        <?= !empty($row['last_logged_in']) ? user_config_h($row['last_logged_in']) : '<span class="text-muted">Belum pernah</span>'; ?>
                      </td>
                      <td>
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-primary user-edit-btn"
                          title="Edit user"
                          data-toggle="modal"
                          data-target="#editUserModal"
                          data-id="<?= user_config_h($row['id_user'] ?? ''); ?>"
                          data-username="<?= user_config_h($row['username'] ?? ''); ?>"
                          data-nama="<?= user_config_h($row['nama_user'] ?? ''); ?>"
                          data-level="<?= user_config_h($row['level'] ?? ''); ?>"
                          data-status="<?= user_config_h($rowStatus); ?>">
                          <i class="fas fa-edit"></i>
                        </button>
                        <form method="post" action="main?id=userConfig" class="d-inline" onsubmit="return confirm('Hapus user ini?');">
                          <input type="hidden" name="csrf_token" value="<?= user_config_h($token); ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id_user" value="<?= user_config_h($row['id_user'] ?? ''); ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus user" <?= ((int) ($_SESSION['id_user'] ?? 0) === (int) ($row['id_user'] ?? 0)) ? 'disabled' : ''; ?>>
                            <i class="fas fa-trash-alt"></i>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post" action="main?id=userConfig" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= user_config_h($token); ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id_user" id="edit-id-user">
        <div class="modal-header">
          <h5 class="modal-title" id="editUserModalLabel"><i class="fas fa-user-edit mr-2"></i>Edit User</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="edit-username">Username</label>
            <input type="text" class="form-control" id="edit-username" name="username" maxlength="80" required>
          </div>
          <div class="form-group">
            <label for="edit-nama-user">Nama User</label>
            <input type="text" class="form-control" id="edit-nama-user" name="nama_user" maxlength="120" required>
          </div>
          <div class="form-group">
            <label for="edit-password">Password Baru</label>
            <input type="password" class="form-control" id="edit-password" name="password" minlength="6" autocomplete="new-password" placeholder="Kosongkan jika tidak diganti">
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="edit-level">Level</label>
              <select class="custom-select" id="edit-level" name="level" required>
                <?php foreach ($levels as $item): ?>
                  <option value="<?= user_config_h($item); ?>"><?= user_config_h($item); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-6">
              <label for="edit-status">Status</label>
              <select class="custom-select" id="edit-status" name="status" required>
                <?php foreach ($statuses as $item): ?>
                  <option value="<?= user_config_h($item); ?>"><?= user_config_h($item); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i>Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && $.fn.DataTable) {
      $('#userConfigTable').DataTable({
        responsive: true,
        autoWidth: false,
        order: [[2, 'asc']],
        language: {
          searchPlaceholder: 'Kata Kunci...',
          processing: 'Sedang memuat...'
        }
      });
    }

    document.querySelectorAll('.user-edit-btn').forEach(function(button) {
      button.addEventListener('click', function() {
        document.getElementById('edit-id-user').value = this.dataset.id || '';
        document.getElementById('edit-username').value = this.dataset.username || '';
        document.getElementById('edit-nama-user').value = this.dataset.nama || '';
        document.getElementById('edit-level').value = this.dataset.level || 'Kasir';
        document.getElementById('edit-status').value = this.dataset.status || 'Aktif';
        document.getElementById('edit-password').value = '';
      });
    });
  });
</script>
