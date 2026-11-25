<?php
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect_to_role_dashboard('../');
} else {
    redirect('../login.php');
}

