<?php
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    $role = get_current_user_role();
    if ($role === 'parent') {
        redirect('dashboard.php');
    } elseif (in_array($role, ['super_admin', 'admin', 'principal'])) {
        redirect('../admin/dashboard.php');
    } elseif ($role === 'teacher') {
        redirect('../teacher/dashboard.php');
    } elseif ($role === 'accountant') {
        redirect('../accountant/dashboard.php');
    } else {
        redirect('../index.php');
    }
} else {
    redirect('../login.php');
}
