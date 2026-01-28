<?php
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';

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
    $scheduled_date = safe_input($_POST['scheduled_date']);
    $description = safe_input($_POST['description']);
    
    // Get current date and time
    $reported_date = date('Y-m-d H:i:s');
    
    // Insert maintenance
    $insert_query = "INSERT INTO Maintenance (Asset_ID, Type_ID, Priority_ID, Reported_Date, 
                                           Scheduled_Date, Assigned_Engineer_ID, Assigned_Technician_ID, Description)
                     VALUES ($asset_id, $type_id, $priority_id, '$reported_date', 
                            '$scheduled_date', $engineer_id, " . ($technician_id ? $technician_id : "NULL") . ", '$description')";
    
    if (execute_query($insert_query)) {
        // Update equipment status to "En Maintenance" (Status_ID = 2)
        $update_equipment = "UPDATE Assets SET Status_ID = 2 WHERE Asset_ID = $asset_id";
        execute_query($update_equipment);
        
        $success_message = "Maintenance préventive ajoutée avec succès!";
        header("Location: preventive_maintenance.php?success=1");
        exit();
    } else {
        $error_message = "Erreur lors de l'ajout de la maintenance: " . get_error();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter Maintenance - Ingénieur | CMMS Hôpital</title>
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
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
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
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
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
            background: #667eea;
            color: white;
        }
        
        .btn-submit:hover {
            background: #764ba2;
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
                <li><a href="preventive_maintenance.php" class="active">🔧 Prév. Maintenance</a></li>
                <li><a href="corrective_maintenance.php">🚨 Corr. Maintenance</a></li>

                <li><a href="users.php">👥 Techniciens</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Ajouter une Maintenance Préventive</h1>
                <div class="breadcrumb">
                    <a href="preventive_maintenance.php">Maintenance Préventive</a> > Nouvelle Maintenance
                </div>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">✓ <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">✗ <?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <form class="form-container" method="POST">
                <!-- Section Informations Principales -->
                <div class="form-section">
                    <div class="form-section-title">Informations Principales</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="asset_id">Équipement <span class="required">*</span></label>
                            <select id="asset_id" name="asset_id" required>
                                <option value="">-- Sélectionner un équipement --</option>
                                <?php if (!empty($assets)): ?>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?php echo $asset['Asset_ID']; ?>">
                                            <?php echo htmlspecialchars($asset['Asset_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="type_id">Type de Maintenance <span class="required">*</span></label>
                            <select id="type_id" name="type_id" required>
                                <option value="">-- Sélectionner un type --</option>
                                <?php if (!empty($types)): ?>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?php echo $type['Type_ID']; ?>">
                                            <?php echo htmlspecialchars($type['Type_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Section Priorité & Assignation -->
                <div class="form-section">
                    <div class="form-section-title">Priorité & Assignation</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="priority_id">Priorité <span class="required">*</span></label>
                            <select id="priority_id" name="priority_id" required>
                                <option value="">-- Sélectionner une priorité --</option>
                                <?php if (!empty($priorities)): ?>
                                    <?php foreach ($priorities as $priority): ?>
                                        <option value="<?php echo $priority['Priority_ID']; ?>">
                                            <?php echo htmlspecialchars($priority['Priority_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
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
                    </div>
                    
                    <div class="form-row">
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
                        <div class="form-group">
                            <label for="scheduled_date">Date/Heure Programmée <span class="required">*</span></label>
                            <input type="datetime-local" id="scheduled_date" name="scheduled_date" required>
                        </div>
                    </div>
                </div>
                
                <!-- Section Description -->
                <div class="form-section">
                    <div class="form-section-title">Description</div>
                    
                    <div class="form-row full">
                        <div class="form-group">
                            <label for="description">Détails de la Maintenance</label>
                            <textarea id="description" name="description" placeholder="Décrivez les tâches à effectuer, vérifications, pièces à remplacer, etc..."></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Boutons d'Action -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-submit">💾 Créer la Maintenance</button>
                    <a href="preventive_maintenance.php" class="btn btn-cancel">← Annuler</a>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
