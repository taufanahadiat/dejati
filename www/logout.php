<?php
require_once __DIR__ . '/config/session.php';
session_start();
session_unset();
session_destroy();
header("Location: ./");
exit;