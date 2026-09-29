<?php
require_once __DIR__.'/../config/session.php';session_start();
if (empty($_SESSION['loggedin'])||($_SESSION['level']??'')!=='Administrator') {http_response_code(403);exit('Forbidden');}
if ($_SERVER['REQUEST_METHOD']!=='POST'||!isset($_SESSION['olsera_csrf'])||!hash_equals($_SESSION['olsera_csrf'],(string)($_POST['csrf']??''))) {http_response_code(403);exit('Permintaan tidak valid.');}
require __DIR__.'/../config/config.php';require __DIR__.'/../config/olsera.php';
try {$date=olseraDate((string)($_POST['date']??''));olseraQuery($conn,"UPDATE olsera_sync_jobs SET state='pending',next_attempt_at=NOW(),last_error=NULL WHERE sales_date=? AND state='failed'",'s',[$date]);header('Location: /main?id=salesReport&start='.$date.'&end='.$date, true,303);}
catch(Throwable $e) {http_response_code(400);echo 'Tidak dapat mengulang pekerjaan.';}
