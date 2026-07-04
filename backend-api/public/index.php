<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

final class JsonStore
{
    public function __construct(private string $file, private string $seedFile)
    {
        if (!file_exists($this->file)) {
            copy($this->seedFile, $this->file);
        }
    }

    public function read(): array
    {
        $data = json_decode(file_get_contents($this->file) ?: '{}', true);
        return is_array($data) ? $data : [];
    }

    public function write(array $data): void
    {
        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}

final class TokenStore
{
    public function __construct(private string $file)
    {
        if (!file_exists($this->file)) {
            file_put_contents($this->file, '{}');
        }
    }

    public function all(): array
    {
        $data = json_decode(file_get_contents($this->file) ?: '{}', true);
        return is_array($data) ? $data : [];
    }

    public function put(string $token, array $user): void
    {
        $tokens = $this->all();
        $tokens[$token] = ['user' => $user, 'createdAt' => date('Y-m-d H:i:s')];
        file_put_contents($this->file, json_encode($tokens, JSON_PRETTY_PRINT), LOCK_EX);
    }

    public function get(string $token): ?array
    {
        $entry = $this->all()[$token] ?? null;
        return is_array($entry) ? ($entry['user'] ?? null) : null;
    }

    public function delete(string $token): void
    {
        $tokens = $this->all();
        unset($tokens[$token]);
        file_put_contents($this->file, json_encode($tokens, JSON_PRETTY_PRINT), LOCK_EX);
    }
}

$store = new JsonStore(__DIR__ . '/../data/app.json', __DIR__ . '/../data/seed.json');
$tokens = new TokenStore(__DIR__ . '/../data/tokens.json');
$method = $_SERVER['REQUEST_METHOD'];
$uriPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
$segments = $uriPath === '' ? [] : explode('/', $uriPath);

function input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    return is_array($json) ? $json : $_POST;
}

function out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function bearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $header, $m)) {
        return trim($m[1]);
    }
    return null;
}

