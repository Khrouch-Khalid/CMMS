<?php
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';

// Check if we have a maintenance_id to pre-fill form
$maintenance_id = isset($_GET['maintenance_id']) ? intval($_GET['maintenance_id']) : 0;
$panne_id = isset($_GET['panne_id']) ? intval($_GET['panne_id']) : 0;
$pre_filled_maintenance = null;
$pre_filled_asset_id = '';
$pre_filled_type_id = '';
$pre_filled_priority_id = '';
$pre_filled_description = '';

if ($maintenance_id > 0) {
    $maintenance_query = "SELECT m.*, a.Asset_ID FROM Maintenance m
                          INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
                          WHERE m.Maintenance_ID = " . intval($maintenance_id);
    $pre_filled_maintenance = fetch_one($maintenance_query);
    
    if ($pre_filled_maintenance) {
        $pre_filled_asset_id = $pre_filled_maintenance['Asset_ID'];
        $pre_filled_type_id = $pre_filled_maintenance['Type_ID'];
        $pre_filled_priority_id = $pre_filled_maintenance['Priority_ID'];
        $pre_filled_description = $pre_filled_maintenance['Description'];
    }
} elseif ($panne_id > 0) {
    // Pre-fill from panne declaration
    $panne_query = "SELECT pd.*, a.Asset_ID FROM Panne_Declarations pd
                    INNER JOIN Assets a ON pd.Asset_ID = a.Asset_ID
                    WHERE pd.Panne_Declaration_ID = " . intval($panne_id);
    $panne_data = fetch_one($panne_query);
    
    if ($panne_data) {
        $pre_filled_asset_id = $panne_data['Asset_ID'];
        // Set type to "Panne Déclarée" (find its ID)
        $panne_type_query = "SELECT Type_ID FROM Maintenance_Type WHERE Type_Name = 'Panne Déclarée' LIMIT 1";
        $panne_type = fetch_one($panne_type_query);
        if ($panne_type) {
            $pre_filled_type_id = $panne_type['Type_ID'];
        }
        // Map panne Severity to Priority
        $severity_to_priority = array(
            'Urgente' => 1,  // High
            'Normal' => 2,   // Medium
            'Basse' => 3     // Low
        );
        $pre_filled_priority_id = $severity_to_priority[$panne_data['Severity']] ?? 2;
        $pre_filled_description = $panne_data['Description'];
    }
}

// Fetch required data
$assets_query = "SELECT * FROM Assets ORDER BY Asset_Name ASC";
$assets = fetch_all($assets_query);

$types_query = "SELECT * FROM Maintenance_Type ORDER BY Type_Name ASC";
$types = fetch_all($types_query);

$priority_query = "SELECT * FROM Maintenance_Priority ORDER BY Priority_Level ASC";
$priorities = fetch_all($priority_query);

$engineers_query = "SELECT be.Engineer_ID, e.First_Name, e.Last_Name 
                    FROM Biomedical_Engineers be
                    INNER JOIN Employees e ON be.Employee_ID = e.Employee_ID
                    WHERE e.Status_ID = 1
                    ORDER BY e.First_Name ASC";
$engineers = fetch_all($engineers_query);

// Fetch technicians
$technicians_query = "SELECT t.Technician_ID, e.First_Name, e.Last_Name
                      FROM Technicians t
                      INNER JOIN Employees e ON t.Employee_ID = e.Employee_ID
                      WHERE e.Status_ID = 1
                      ORDER BY e.First_Name ASC";
