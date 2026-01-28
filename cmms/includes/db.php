<?php
// Include configuration
require_once dirname(__DIR__) . '/config/config.php';

// Database helper functions are already defined in config.php
// This file is for backward compatibility and can be used for additional database utilities

// Function to check if email exists
function email_exists($email) {
    $query = "SELECT Employee_ID FROM Employees WHERE Email = '" . safe_input($email) . "'";
    $result = fetch_one($query);
    return !empty($result);
}

// Function to get user by email
function get_user_by_email($email) {
    $email = safe_input($email);
    $query = "SELECT * FROM Employees WHERE Email = '$email'";
    return fetch_one($query);
}

// Function to get employee by ID
function get_employee($employee_id) {
    $employee_id = intval($employee_id);
    $query = "SELECT * FROM Employees WHERE Employee_ID = $employee_id";
    return fetch_one($query);
}

// Function to get engineer by employee ID
function get_engineer($employee_id) {
    $employee_id = intval($employee_id);
    $query = "SELECT * FROM Biomedical_Engineers WHERE Employee_ID = $employee_id";
    return fetch_one($query);
}

// Function to get technician by employee ID
function get_technician($employee_id) {
    $employee_id = intval($employee_id);
    $query = "SELECT * FROM Technicians WHERE Employee_ID = $employee_id";
    return fetch_one($query);
}

// Function to get all equipment
function get_all_equipment() {
    $query = "SELECT a.*, at.Type_Name, d.Department_Name, s.Status_Name, c.Level_Name 
              FROM Assets a 
              LEFT JOIN Asset_Types at ON a.Asset_Type_ID = at.Type_ID
              LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
              LEFT JOIN Asset_Status s ON a.Status_ID = s.Status_ID
              LEFT JOIN Criticality_Level c ON a.Criticality_Level_ID = c.Level_ID
              ORDER BY a.Asset_Name";
    return fetch_all($query);
}

// Function to get maintenance count by status
function get_maintenance_count($status = null) {
    $query = "SELECT COUNT(*) as count FROM Maintenance";
    if ($status) {
        // You can add status filter here
    }
    $result = fetch_one($query);
    return $result['count'] ?? 0;
}

// Function to get technician count
function get_technician_count() {
    $query = "SELECT COUNT(*) as count FROM Technicians WHERE Employee_ID IN (SELECT Employee_ID FROM Employees WHERE Status_ID = 1)";
    $result = fetch_one($query);
    return $result['count'] ?? 0;
}

// Function to get equipment count
function get_equipment_count() {
    $query = "SELECT COUNT(*) as count FROM Assets WHERE Status_ID != 5";
    $result = fetch_one($query);
    return $result['count'] ?? 0;
}

// Function to get maintenance in progress count
function get_maintenance_progress_count() {
    $query = "SELECT COUNT(*) as count FROM Maintenance WHERE Started_Date IS NOT NULL AND Completed_Date IS NULL";
    $result = fetch_one($query);
    return $result['count'] ?? 0;
}

// Function to get active technicians count
function get_active_technicians_count() {
    $query = "SELECT COUNT(*) as count FROM Technicians t 
              INNER JOIN Employees e ON t.Employee_ID = e.Employee_ID 
              WHERE e.Status_ID = 1";
    $result = fetch_one($query);
    return $result['count'] ?? 0;
}

?>