function nextId(array $rows): int
{
    return empty($rows) ? 1 : max(array_map(fn($row) => (int) $row['id'], $rows)) + 1;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function inRange(string $createdAt, ?string $from, ?string $to): bool
{
    $date = substr($createdAt, 0, 10);
    if ($from && $date < $from) return false;
    if ($to && $date > $to) return false;
    return true;
}

function publicUser(array $user): array
{
    return [
        'id' => $user['id'],
        'username' => $user['username'],
        'name' => $user['name'],
        'role' => $user['role'],
        'status' => $user['status'] ?? 'Aktif',
    ];
}

function makeCatalog(array $data): array
{
    $items = [];
    foreach ($data['products'] ?? [] as $product) {
        $items[] = $product + ['type' => 'product'];
    }
    foreach ($data['carwashProducts'] ?? [] as $product) {
        $items[] = $product + ['type' => 'carwash', 'categoryId' => 'carwash', 'hasVariants' => false, 'variants' => []];
    }
    usort($items, fn($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']));
    return ['categories' => $data['categories'] ?? [], 'items' => $items];
}

function summarize(array $orders, array $expenses, ?string $from = null, ?string $to = null): array
{
    $filteredOrders = array_values(array_filter($orders, fn($o) => inRange($o['createdAt'] ?? '', $from, $to)));
    $filteredExpenses = array_values(array_filter($expenses, fn($e) => inRange($e['createdAt'] ?? '', $from, $to)));
    $summary = [
        'from' => $from,
        'to' => $to,
        'transactionCount' => count($filteredOrders),
        'totalPenjualan' => 0,
        'cash' => 0,
        'qris' => 0,
        'card' => 0,
        'cafe' => 0,
        'carwash' => 0,
        'profitPegawai' => 0,
        'profitManagement' => 0,
        'pengeluaran' => array_sum(array_map(fn($e) => (int) ($e['total'] ?? 0), $filteredExpenses)),
        'net' => 0,
        'expenses' => $filteredExpenses,
    ];
    foreach ($filteredOrders as $order) {
        $amount = (int) ($order['totalAmount'] ?? 0);
        $summary['totalPenjualan'] += $amount;
        $method = $order['paymentMethod'] ?? '';
        if ($method === 'cash') $summary['cash'] += $amount;
        if ($method === 'qris') $summary['qris'] += $amount;
        if (in_array($method, ['credit_card', 'debit', 'card'], true)) $summary['card'] += $amount;
        foreach ($order['items'] ?? [] as $item) {
            if (($item['type'] ?? '') === 'carwash') {
                $summary['carwash'] += (int) ($item['total'] ?? 0);
                $summary['profitPegawai'] += (int) ($item['profitPegawai'] ?? 0);
                $summary['profitManagement'] += (int) ($item['profitManagement'] ?? 0);
            } else {
                $summary['cafe'] += (int) ($item['total'] ?? 0);
            }
        }
    }
    $summary['net'] = $summary['totalPenjualan'] - $summary['pengeluaran'];
    return $summary;
}

$data = $store->read();
$resource = $segments[0] ?? '';

if ($resource === 'health') {
    out(['ok' => true, 'service' => 'dejati-mobile-api', 'time' => now()]);
}

if ($resource === 'auth' && ($segments[1] ?? '') === 'login' && $method === 'POST') {
    $payload = input();
    foreach ($data['users'] ?? [] as $user) {
        if (($user['username'] ?? '') === ($payload['username'] ?? '') && ($user['password'] ?? '') === ($payload['password'] ?? '') && ($user['status'] ?? 'Aktif') === 'Aktif') {
            $token = bin2hex(random_bytes(32));
            $safeUser = publicUser($user);
            $tokens->put($token, $safeUser);
            out(['token' => $token, 'user' => $safeUser]);
        }
    }
    out(['error' => 'Username atau password salah'], 401);
}

$token = bearerToken();
$currentUser = $token ? $tokens->get($token) : null;
if (!$currentUser) {
    out(['error' => 'Bearer token required'], 401);
}

if ($resource === 'auth' && ($segments[1] ?? '') === 'logout' && $method === 'POST') {
    $tokens->delete($token);
    out(['ok' => true]);
}

if ($resource === 'auth' && ($segments[1] ?? '') === 'me') {
    out(['user' => $currentUser]);
}

if ($resource === 'catalog' && $method === 'GET') {
    out(makeCatalog($data));
}

if ($resource === 'categories') {
    if ($method === 'GET') out(['categories' => $data['categories'] ?? []]);
    if ($method === 'POST') {
        $payload = input();
        $row = ['id' => nextId($data['categories'] ?? []), 'name' => trim((string) ($payload['name'] ?? '')), 'icon' => $payload['icon'] ?? 'category'];
        if ($row['name'] === '') out(['error' => 'Category name is required'], 422);
        $data['categories'][] = $row;
        $store->write($data);
        out(['category' => $row], 201);
    }
}

if ($resource === 'products') {
    if ($method === 'GET') out(['products' => $data['products'] ?? []]);
    if ($method === 'POST') {
        $payload = input();
        $row = [
            'id' => nextId($data['products'] ?? []),
            'name' => trim((string) ($payload['name'] ?? '')),
            'categoryId' => (int) ($payload['categoryId'] ?? 1),
            'hasVariants' => (bool) ($payload['hasVariants'] ?? false),
            'variants' => array_values($payload['variants'] ?? []),
            'price' => isset($payload['price']) ? (int) $payload['price'] : null,
            'image' => $payload['image'] ?? '',
            'updatedAt' => now(),
            'updatedBy' => $currentUser['id'],
        ];
        if ($row['name'] === '') out(['error' => 'Product name is required'], 422);
        $data['products'][] = $row;
        $store->write($data);
        out(['product' => $row], 201);
    }
    if (($method === 'PUT' || $method === 'PATCH') && isset($segments[1])) {
        $id = (int) $segments[1];
        $payload = input();
        foreach ($data['products'] as &$row) {
            if ((int) $row['id'] === $id) {
                foreach (['name', 'categoryId', 'hasVariants', 'variants', 'price', 'image'] as $field) {
                    if (array_key_exists($field, $payload)) $row[$field] = $payload[$field];
                }
                $row['updatedAt'] = now();
                $row['updatedBy'] = $currentUser['id'];
                $store->write($data);
                out(['product' => $row]);
            }
        }
        out(['error' => 'Product not found'], 404);
    }
    if ($method === 'DELETE' && isset($segments[1])) {
        $id = (int) $segments[1];
        $data['products'] = array_values(array_filter($data['products'] ?? [], fn($row) => (int) $row['id'] !== $id));
        $store->write($data);
        out(['ok' => true]);
    }
}

if ($resource === 'carwash-products') {
    if ($method === 'GET') out(['products' => $data['carwashProducts'] ?? []]);
    if ($method === 'POST') {
        $payload = input();
        $row = ['id' => nextId($data['carwashProducts'] ?? []), 'name' => trim((string) ($payload['name'] ?? '')), 'price' => (int) ($payload['price'] ?? 0), 'updatedAt' => now(), 'updatedBy' => $currentUser['id']];
        if ($row['name'] === '' || $row['price'] <= 0) out(['error' => 'Name and price are required'], 422);
        $data['carwashProducts'][] = $row;
        $store->write($data);
        out(['product' => $row], 201);
    }
}

if ($resource === 'transactions') {
    if ($method === 'GET' && !isset($segments[1])) {
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $orders = array_values(array_filter($data['orders'] ?? [], fn($o) => inRange($o['createdAt'] ?? '', $from, $to)));
        usort($orders, fn($a, $b) => strcmp($b['createdAt'], $a['createdAt']));
        out(['transactions' => $orders]);
    }
    if ($method === 'GET' && isset($segments[1])) {
        foreach ($data['orders'] ?? [] as $order) if ((int) $order['id'] === (int) $segments[1]) out(['transaction' => $order]);
        out(['error' => 'Transaction not found'], 404);
    }
    if ($method === 'POST') {
        $payload = input();
        $items = $payload['items'] ?? [];
        if (!is_array($items) || count($items) === 0) out(['error' => 'Transaction requires items'], 422);
        $normalized = [];
        $total = 0;
        foreach ($items as $item) {
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $finalPrice = (int) ($item['finalPrice'] ?? $item['unitPrice'] ?? 0);
            $lineTotal = $finalPrice * $qty;
            $row = [
                'type' => $item['type'] ?? 'product',
                'productId' => $item['productId'] ?? $item['id'] ?? null,
                'name' => (string) ($item['name'] ?? 'Item'),
                'qty' => $qty,
                'unitPrice' => (int) ($item['unitPrice'] ?? $finalPrice),
                'finalPrice' => $finalPrice,
                'total' => $lineTotal,
                'notes' => $item['notes'] ?? '',
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
        $paid = (int) ($payload['paidAmount'] ?? 0);
        $order = [
            'id' => nextId($data['orders'] ?? []),
            'tableNumber' => (string) ($payload['tableNumber'] ?? ''),
            'paymentMethod' => (string) ($payload['paymentMethod'] ?? 'cash'),
            'totalAmount' => $total,
            'paidAmount' => $paid,
            'changeAmount' => max(0, $paid - $total),
            'status' => $paid > 0 ? 'paid' : 'open',
            'createdAt' => now(),
            'createdBy' => $currentUser['id'],
            'items' => $normalized,
        ];
        if ($order['tableNumber'] === '') out(['error' => 'Table number is required'], 422);
        $data['orders'][] = $order;
        $store->write($data);
        out(['transaction' => $order], 201);
    }
    if ($method === 'PATCH' && isset($segments[1]) && ($segments[2] ?? '') === 'finalize') {
        $payload = input();
        foreach ($data['orders'] as &$order) {
            if ((int) $order['id'] === (int) $segments[1]) {
                $paid = (int) ($payload['paidAmount'] ?? $order['totalAmount']);
                $order['paymentMethod'] = (string) ($payload['paymentMethod'] ?? $order['paymentMethod']);
                $order['paidAmount'] = $paid;
                $order['changeAmount'] = max(0, $paid - (int) $order['totalAmount']);
                $order['status'] = 'paid';
                $order['finalizedAt'] = now();
                $store->write($data);
                out(['transaction' => $order]);
            }
        }
        out(['error' => 'Transaction not found'], 404);
    }
}

if ($resource === 'reports') {
    $from = $_GET['from'] ?? date('Y-m-d');
    $to = $_GET['to'] ?? $from;
    if (($segments[1] ?? '') === 'summary') out(['summary' => summarize($data['orders'] ?? [], $data['expenses'] ?? [], $from, $to)]);
    if (($segments[1] ?? '') === 'daily') out(['summary' => summarize($data['orders'] ?? [], $data['expenses'] ?? [], date('Y-m-d'), date('Y-m-d'))]);
}

if ($resource === 'expenses') {
    if ($method === 'GET') out(['expenses' => $data['expenses'] ?? []]);
    if ($method === 'POST') {
        $payload = input();
        $row = ['id' => nextId($data['expenses'] ?? []), 'description' => trim((string) ($payload['description'] ?? '')), 'total' => (int) ($payload['total'] ?? 0), 'createdAt' => now(), 'createdBy' => $currentUser['id']];
        if ($row['description'] === '' || $row['total'] <= 0) out(['error' => 'Description and total are required'], 422);
        $data['expenses'][] = $row;
        $store->write($data);
        out(['expense' => $row], 201);
    }
}

if ($resource === 'closing') {
    if ($method === 'GET') out(['summary' => summarize($data['orders'] ?? [], $data['expenses'] ?? [], date('Y-m-d'), date('Y-m-d'))]);
    if ($method === 'POST') {
        $summary = summarize($data['orders'] ?? [], $data['expenses'] ?? [], date('Y-m-d'), date('Y-m-d'));
        $row = ['id' => nextId($data['closings'] ?? []), 'createdAt' => now(), 'createdBy' => $currentUser['id'], 'summary' => $summary];
        $data['closings'][] = $row;
        $store->write($data);
        out(['closing' => $row], 201);
    }
}

if ($resource === 'users') {
    if ($method === 'GET') out(['users' => array_map('publicUser', $data['users'] ?? [])]);
    if ($method === 'POST') {
        $payload = input();
        $row = ['id' => nextId($data['users'] ?? []), 'username' => trim((string) ($payload['username'] ?? '')), 'name' => trim((string) ($payload['name'] ?? '')), 'password' => (string) ($payload['password'] ?? 'admin123'), 'role' => $payload['role'] ?? 'Kasir', 'status' => $payload['status'] ?? 'Aktif'];
        if ($row['username'] === '' || $row['name'] === '') out(['error' => 'Username and name are required'], 422);
        $data['users'][] = $row;
        $store->write($data);
        out(['user' => publicUser($row)], 201);
    }
}

if ($resource === 'printer-settings') {
    if ($method === 'GET') out(['settings' => $data['printerSettings'] ?? []]);
    if ($method === 'PUT' || $method === 'PATCH') {
        $data['printerSettings'] = array_merge($data['printerSettings'] ?? [], input());
        $store->write($data);
        out(['settings' => $data['printerSettings']]);
    }
}

out(['error' => 'Endpoint not found'], 404);
