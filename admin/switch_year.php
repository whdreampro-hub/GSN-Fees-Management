<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_year_id'])) {
    $year_id = (int)$_POST['switch_year_id'];
    $_SESSION['active_academic_year_id'] = $year_id;
    
    // Redirect back to the referring page or dashboard
    $referrer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
    header("Location: " . $referrer);
    exit();
}

header("Location: dashboard.php");
exit();
