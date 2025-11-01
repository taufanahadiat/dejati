<?php
date_default_timezone_set("Asia/Jakarta");

$sql_details = [
    'user' => 'root',
    'pass' => 'akpidev3',
    'db' => 'dejati',
    'host' => 'db'
];

$conn = mysqli_connect($sql_details['host'], $sql_details['user'], $sql_details['pass'], $sql_details['db']);

if (!$conn) {
    die("Gagal terhubung dengan database: " . mysqli_connect_error());
}

define('CDN_BASE', 'https://cdn.jsdelivr.net/gh/araisantai/assets-automated@main/assets-cafe/');