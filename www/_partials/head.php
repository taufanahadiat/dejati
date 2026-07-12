<?php require_once 'config/config.php';
?>
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#1f2937">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" href="/dist/img/512.png">
  <link rel="shortcut icon" href="dist/img/logo-only-white.png" type="image/x-icon">
  <title>De'Jati Universe</title>
  <?php
  $requestIsHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] == 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
  if ($requestIsHttps):
  ?>
  <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
  <?php endif; ?>
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
  <link rel="stylesheet" href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="plugins/ionicons/css/ionicons.min.css">
  <link rel="stylesheet" href="plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/bootstrap4-duallistbox/bootstrap-duallistbox.min.css">
  <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/toastr/toastr.min.css">
  <link rel="stylesheet" href="plugins/sweetalert2/sweetalert2.min.css">
  <link rel="stylesheet" href="plugins/dropzone/min/dropzone.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <link rel="stylesheet" href="dist/css/theme.css">
  <link rel="stylesheet" href="plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <link rel="stylesheet" href="plugins/uploadify/uploadify.css">
  <link rel="stylesheet" href="plugins/uploadify/uploadify.jGrowl.css">
  <link rel="stylesheet" href="plugins/uploadify/uploadify.styling.css">
  <link rel="stylesheet" href="plugins/autocomplete/autocomp.css">
  <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->
  <link rel="stylesheet" type="text/css" href="dist/css/fontsgoogleapis.css">
  <link rel="stylesheet" type="text/css" href="dist/css/style.css">
  <script src="plugins/jquery/jquery.min.js"></script>
  <script src="/dist/js/bluetooth-printer-manager.js?v=2026071206"></script>
</head>
