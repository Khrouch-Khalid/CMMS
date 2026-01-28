<?php
session_start();

// Include database configuration
require_once '../config/config.php';

// Check if user is logged in and has engineer role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

// Get POST data
$panne_id = intval($_POST['panne_id'] ?? 0);

if ($panne_id) {
    // Get engineer ID
    $engineer_name = isset($_SESSION['name']) ? $_SESSION['name'] : '';
    $engineer_id = 0;

    if (!empty($engineer_name)) {
        // Split name into first and last name
        $name_parts = explode(' ', trim($engineer_name));
        $first_name = $name_parts[0] ?? '';
        $last_name = isset($name_parts[1]) ? implode(' ', array_slice($name_parts, 1)) : '';
        
        // Find engineer by name
        $engineer_query = "SELECT bg.Engineer_ID FROM Biomedical_Engineers bg
                           INNER JOIN Employees e ON bg.Employee_ID = e.Employee_ID
                           WHERE e.First_Name = '" . mysqli_real_escape_string($GLOBALS['conn'], $first_name) . "'
                           AND e.Last_Name = '" . mysqli_real_escape_string($GLOBALS['conn'], $last_name) . "'";
        $engineer_result = fetch_one($engineer_query);
        $engineer_id = $engineer_result['Engineer_ID'] ?? 0;
    }
    
    if ($engineer_id) {
        // Update panne status to "Vue" (Seen/Acknowledged)
        $update_query = "UPDATE Panne_Declarations 
                        SET Status = 'Vue', 
                            Accepted_Date = NOW(), 
                            Accepted_By_Engineer_ID = " . intval($engineer_id) . "
                        WHERE Panne_Declaration_ID = " . intval($panne_id);
        
        $result = execute_query($update_query);
        
        if ($result) {
            // Get panne details to create maintenance record
            $panne_details = fetch_one("SELECT * FROM Panne_Declarations WHERE Panne_Declaration_ID = " . intval($panne_id));
            
            if ($panne_details) {
                // Get technician ID from the panne declaration
                $technician_id = $panne_details['Technician_ID'] ?? 0;
                
                // Create corrective maintenance record and assign to technician
                $maintenance_query = "INSERT INTO Maintenance (Asset_ID, Type_ID, Priority_ID, Reported_Date, Scheduled_Date, Assigned_Engineer_ID, Assigned_Technician_ID, Description)
                                    VALUES (" . intval($panne_details['Asset_ID']) . ", 
                                           (SELECT Type_ID FROM Maintenance_Type WHERE Type_Name = 'Maintenance Corrective'), 
                                           (SELECT Priority_ID FROM Maintenance_Priority WHERE Priority_Name = 'Urgence'), 
                                           NOW(), NOW(), " . intval($engineer_id) . ", 
                                           " . intval($technician_id) . ", 
                                           '" . mysqli_real_escape_string($GLOBALS['conn'], "Panne déclarée par technicien: " . $panne_details['Description']) . "')";
                
                if (execute_query($maintenance_query)) {
                    // Update equipment status to "En Maintenance" (Status_ID = 2)
                    $update_equipment = "UPDATE Assets SET Status_ID = 2 WHERE Asset_ID = " . intval($panne_details['Asset_ID']);
                    execute_query($update_equipment);
                }
                
                $_SESSION['success_message'] = 'Panne vue et ajoutée à la maintenance corrective. Vérifiez la page de maintenance corrective.';
            }
        }
    }
}

// Redirect back to dashboard
header('Location: dashboard.php');
exit();
?>
