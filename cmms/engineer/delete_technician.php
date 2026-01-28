<?php
session_start();

require_once '../config/config.php';

// Check if user is logged in and has engineer role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

// Get technician ID from URL
$technician_id = intval($_GET['technician_id'] ?? 0);

if ($technician_id <= 0) {
    $_SESSION['error'] = 'Technicien non trouvé';
    header('Location: users.php');
    exit();
}

// Get technician and employee information
$tech_query = "SELECT t.*, e.Employee_ID FROM Technicians t
               INNER JOIN Employees e ON t.Employee_ID = e.Employee_ID
               WHERE t.Technician_ID = $technician_id";
$technician = fetch_one($tech_query);

if (!$technician) {
    $_SESSION['error'] = 'Technicien non trouvé';
    header('Location: users.php');
    exit();
}

$employee_id = $technician['Employee_ID'];

// Start transaction to ensure all deletes happen or none
mysqli_begin_transaction($conn);

try {
    // 1. Delete all maintenance assignments for this technician
    $delete_maintenance = "UPDATE Maintenance SET Assigned_Technician_ID = NULL 
                           WHERE Assigned_Technician_ID = $technician_id";
    if (!mysqli_query($conn, $delete_maintenance)) {
        throw new Exception("Erreur lors de la mise à jour des tâches de maintenance");
    }
    
    // 2. Delete user login account
    $delete_user = "DELETE FROM Users WHERE Employee_ID = $employee_id";
    if (!mysqli_query($conn, $delete_user)) {
        throw new Exception("Erreur lors de la suppression du compte utilisateur");
    }
    
    // 3. Delete technician record
    $delete_technician = "DELETE FROM Technicians WHERE Technician_ID = $technician_id";
    if (!mysqli_query($conn, $delete_technician)) {
        throw new Exception("Erreur lors de la suppression du technicien");
    }
    
    // 4. Delete employee record
    $delete_employee = "DELETE FROM Employees WHERE Employee_ID = $employee_id";
    if (!mysqli_query($conn, $delete_employee)) {
        throw new Exception("Erreur lors de la suppression de l'employé");
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    $_SESSION['success'] = 'Technicien supprimé avec succès';
    header('Location: users.php');
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    $_SESSION['error'] = 'Erreur lors de la suppression: ' . $e->getMessage();
    header('Location: users.php');
    exit();
}

mysqli_close($conn);
?>