$technicians = fetch_all($technicians_query);

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $asset_id = intval($_POST['asset_id']);
    $type_id = intval($_POST['type_id']);
    $priority_id = intval($_POST['priority_id']);
    $engineer_id = intval($_POST['engineer_id']);
    $technician_id = !empty($_POST['technician_id']) ? intval($_POST['technician_id']) : null;
    $scheduled_date = !empty($_POST['scheduled_date']) ? safe_input($_POST['scheduled_date']) : date('Y-m-d H:i:s', strtotime('+1 hour'));
    $description = safe_input($_POST['description']);
    
    // Check if we're updating an existing maintenance (from panne declaration)
    if ($maintenance_id > 0) {
        // Update the existing maintenance record
        $update_query = "UPDATE Maintenance 
                        SET Asset_ID = $asset_id, 
                            Type_ID = $type_id, 
                            Priority_ID = $priority_id,
                            Scheduled_Date = '$scheduled_date',
                            Assigned_Engineer_ID = $engineer_id,
                            Assigned_Technician_ID = " . ($technician_id ? $technician_id : "NULL") . ",
                            Description = '$description'
                        WHERE Maintenance_ID = " . intval($maintenance_id);
        
        if (execute_query($update_query)) {
            $success_message = "Intervention mise à jour avec succès!";
            header("Location: corrective_maintenance.php?success=1");
            exit();
        } else {
            $error_message = "Erreur lors de la mise à jour de l'intervention.";
        }
    } elseif ($panne_id > 0) {
        // Create maintenance from panne declaration
        $reported_date = date('Y-m-d H:i:s');
        
        $insert_query = "INSERT INTO Maintenance (Asset_ID, Type_ID, Priority_ID, Reported_Date, 
                                               Scheduled_Date, Assigned_Engineer_ID, Assigned_Technician_ID, Description)
                         VALUES ($asset_id, $type_id, $priority_id, '$reported_date', 
                                '$scheduled_date', $engineer_id, " . ($technician_id ? $technician_id : "NULL") . ", '$description')";
        
        if (execute_query($insert_query)) {
            // Update equipment status to "En Maintenance" (Status_ID = 2)
            $update_equipment = "UPDATE Assets SET Status_ID = 2 WHERE Asset_ID = $asset_id";
            execute_query($update_equipment);
            
            // Mark panne as accepted
            $engineer_id_session = isset($_SESSION['employee_id']) ? intval($_SESSION['employee_id']) : 0;
            $update_panne = "UPDATE Panne_Declarations SET Status = 'Acceptée', Accepted_Date = NOW(), Accepted_By_Engineer_ID = " . $engineer_id . " WHERE Panne_Declaration_ID = " . intval($panne_id);
            execute_query($update_panne);
            
            $success_message = "Intervention créée à partir de la panne et assignée avec succès!";
            header("Location: corrective_maintenance.php?success=1");
            exit();
        } else {
            $error_message = "Erreur lors de la création de l'intervention: " . get_error();
        }
    } else {
        // Create a new maintenance record
        $reported_date = date('Y-m-d H:i:s');
        
        $insert_query = "INSERT INTO Maintenance (Asset_ID, Type_ID, Priority_ID, Reported_Date, 
                                               Scheduled_Date, Assigned_Engineer_ID, Assigned_Technician_ID, Description)
                         VALUES ($asset_id, $type_id, $priority_id, '$reported_date', 
                                '$scheduled_date', $engineer_id, " . ($technician_id ? $technician_id : "NULL") . ", '$description')";
        
        if (execute_query($insert_query)) {
            // Update equipment status to "En Maintenance" (Status_ID = 2)
            $update_equipment = "UPDATE Assets SET Status_ID = 2 WHERE Asset_ID = $asset_id";
            execute_query($update_equipment);
            
            $success_message = "Intervention corrective ajoutée avec succès!";
            header("Location: corrective_maintenance.php?success=1");
            exit();
        } else {
            $error_message = "Erreur lors de l'ajout de l'intervention: " . get_error();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Intervention - Ingénieur | CMMS Hôpital</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            padding-left: 20px;
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
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.25);
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
            font-size: 32px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .breadcrumb {
            font-size: 14px;
            color: #999;
            margin-bottom: 20px;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 900px;
        }
        
        .alert-banner {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #c62828;
        }
        
        .alert-banner strong {
            display: block;
            margin-bottom: 5px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f44336;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row.full {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="datetime-local"],
        select,
        textarea {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: border 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="datetime-local"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #f44336;
            box-shadow: 0 0 0 3px rgba(244, 67, 54, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .required {
            color: #f44336;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
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
            background: #f44336;
            color: white;
        }
        
        .btn-submit:hover {
            background: #d32f2f;
        }
        
        .btn-cancel {
            background: #ddd;
            color: #333;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-cancel:hover {
            background: #ccc;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="logo">⚙️ <span>CMMS</span></div>
            <ul class="nav-menu">
                <li><a href="dashboard.php">📊 Tableau de Bord</a></li>
                <li><a href="equipment.php">⚙️ Équipements</a></li>
                <li><a href="preventive_maintenance.php">🔧 Prév. Maintenance</a></li>
                <li><a href="corrective_maintenance.php" class="active">🚨 Corr. Maintenance</a></li>

                <li><a href="users.php">👥 Techniciens</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Signaler une Intervention Corrective</h1>
                <div class="breadcrumb">
                    <a href="corrective_maintenance.php">Maintenance Corrective</a> > Nouvelle Intervention
                </div>
            </div>
            
            <div class="alert-banner">
                <strong>⚠️ Intervention Urgente</strong>
                Utilisez ce formulaire pour signaler les pannes et problèmes imprévus qui nécessitent une intervention d'urgence.
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">✓ <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">✗ <?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <form class="form-container" method="POST">
                <!-- Section Équipement -->
                <div class="form-section">
                    <div class="form-section-title">Équipement Affecté</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="asset_id">Équipement en Panne <span class="required">*</span></label>
                            <select id="asset_id" name="asset_id" required>
                                <option value="">-- Sélectionner l'équipement --</option>
                                <?php if (!empty($assets)): ?>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?php echo $asset['Asset_ID']; ?>"
                                            <?php echo ($pre_filled_asset_id == $asset['Asset_ID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($asset['Asset_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Section Diagnostic -->
                <div class="form-section">
                    <div class="form-section-title">Classification de l'Intervention</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="type_id">Type de Défaut <span class="required">*</span></label>
                            <select id="type_id" name="type_id" required>
                                <option value="">-- Sélectionner le type --</option>
                                <?php if (!empty($types)): ?>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?php echo $type['Type_ID']; ?>"
                                            <?php echo ($pre_filled_type_id == $type['Type_ID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['Type_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="priority_id">Niveau de Priorité <span class="required">*</span></label>
                            <select id="priority_id" name="priority_id" required>
                                <option value="">-- Sélectionner la priorité --</option>
                                <?php if (!empty($priorities)): ?>
                                    <?php foreach ($priorities as $priority): ?>
                                        <option value="<?php echo $priority['Priority_ID']; ?>"
                                            <?php echo ($pre_filled_priority_id == $priority['Priority_ID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($priority['Priority_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Section Assignation -->
                <div class="form-section">
                    <div class="form-section-title">Assignation</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="engineer_id">Ingénieur Responsable <span class="required">*</span></label>
                            <select id="engineer_id" name="engineer_id" required>
                                <option value="">-- Sélectionner un ingénieur --</option>
                                <?php if (!empty($engineers)): ?>
                                    <?php foreach ($engineers as $engineer): ?>
                                        <option value="<?php echo $engineer['Engineer_ID']; ?>">
                                            <?php echo htmlspecialchars($engineer['First_Name'] . ' ' . $engineer['Last_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="technician_id">Technicien Assigné (Optionnel)</label>
                            <select id="technician_id" name="technician_id">
                                <option value="">-- Aucun technicien assigné --</option>
                                <?php if (!empty($technicians)): ?>
                                    <?php foreach ($technicians as $technician): ?>
                                        <option value="<?php echo $technician['Technician_ID']; ?>">
                                            <?php echo htmlspecialchars($technician['First_Name'] . ' ' . $technician['Last_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="scheduled_date">Date/Heure Intervention (Optionnel)</label>
                            <input type="datetime-local" id="scheduled_date" name="scheduled_date">
                            <small style="color: #999; margin-top: 5px;">Laissez vide pour intervention immédiate</small>
                        </div>
                    </div>
                </div>
                
                <!-- Section Description du Problème -->
                <div class="form-section">
                    <div class="form-section-title">Description du Problème</div>
                    
                    <div class="form-row full">
                        <div class="form-group">
                            <label for="description">Détails de la Panne <span class="required">*</span></label>
                            <textarea id="description" name="description" required placeholder="Décrivez précisément:
- Les symptômes observés
- Les messages d'erreur (s'il y en a)
- Quand le problème s'est produit
- Impact sur les opérations hospitalières
- Toute action déjà tentée"><?php echo htmlspecialchars($pre_filled_description); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Boutons d'Action -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-submit">🚨 Signaler l'Intervention</button>
                    <a href="corrective_maintenance.php" class="btn btn-cancel">← Annuler</a>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
