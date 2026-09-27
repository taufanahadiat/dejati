<?php
require_once __DIR__ . '/../config/session.php';
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
function waError(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}
if (empty($_SESSION['loggedin'])) waError(401, 'Silakan login kembali.');
if (($_SESSION['level'] ?? '') !== 'Administrator') waError(403, 'Hanya Administrator yang dapat mengelola WhatsApp.');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? 'status';
if ($method === 'POST') {
    if (empty($_SESSION['whatsapp_csrf']) || !is_string($_POST['csrf'] ?? null) ||
        !hash_equals($_SESSION['whatsapp_csrf'], $_POST['csrf'])) waError(403, 'Token tidak valid. Muat ulang halaman.');
    if (!in_array($action, ['connect', 'disconnect'], true)) waError(400, 'Aksi tidak valid.');
} elseif ($method !== 'GET') {
    header('Allow: GET, POST');
    waError(405, 'Metode tidak diizinkan.');
} else {
    $action = 'status';
}
session_write_close();
$context = stream_context_create(['http' => [
    'method' => $method, 'timeout' => 25, 'ignore_errors' => true,
    'header' => "Content-Type: application/json\r\nConnection: close\r\n",
    'content' => $method === 'POST' ? '{}' : ''
]]);
$response = @file_get_contents('http://whatsapp:3000/' . $action, false, $context);
if ($response === false) waError(503, 'Layanan WhatsApp belum tersedia. Coba lagi sebentar.');
$data = json_decode($response, true);
if (!is_array($data)) waError(502, 'Respons layanan WhatsApp tidak valid.');
if (isset($data['error'])) waError(503, $data['error']);
echo json_encode($data);
