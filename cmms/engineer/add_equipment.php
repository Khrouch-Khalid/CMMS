<?php
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';

// Fetch asset types, departments, and status
$types_query = "SELECT * FROM Asset_Types ORDER BY Type_Name ASC";
$types = fetch_all($types_query);

$depts_query = "SELECT * FROM Departments ORDER BY Department_Name ASC";
$departments = fetch_all($depts_query);

$status_query = "SELECT * FROM Asset_Status ORDER BY Status_Name ASC";
$statuses = fetch_all($status_query);

$criticality_query = "SELECT * FROM Criticality_Level ORDER BY Level_Name ASC";
$criticality_levels = fetch_all($criticality_query);

$suppliers_query = "SELECT * FROM Suppliers ORDER BY Supplier_Name ASC";
$suppliers = fetch_all($suppliers_query);

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $asset_name = safe_input($_POST['asset_name']);
    $serial_number = safe_input($_POST['serial_number'] ?? '');
    $type_id = intval($_POST['type_id']);
    $department_id = intval($_POST['department_id']);
    $status_id = intval($_POST['status_id']);
    $criticality_id = intval($_POST['criticality_id']);
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
    $model = safe_input($_POST['model']);
    $installation_date = isset($_POST['installation_date']) ? safe_input($_POST['installation_date']) : '';
    $warranty_expiry = isset($_POST['warranty_expiry']) ? safe_input($_POST['warranty_expiry']) : '';
    $purchase_cost = !empty($_POST['purchase_cost']) ? floatval($_POST['purchase_cost']) : 0;
    $additional_notes = safe_input($_POST['additional_notes'] ?? '');
    
    // Validate required fields
    if (empty($supplier_id) || empty($installation_date)) {
        $error_message = "Erreur: Le fournisseur et la date d'installation sont obligatoires!";
    } else {
    
    // Insert equipment
    $insert_query = "INSERT INTO Assets (Asset_Name, Serial_Number, Asset_Type_ID, Department_ID, Status_ID, 
                                       Criticality_Level_ID, Model, Installation_Date, 
                                       Warranty_Expiry, Purchase_Cost, Supplier_ID, Additional_Notes)
                     VALUES ('$asset_name', '$serial_number', $type_id, $department_id, $status_id, 
                            $criticality_id, '$model', '$installation_date', '$warranty_expiry', 
                            $purchase_cost, $supplier_id, '$additional_notes')";
    
    if (execute_query($insert_query)) {
        $success_message = "Équipement ajouté avec succès!";
        header("Location: equipment.php?success=1");
        exit();
    } else {
        $error_message = "Erreur lors de l'ajout de l'équipement: " . get_error();
    }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter Équipement - Ingénieur | CMMS Hôpital</title>
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
        input[type="date"],
        input[type="number"],
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
        input[type="date"]:focus,
        input[type="number"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        input[type="number"] {
            font-weight: 500;
            color: #333;
        }
        
        input[type="number"]::placeholder {
            color: #999;
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
                <li><a href="equipment.php" class="active">⚙️ Équipements</a></li>
                <li><a href="preventive_maintenance.php">🔧 Prév. Maintenance</a></li>
                <li><a href="corrective_maintenance.php">🚨 Corr. Maintenance</a></li>
                <li><a href="users.php">👥 Techniciens</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Ajouter un Nouvel Équipement</h1>
                <div class="breadcrumb">
                    <a href="equipment.php">Équipements</a> > Ajouter Équipement
                </div>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">✓ <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">✗ <?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <form class="form-container" method="POST">
                <!-- Section Informations Générales -->
                <div class="form-section">
                    <div class="form-section-title">Informations Générales</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="asset_name">Nom de l'Équipement <span class="required">*</span></label>
                            <input type="text" id="asset_name" name="asset_name" required placeholder="Ex: IRM Scanner">
                        </div>
                        <div class="form-group">
                            <label for="serial_number">Numéro de Série <span class="required">*</span></label>
                            <input type="text" id="serial_number" name="serial_number" required placeholder="Ex: SN-2024-001">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="type_id">Type d'Équipement <span class="required">*</span></label>
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
                        <div class="form-group">
                            <label for="department_id">Département <span class="required">*</span></label>
                            <select id="department_id" name="department_id" required>
                                <option value="">-- Sélectionner un département --</option>
                                <?php if (!empty($departments)): ?>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['Department_ID']; ?>">
                                            <?php echo htmlspecialchars($dept['Department_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Section Fabricant & Modèle -->
                <div class="form-section">
                    <div class="form-section-title">Fabricant & Modèle</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="manufacturer">Fabricant <span class="required">*</span></label>
                            <input type="text" id="manufacturer" name="manufacturer" required placeholder="Ex: GE Healthcare">
                        </div>
                        <div class="form-group">
                            <label for="model">Modèle <span class="required">*</span></label>
                            <input type="text" id="model" name="model" required placeholder="Ex: SIGNA 3.0T">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="supplier_id">Fournisseur <span class="required">*</span></label>
                            <select id="supplier_id" name="supplier_id" required>
                                <option value="">-- Sélectionner un fournisseur --</option>
                                <?php if (!empty($suppliers)): ?>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['Supplier_ID']; ?>">
                                            <?php echo htmlspecialchars($supplier['Supplier_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Section Localisation & Dates -->
                <div class="form-section">
                    <div class="form-section-title">Localisation & Dates</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="location">Localisation</label>
                            <input type="text" id="location" name="location" placeholder="Ex: Bâtiment A, Etage 2, Salle 201">
                        </div>
                        <div class="form-group">
                            <label for="installation_date">Date d'Installation <span class="required">*</span></label>
                            <input type="date" id="installation_date" name="installation_date" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="purchase_cost">Coût d'Achat <span class="required">(MAD)</span></label>
                            <input type="number" id="purchase_cost" name="purchase_cost" min="0" placeholder="">
                        </div>
                        <div class="form-group">
                            <label for="warranty_expiry">Date d'Expiration Garantie</label>
                            <input type="date" id="warranty_expiry" name="warranty_expiry">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status_id">Statut <span class="required">*</span></label>
                            <select id="status_id" name="status_id" required>
                                <option value="">-- Sélectionner un statut --</option>
                                <?php if (!empty($statuses)): ?>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?php echo $status['Status_ID']; ?>">
                                            <?php echo htmlspecialchars($status['Status_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Section Criticité -->
                <div class="form-section">
                    <div class="form-section-title">Criticité</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="criticality_id">Niveau de Criticité <span class="required">*</span></label>
                            <select id="criticality_id" name="criticality_id" required>
                                <option value="">-- Sélectionner un niveau --</option>
                                <?php if (!empty($criticality_levels)): ?>
                                    <?php foreach ($criticality_levels as $crit): ?>
                                        <option value="<?php echo $crit['Level_ID']; ?>">
                                            <?php echo htmlspecialchars($crit['Level_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Section Notes -->
                <div class="form-section">
                    <div class="form-section-title">Observations</div>
                    
                    <div class="form-row full">
                        <div class="form-group">
                            <label for="additional_notes">Notes Additionnelles</label>
                            <textarea id="additional_notes" name="additional_notes" placeholder="Entrez toute information additionnelle sur l'équipement..."></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Boutons d'Action -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-submit">💾 Enregistrer Équipement</button>
                    <a href="equipment.php" class="btn btn-cancel">← Annuler</a>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
