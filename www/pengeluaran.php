<?php
include 'config/config.php';
date_default_timezone_set("Asia/Jakarta");

if (!isset($_POST['data'])) {
  http_response_code(400);
  echo "Missing data";
  exit;
}

$data = json_decode($_POST['data'], true);
if (!$data) {
  http_response_code(400);
  echo "Invalid JSON";
  exit;
}

foreach ($data as $row) {
  $keterangan = mysqli_real_escape_string($conn, $row['keterangan']);
  $total = (int)$row['total'];
  $sql = "INSERT INTO pengeluaran (keterangan, total, created_at) VALUES ('$keterangan', $total, NOW())";
  mysqli_query($conn, $sql);
}

echo json_encode(['status' => 'success', 'count' => count($data)]);
