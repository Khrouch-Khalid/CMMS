<?php
session_start();

// Include database configuration
require_once '../config/config.php';

// Check if user is logged in and has technician role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'technician') {
    header('Location: ../auth/login.php');
    exit();
}

// Get parameters from URL
$maintenance_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? safe_input($_GET['action']) : '';

if ($maintenance_id === 0 || empty($action)) {
    header('Location: work_orders.php');
    exit();
}

// Validate action
if (!in_array($action, ['start', 'complete'])) {
    header('Location: work_orders.php');
    exit();
}

$success = false;
$message = '';

if ($action === 'start') {
    // Update maintenance to started
    $update_query = "UPDATE Maintenance SET Started_Date = NOW() WHERE Maintenance_ID = $maintenance_id";
    if (execute_query($update_query)) {
        $success = true;
        $message = 'Maintenance démarrée avec succès!';
    } else {
        $message = 'Erreur lors du démarrage de la maintenance';
    }
} elseif ($action === 'complete') {
    // Update maintenance to completed
    $update_query = "UPDATE Maintenance SET Completed_Date = NOW() WHERE Maintenance_ID = $maintenance_id";
    if (execute_query($update_query)) {
        $success = true;
        $message = 'Maintenance complétée avec succès!';
    } else {
        $message = 'Erreur lors de la complétion de la maintenance';
    }
}

// Redirect back to work orders list with message
if ($success) {
    header("Location: work_orders.php?message=" . urlencode($message) . "&type=success");
} else {
    header("Location: work_orders.php?message=" . urlencode($message) . "&type=error");
}
exit();
?>
