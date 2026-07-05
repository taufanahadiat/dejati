<?php
const APP_SESSION_LIFETIME = 21600; // 6 hours

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string)APP_SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', (string)APP_SESSION_LIFETIME);

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => APP_SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}