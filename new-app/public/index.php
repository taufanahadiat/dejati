<?php
if (str_starts_with(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/api')) {
    require __DIR__ . '/../api/index.php';
    exit;
}
require __DIR__ . '/index.html';
