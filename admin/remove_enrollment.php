<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$enrollment_id = (int)$_GET['id'];
$return_url = isset($_GET['return']) ? $_GET['return'] : 'manage_classes.php';

if ($enrollment_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM enrollments WHERE id = ?");
        $stmt->execute([$enrollment_id]);
    } catch (PDOException $e) {
        // Handle constraint errors if any
    }
}

redirect($return_url);
?>
