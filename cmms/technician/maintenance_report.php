<?php
session_start();

require_once '../config/config.php';

// Check if user is logged in and has technician role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'technician') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Technicien';

// Get maintenance ID from URL
$maintenance_id = intval($_GET['maintenance_id'] ?? 0);

if ($maintenance_id <= 0) {
    header('Location: work_orders.php');
    exit();
}

// Fetch maintenance details
$maintenance_query = "SELECT m.*, a.Asset_Name, a.Model, a.Asset_ID, mt.Type_Name, 
                             d.Department_Name, e.First_Name, e.Last_Name
                      FROM Maintenance m
                      INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
                      INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
                      LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
                      LEFT JOIN Biomedical_Engineers be ON m.Assigned_Engineer_ID = be.Engineer_ID
                      LEFT JOIN Employees e ON be.Employee_ID = e.Employee_ID
                      WHERE m.Maintenance_ID = $maintenance_id";

$maintenance = fetch_one($maintenance_query);

if (!$maintenance) {
    header('Location: work_orders.php');
    exit();
}

// Handle report submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $work_description = mysqli_real_escape_string($conn, $_POST['work_description'] ?? '');
    $maintenance_type = safe_input($_POST['maintenance_type'] ?? '');
    $failure_cause = mysqli_real_escape_string($conn, $_POST['failure_cause'] ?? '');
    $parts_used = mysqli_real_escape_string($conn, $_POST['parts_used'] ?? '');
    $parts_ordered = mysqli_real_escape_string($conn, $_POST['parts_ordered'] ?? '');
    $work_completed = isset($_POST['work_completed']) ? 1 : 0;
    $client_satisfaction = safe_input($_POST['client_satisfaction'] ?? '');
    $hours_worked = floatval($_POST['hours_worked'] ?? 0);
    
    if (empty($work_description)) {
        $error_message = "Veuillez décrire les travaux réalisés";
    } else {
        // Create Maintenance Report record
        $report_query = "INSERT INTO Maintenance_Reports 
                        (Maintenance_ID, Work_Description, Maintenance_Type, Failure_Cause, 
                         Parts_Used, Parts_Ordered, Work_Completed, Client_Satisfaction, 
                         Hours_Worked, Report_Date, Technician_ID)
                        VALUES 
                        ($maintenance_id, '$work_description', '$maintenance_type', '$failure_cause',
                         '$parts_used', '$parts_ordered', $work_completed, '$client_satisfaction',
                         $hours_worked, NOW(), " . 
                        (isset($_SESSION['employee_id']) ? intval($_SESSION['employee_id']) : 0) . ")";
        
        if (execute_query($report_query)) {
            $report_id = get_last_id();
            
            // Update Maintenance table to link report
            $update_maintenance_query = "UPDATE Maintenance SET Report_Filed = 1, Report_ID = $report_id WHERE Maintenance_ID = $maintenance_id";
            execute_query($update_maintenance_query);
            
            // Get asset ID from maintenance record
            $asset_query = "SELECT Asset_ID FROM Maintenance WHERE Maintenance_ID = $maintenance_id";
            $asset_result = fetch_one($asset_query);
            if ($asset_result) {
                $asset_id = $asset_result['Asset_ID'];
                // Update equipment status back to Opérationnel (Status_ID = 1)
                $update_equipment = "UPDATE Assets SET Status_ID = 1 WHERE Asset_ID = $asset_id";
                execute_query($update_equipment);
            }
            
            $success_message = "Rapport d'intervention sauvegardé et envoyé à l'ingénieur!";
            // Redirect after 2 seconds
            header("Refresh: 2; url=work_orders.php");
        } else {
            $error_message = "Erreur lors de la sauvegarde du rapport: " . get_error();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport d'Intervention - Technicien | CMMS Hôpital</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            font-size: 24px;
            font-weight: bold;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-menu li {
            margin-bottom: 5px;
        }
        
        .nav-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }
        
        .logout-btn {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 20px;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }
        
        .header {
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .report-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            max-width: 900px;
        }
        
        .report-header {
            text-align: center;
            border-bottom: 3px solid #ff6b6b;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .report-header h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .report-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 15px;
            color: #333;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #ff6b6b;
            margin-bottom: 15px;
            text-transform: uppercase;
            border-bottom: 2px solid #ff6b6b;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #ff6b6b;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: auto;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-submit {
            background: #ff6b6b;
            color: white;
        }
        
        .btn-submit:hover {
            background: #ee5a6f;
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            background: #e0e0e0;
            color: #333;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-cancel:hover {
            background: #d0d0d0;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">⚙️ <span>CMMS</span></div>
            <ul class="nav-menu">
                <li><a href="dashboard.php">📊 Tableau de Bord</a></li>
                <li><a href="work_orders.php" class="active">📋 Ordres de Travail</a></li>
                <li><a href="maintenance.php">🔧 Maintenance</a></li>
                <li><a href="history.php">📜 Historique</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>📝 Rapport d'Intervention de Maintenance</h1>
            </div>
            
            <div class="report-container">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">✓ <?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">✗ <?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                
                <!-- Report Header -->
                <div class="report-header">
                    <h2>RAPPORT D'INTERVENTION</h2>
                    <p>Maintenance de l'Équipement Médical</p>
                </div>
                
                <!-- Equipment Information -->
                <div class="report-info">
                    <div class="info-item">
                        <span class="info-label">Équipement</span>
                        <span class="info-value"><?php echo htmlspecialchars($maintenance['Asset_Name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Modèle</span>
                        <span class="info-value"><?php echo htmlspecialchars($maintenance['Model'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Type de Maintenance</span>
                        <span class="info-value"><?php echo htmlspecialchars($maintenance['Type_Name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Département</span>
                        <span class="info-value"><?php echo htmlspecialchars($maintenance['Department_Name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Ingénieur Responsable</span>
                        <span class="info-value"><?php echo htmlspecialchars(($maintenance['First_Name'] ?? '') . ' ' . ($maintenance['Last_Name'] ?? '')); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Technicien</span>
                        <span class="info-value"><?php echo htmlspecialchars($user_name); ?></span>
                    </div>
                </div>
                
                <!-- Report Form -->
                <form method="POST">
                    <!-- Work Description Section -->
                    <div class="form-section">
                        <div class="section-title">📋 Travaux Réalisés</div>
                        
                        <div class="form-group">
                            <label for="work_description">Description des Travaux *</label>
                            <textarea id="work_description" name="work_description" required placeholder="Décrivez en détail les travaux effectués..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Type de Maintenance *</label>
                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="radio" name="maintenance_type" value="preventive" checked>
                                    Maintenance Préventive
                                </label>
                                <label class="checkbox-item">
                                    <input type="radio" name="maintenance_type" value="corrective">
                                    Maintenance Corrective
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="hours_worked">Heures de Travail</label>
                            <input type="number" id="hours_worked" name="hours_worked" min="0" step="0.5" placeholder="Nombre d'heures">
                        </div>
                    </div>
                    
                    <!-- Failure Information -->
                    <div class="form-section">
                        <div class="section-title">🔴 Cause de la Panne</div>
                        
                        <div class="form-group">
                            <label for="failure_cause">Description de la Cause (si applicable)</label>
                            <textarea id="failure_cause" name="failure_cause" placeholder="Décrivez la cause identifiée..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Parts Section -->
                    <div class="form-section">
                        <div class="section-title">🔧 Pièces de Rechange</div>
                        
                        <div class="form-group">
                            <label for="parts_used">Pièces Utilisées</label>
                            <textarea id="parts_used" name="parts_used" placeholder="Listez les références et descriptions des pièces utilisées..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="parts_ordered">Pièces à Commander</label>
                            <textarea id="parts_ordered" name="parts_ordered" placeholder="Listez les références des pièces à commander..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Completion Section -->
                    <div class="form-section">
                        <div class="section-title">✓ Statut</div>
                        
                        <div class="form-group">
                            <label class="checkbox-item">
                                <input type="checkbox" name="work_completed">
                                Travail Complètement Terminé
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Satisfaction du Client</label>
                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="radio" name="client_satisfaction" value="satisfied">
                                    Très Satisfait
                                </label>
                                <label class="checkbox-item">
                                    <input type="radio" name="client_satisfaction" value="neutral">
                                    Neutre
                                </label>
                                <label class="checkbox-item">
                                    <input type="radio" name="client_satisfaction" value="unsatisfied">
                                    Insatisfait
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="form-actions">
                        <a href="work_order_detail.php?id=<?php echo $maintenance_id; ?>" class="btn btn-cancel">← Retour</a>
                        <button type="submit" class="btn btn-submit">📤 Envoyer le Rapport</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
