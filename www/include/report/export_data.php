<?php
require_once __DIR__ . '/../../config/session.php';
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['loggedin'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Sesi login berakhir. Silakan login kembali.']);
    exit;
}
session_write_close();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/export_report.php';
try {
    $options = salesExportOptions($_GET);
    $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    $report = salesExportBuild($conn, $options);
    $conn->commit();
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['message' => $e->getMessage()]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Sales export: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Laporan gagal disiapkan. Silakan coba lagi.']);
}
