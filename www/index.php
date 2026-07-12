<?php

require_once __DIR__ . '/config/session.php';
session_start();

require_once 'config/config.php';

if (isset($_SESSION['loggedin'])) {
  if ($_SESSION['level'] === 'Administrator') {
    header("Location: ./main.php");
  } elseif ($_SESSION['level'] === 'Kasir') {
    header("Location: ./main.php");
  } elseif ($_SESSION['level'] === 'Karyawan') {
    header("Location: ./main.php");
  }
  die();
}

if (isset($_POST['login'])) {
  $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
  $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS);

  $stmt = mysqli_prepare($conn, "SELECT * FROM tb_user WHERE username = ? LIMIT 1");
  if (!$stmt) {
    $_SESSION['errLog'] = 'Login gagal diproses. Silahkan coba lagi.';
    header("Refresh: 0; url=./");
    die();
  }

  mysqli_stmt_bind_param($stmt, 's', $username);
  mysqli_stmt_execute($stmt);
  $query = mysqli_stmt_get_result($stmt);
  $cek = $query ? mysqli_num_rows($query) : 0;
  $data = $query ? mysqli_fetch_array($query) : [];
  mysqli_stmt_close($stmt);

  if ($cek > 0) {
    if (password_verify($password, $data['password'])) {
      if (isset($data['status']) && strtolower((string) $data['status']) !== 'aktif') {
        $_SESSION['errLog'] = 'Akun Anda sedang nonaktif. Silahkan hubungi administrator.';
        header("Refresh: 0; url=./");
        die();
      }

      $_SESSION['loggedin'] = TRUE;
      $_SESSION['id_user'] = $data['id_user'];
      $_SESSION['username'] = $data['username'];
      $_SESSION['nama_user'] = $data['nama_user'];
      $_SESSION['level'] = $data['level'];
      $_SESSION['status'] = $data['status'];
      if (isset($data['created_at'])) {
        $_SESSION['created_at'] = $data['created_at'];
      }

      $date = date('Y-m-d H:i:s');
      $ip_address = $_SERVER['REMOTE_ADDR'];
      $s_username = $_SESSION['username'];
      mysqli_query($conn, "UPDATE tb_user SET last_logged_in = '$date', ip_address = '$ip_address' WHERE username = '$s_username'");

      if ($data['level'] === 'Administrator') {
        header("Refresh: 0; url=./main.php");
      } elseif ($data['level'] === 'Kasir') {
        header("Refresh: 0; url=./main.php");
      } elseif ($data['level'] === 'Karyawan') {
        header("Refresh: 0; url=./main.php");
      }
      die();
    }

    $_SESSION['errLog'] = 'Username dan Password yang Anda masukkan salah, silahkan coba lagi.';
    header("Refresh: 0; url=./");
    die();
  }

  $_SESSION['errLog'] = 'Username dan Password yang Anda masukkan salah, silahkan coba lagi.';
  header("Refresh: 0; url=./");
  die();
}
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <link rel="shortcut icon" href="dist/img/favicon_carwash.ico">
  <title>Sistem Kasir - Dejati Coffee Garden & Carwash</title>
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <script>
    (function() {
      var storageKey = 'adminlte-theme-mode';
      var mode = null;

      try {
        mode = localStorage.getItem(storageKey);
      } catch (error) {
        mode = null;
      }

      if (mode !== 'dark' && mode !== 'light') {
        mode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      }

      var isDark = mode === 'dark';
      document.documentElement.setAttribute('data-theme-mode', mode);
      document.documentElement.classList.add('theme-preload');
      document.documentElement.classList.toggle('theme-preload-dark', isDark);
      document.documentElement.classList.toggle('theme-preload-light', !isDark);
      document.documentElement.classList.toggle('dark-mode', isDark);
    })();
  </script>
  <link rel="stylesheet" type="text/css" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <link rel="stylesheet" type="text/css" href="dist/css/adminlte.min.css">
  <link rel="stylesheet" type="text/css" href="dist/css/theme.css">
  <style type="text/css">
    body {
      min-height: 100vh;
      background: #f4f1ea;
      color: #2f2a24;
    }

    .bg::before {
      content: '';
      background-image: url('./dist/img/background.jpg');
      background-repeat: repeat;
      background-size: 760px;
      position: fixed;
      inset: 0;
      z-index: -2;
      opacity: 0.12;
    }

    .bg::after {
      content: '';
      position: fixed;
      inset: 0;
      z-index: -1;
      background: linear-gradient(180deg, rgba(250, 248, 244, 0.92), rgba(248, 245, 239, 0.97));
    }

    .login-box {
      width: 420px;
      max-width: calc(100vw - 2rem);
    }

    .login-card {
      border: 0;
      border-top: 4px solid #8c6a43;
      border-radius: 0.85rem;
      box-shadow: 0 1rem 2.5rem rgba(66, 44, 20, 0.12);
      overflow: hidden;
      backdrop-filter: blur(3px);
    }

    .login-card-body {
      padding: 2.25rem 2rem 1.75rem;
      background: rgba(255, 255, 255, 0.95);
    }

    .login-logo img {
      width: 152px;
      margin-bottom: 0.75rem;
    }

    .login-title {
      margin: 0;
      font-size: 1.75rem;
      font-weight: 700;
    }

    .login-subtitle {
      margin: 0.5rem 0 0;
      color: #6c757d;
      font-size: 0.98rem;
    }

    .brand-caption {
      margin: 1.5rem 0 1.25rem;
      text-align: center;
      color: #7a5a38;
      font-size: 0.82rem;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .input-group-text {
      background: #f7f4ef;
      border-color: #d8cfc2;
      color: #7a5a38;
    }

    .form-control {
      border-color: #d8cfc2;
      height: calc(2.6rem + 2px);
    }

    .form-control:focus {
      border-color: #8c6a43;
      box-shadow: 0 0 0 0.2rem rgba(140, 106, 67, 0.12);
    }

    .btn-login {
      background: #8c6a43;
      border-color: #8c6a43;
      font-weight: 600;
      padding-top: 0.65rem;
      padding-bottom: 0.65rem;
    }

    .btn-login:hover,
    .btn-login:focus {
      background: #755434;
      border-color: #755434;
    }

    .login-help {
      margin-top: 1rem;
      text-align: center;
      color: #8b8f94;
      font-size: 0.9rem;
    }
  </style>
  <!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->
  <link rel="stylesheet" type="text/css" href="dist/css/fontsgoogleapis.css">
</head>

<body class="hold-transition login-page bg">
  <div class="login-box">
    <div class="card login-card">
      <div class="card-body login-card-body">
        <div class="login-logo">
          <img title="De Jati" src="dist/img/logo-dejati-black.PNG" alt="Logo De Jati" />
          <p class="login-title">Login Kasir</p>
          <p class="login-subtitle">De'Jati Coffee Garden & Carwash</p>
        </div>

        <div class="brand-caption">Sistem Kasir De'Jati</div>

        <form action="" method="post">
          <?php
          if (isset($_SESSION['errLog'])) {
            $errLog = $_SESSION['errLog'];
          ?>
            <div class="alert alert-danger" role="alert">
              <button type="button" class="close" data-dismiss="alert">&times;</button>
              <?= $errLog; ?>
            </div>
          <?php
            unset($_SESSION['errLog']);
          }
          ?>
          <div class="input-group mb-3">
            <input type="text" class="form-control" name="username" placeholder="Username" autofocus="autofocus" autocomplete="username">
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-user"></span>
              </div>
            </div>
          </div>
          <div class="input-group mb-4">
            <input type="password" class="form-control" name="password" placeholder="Password" autocomplete="current-password">
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-lock"></span>
              </div>
            </div>
          </div>
          <button type="submit" name="login" class="btn btn-primary btn-block btn-login">Log In</button>
        </form>

        <div class="login-help">Masukkan akun kasir yang aktif untuk mulai transaksi.</div>
      </div>
    </div>
  </div>

  <script src="plugins/jquery/jquery.min.js"></script>
  <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="dist/js/theme.js"></script>
</body>

</html>
