<?php
require_once __DIR__ . '/../includes/functions.php';
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}
redirect('../login.php');
