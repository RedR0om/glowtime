<?php
require_once 'inc/bootstrap.php';
require_once 'staff.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) {
    delete_staff($id);
}
header('Location: admin_staff.php');
exit;