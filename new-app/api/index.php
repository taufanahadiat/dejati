<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json; charset=utf-8');

final class Store
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
        if (!file_exists($file)) {
            copy(__DIR__ . '/../data/seed.json', $file);
        }
    }

    public function read(): array
    {
        $json = file_get_contents($this->file);
        $data = json_decode($json ?: '{}', true);
        return is_array($data) ? $data : [];
    }

    public function write(array $data): void
    {
        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

$store = new Store(__DIR__ . '/../data/app.json');
$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
$path = preg_replace('#^(new-app/public/)?api/?#', '', $path);
$segments = $path === '' ? [] : explode('/', $path);

function body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

function respond(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function requireLogin(): void
{
    if (empty($_SESSION['user'])) {
        respond(['error' => 'Authentication required'], 401);
    }
}

function nextId(array $rows): int
{
    return empty($rows) ? 1 : max(array_map(fn($row) => (int) $row['id'], $rows)) + 1;
}

function todayRows(array $rows, string $field = 'createdAt'): array
{
    $today = date('Y-m-d');
    return array_values(array_filter($rows, fn($row) => str_starts_with((string) ($row[$field] ?? ''), $today)));
}

if ($method === 'POST' && ($segments[0] ?? '') === 'login') {
    $data = $store->read();
    $input = body();
    foreach ($data['users'] as $user) {
        if ($user['username'] === ($input['username'] ?? '') && $user['password'] === ($input['password'] ?? '') && $user['status'] === 'Aktif') {
            $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'username' => $user['username'], 'role' => $user['role']];
            respond(['user' => $_SESSION['user']]);
        }
    }
    respond(['error' => 'Username atau password salah'], 401);
}

if ($method === 'POST' && ($segments[0] ?? '') === 'logout') {
    session_destroy();
    respond(['ok' => true]);
}

if (($segments[0] ?? '') === 'me') {
    respond(['user' => $_SESSION['user'] ?? null]);
}

requireLogin();
$data = $store->read();
$resource = $segments[0] ?? '';

if ($method === 'GET' && $resource === 'catalog') {
    $catalog = [];
    foreach ($data['products'] as $product) {
        $catalog[] = $product + ['type' => 'product'];
    }
    foreach ($data['carwashProducts'] as $product) {
        $catalog[] = $product + ['type' => 'carwash', 'categoryId' => 'carwash', 'hasVariants' => false, 'variants' => []];
    }
    usort($catalog, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    respond(['categories' => $data['categories'], 'items' => $catalog]);
}

if ($resource === 'products') {
    if ($method === 'POST') {
        $input = body();
        $product = [
            'id' => nextId($data['products']),
            'name' => trim((string) ($input['name'] ?? '')),
            'categoryId' => (int) ($input['categoryId'] ?? 1),
            'hasVariants' => (bool) ($input['hasVariants'] ?? false),
            'variants' => $input['variants'] ?? [],
            'price' => isset($input['price']) ? (int) $input['price'] : null,
            'image' => $input['image'] ?? ''
        ];
        if ($product['name'] === '') respond(['error' => 'Product name is required'], 422);
        $data['products'][] = $product;
        $store->write($data);
        respond(['product' => $product], 201);
    }
    if ($method === 'DELETE' && isset($segments[1])) {
        $id = (int) $segments[1];
        $data['products'] = array_values(array_filter($data['products'], fn($row) => (int) $row['id'] !== $id));
        $store->write($data);
        respond(['ok' => true]);
    }
}

if ($resource === 'carwash-products') {
    if ($method === 'POST') {
        $input = body();
        $product = ['id' => nextId($data['carwashProducts']), 'name' => trim((string) ($input['name'] ?? '')), 'price' => (int) ($input['price'] ?? 0)];
        if ($product['name'] === '' || $product['price'] <= 0) respond(['error' => 'Name and price are required'], 422);
        $data['carwashProducts'][] = $product;
        $store->write($data);
        respond(['product' => $product], 201);
    }
}

if ($resource === 'orders') {
    if ($method === 'GET') {
        respond(['orders' => array_reverse($data['orders'])]);
    }
    if ($method === 'POST') {
        $input = body();
        $items = $input['items'] ?? [];
        if (!is_array($items) || count($items) === 0) respond(['error' => 'Order must include items'], 422);

        $normalized = [];
        $total = 0;
        foreach ($items as $item) {
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $finalPrice = (int) ($item['finalPrice'] ?? $item['unitPrice'] ?? 0);
            $lineTotal = $finalPrice * $qty;
            $row = [
                'type' => $item['type'] ?? $item['cartType'] ?? 'product',
                'productId' => $item['productId'] ?? $item['id'] ?? null,
                'name' => (string) ($item['name'] ?? 'Item'),
                'qty' => $qty,
                'unitPrice' => (int) ($item['unitPrice'] ?? $finalPrice),
                'finalPrice' => $finalPrice,
                'total' => $lineTotal,
                'notes' => $item['notes'] ?? ''
            ];
            if ($row['type'] === 'carwash') {
                $row['nopol'] = $item['nopol'] ?? '';
                $row['service'] = $item['service'] ?? '';
                $row['ukuran'] = $item['ukuran'] ?? '';
                $row['vacuum'] = $item['vacuum'] ?? 'no';
                $row['profitPegawai'] = (int) round($lineTotal * 0.30);
                $row['profitManagement'] = $lineTotal - $row['profitPegawai'];
            }
            $total += $lineTotal;
            $normalized[] = $row;
        }

        $paid = (int) ($input['paidAmount'] ?? 0);
        $order = [
            'id' => nextId($data['orders']),
            'tableNumber' => (string) ($input['tableNumber'] ?? ''),
            'paymentMethod' => (string) ($input['paymentMethod'] ?? 'cash'),
            'totalAmount' => $total,
            'paidAmount' => $paid,
            'changeAmount' => max(0, $paid - $total),
            'status' => $paid > 0 ? 'paid' : 'open',
            'createdAt' => date('Y-m-d H:i:s'),
            'items' => $normalized
        ];
        if ($order['tableNumber'] === '') respond(['error' => 'Table number is required'], 422);
        $data['orders'][] = $order;
        $store->write($data);
        respond(['order' => $order], 201);
    }
}

if ($resource === 'orders' && $method === 'PATCH' && isset($segments[1])) {
    $id = (int) $segments[1];
    $input = body();
    foreach ($data['orders'] as &$order) {
        if ((int) $order['id'] === $id) {
            $paid = (int) ($input['paidAmount'] ?? $order['totalAmount']);
            $order['paymentMethod'] = (string) ($input['paymentMethod'] ?? $order['paymentMethod']);
            $order['paidAmount'] = $paid;
            $order['changeAmount'] = max(0, $paid - (int) $order['totalAmount']);
            $order['status'] = 'paid';
            $store->write($data);
            respond(['order' => $order]);
        }
    }
    respond(['error' => 'Order not found'], 404);
}

if ($resource === 'expenses' && $method === 'POST') {
    $input = body();
    $expense = ['id' => nextId($data['expenses']), 'description' => trim((string) ($input['description'] ?? '')), 'total' => (int) ($input['total'] ?? 0), 'createdAt' => date('Y-m-d H:i:s')];
    if ($expense['description'] === '' || $expense['total'] <= 0) respond(['error' => 'Description and total are required'], 422);
    $data['expenses'][] = $expense;
    $store->write($data);
    respond(['expense' => $expense], 201);
}

if ($resource === 'closing' && $method === 'GET') {
    $orders = todayRows($data['orders']);
    $expenses = todayRows($data['expenses']);
    $summary = [
        'date' => date('Y-m-d'),
        'totalPenjualan' => array_sum(array_column($orders, 'totalAmount')),
        'cash' => array_sum(array_map(fn($o) => $o['paymentMethod'] === 'cash' ? (int) $o['totalAmount'] : 0, $orders)),
        'qris' => array_sum(array_map(fn($o) => $o['paymentMethod'] === 'qris' ? (int) $o['totalAmount'] : 0, $orders)),
        'card' => array_sum(array_map(fn($o) => in_array($o['paymentMethod'], ['credit_card', 'debit'], true) ? (int) $o['totalAmount'] : 0, $orders)),
        'cafe' => 0,
        'carwash' => 0,
        'expenses' => $expenses
    ];
    foreach ($orders as $order) {
        foreach ($order['items'] as $item) {
            $summary[$item['type'] === 'carwash' ? 'carwash' : 'cafe'] += (int) $item['total'];
        }
    }
    respond(['summary' => $summary]);
}

if ($resource === 'closing' && $method === 'POST') {
    $orders = todayRows($data['orders']);
    $expenses = todayRows($data['expenses']);
    $closing = ['id' => nextId($data['closings']), 'createdAt' => date('Y-m-d H:i:s'), 'orders' => $orders, 'expenses' => $expenses];
    $data['closings'][] = $closing;
    $store->write($data);
    respond(['closing' => $closing], 201);
}

respond(['error' => 'Endpoint not found'], 404);
