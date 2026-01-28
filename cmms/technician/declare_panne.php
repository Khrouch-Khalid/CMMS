<?php
session_start();

// Include database configuration
require_once '../config/config.php';

// Check if user is logged in and has technician role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'technician') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Technicien';

// Get technician ID from database based on name in session
$name = isset($_SESSION['name']) ? $_SESSION['name'] : '';
$technician_id = 0;

if (!empty($name)) {
    // Split name into first and last name
    $name_parts = explode(' ', trim($name));
    $first_name = $name_parts[0] ?? '';
    $last_name = isset($name_parts[1]) ? implode(' ', array_slice($name_parts, 1)) : '';
    
    // Find employee by name
    $employee_query = "SELECT Employee_ID FROM Employees 
                       WHERE First_Name = '" . mysqli_real_escape_string($GLOBALS['conn'], $first_name) . "'
                       AND Last_Name = '" . mysqli_real_escape_string($GLOBALS['conn'], $last_name) . "'";
    $employee_result = fetch_one($employee_query);
    $employee_id = $employee_result['Employee_ID'] ?? 0;
    
    if ($employee_id > 0) {
        // Then find technician by employee ID
        $technician_query = "SELECT Technician_ID FROM Technicians WHERE Employee_ID = " . intval($employee_id);
        $technician_result = fetch_one($technician_query);
        $technician_id = $technician_result['Technician_ID'] ?? 0;
    }
}

// If technician not found, show error
if ($technician_id == 0) {
    echo "<div style='background: #ffebee; padding: 30px; margin: 20px; border-radius: 8px; border-left: 4px solid #f44336;'>";
    echo "<h3 style='color: #c62828; margin: 0 0 15px 0;'>❌ Erreur d'accès</h3>";
    echo "<p>Technician record not found for: <strong>" . htmlspecialchars($user_name) . "</strong></p>";
    echo "<p>Veuillez contacter l'administrateur pour vérifier votre profil.</p>";
    echo "<a href='dashboard.php' style='display: inline-block; margin-top: 15px; padding: 10px 20px; background: #f44336; color: white; text-decoration: none; border-radius: 4px;'>← Retour au Dashboard</a>";
    echo "</div>";
    die();
}

// Fetch all equipment (simplified query)
$equipment_query = "SELECT DISTINCT a.Asset_ID, a.Asset_Name, d.Department_Name 
                    FROM Assets a
                    LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
                    WHERE a.Status_ID IN (1, 2)
                    ORDER BY a.Asset_Name";
$equipment_list = fetch_all($equipment_query);

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_id = intval($_POST['asset_id'] ?? 0);
    $description = $_POST['description'] ?? '';
    $severity = $_POST['severity'] ?? 'Normal';
    
    if (!$asset_id || empty($description)) {
        $error_message = 'Veuillez remplir tous les champs requis.';
    } else {
        $reported_date = date('Y-m-d H:i:s');
        
        // Insert panne declaration into database
        $insert_query = "INSERT INTO Panne_Declarations (Technician_ID, Asset_ID, Description, Severity, Reported_Date, Status) 
                         VALUES (" . intval($technician_id) . ", " . intval($asset_id) . ", '" . mysqli_real_escape_string($GLOBALS['conn'], $description) . "', '" . mysqli_real_escape_string($GLOBALS['conn'], $severity) . "', '" . $reported_date . "', 'En attente')";
        $result = execute_query($insert_query);
        
        if ($result) {
            // Update equipment status to En Maintenance (Status_ID = 2)
            $update_equipment = "UPDATE Assets SET Status_ID = 2 WHERE Asset_ID = " . intval($asset_id);
            execute_query($update_equipment);
            
            // Redirect to technician dashboard
            header('Location: dashboard.php?message=' . urlencode('Panne déclarée avec succès. L\'ingénieur sera notifié.') . '&type=success');
            exit();
        } else {
            $error_message = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Déclarer une Panne - CMMS Hôpital</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .content {
            padding: 40px 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            color: #2e7d32;
        }

        .alert-danger {
            background: #ffebee;
            border-left: 4px solid #f44336;
            color: #c62828;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.7;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-btn:hover {
            opacity: 1;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .required {
            color: #f44336;
            margin-left: 3px;
        }

        input[type="text"],
        input[type="email"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #ff6b6b;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
            background: #fff;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            padding-right: 40px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 35px;
        }

        button {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-submit {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #757575 0%, #616161 100%) !important;
            color: white !important;
            border: 2px solid #424242 !important;
            transition: all 0.3s ease !important;
            padding: 12px 24px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            cursor: pointer !important;
            text-decoration: none !important;
            display: inline-block !important;
            text-align: center !important;
        }

        .btn-cancel:hover {
            background: linear-gradient(135deg, #616161 0%, #424242 100%) !important;
            border-color: #212121 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.25) !important;
        }

        .btn-cancel:active {
            transform: translateY(0) !important;
        }

        .no-equipment {
            text-align: center;
            padding: 30px 20px;
            color: #999;
        }

        .no-equipment-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        @media (max-width: 600px) {
            .header {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .content {
                padding: 25px 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            textarea {
                min-height: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Déclarer une Panne</h1>
            <p>Signalez un problème technique détecté sur un équipement</p>
        </div>

        <div class="content">
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                    <button class="close-btn" onclick="this.parentElement.style.display='none';">×</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                    <button class="close-btn" onclick="this.parentElement.style.display='none';">×</button>
                </div>
            <?php endif; ?>

            <?php if (empty($equipment_list)): ?>
                <div class="no-equipment">
                    <div class="no-equipment-icon">⚠️</div>
                    <h3>Aucun équipement disponible</h3>
                    <p>Il n'y a actuellement aucun équipement à signaler. Veuillez contacter l'administration.</p>
                    <a href="dashboard.php" class="btn btn-cancel" style="display: inline-block; margin-top: 20px;">Retour au Dashboard</a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="asset_id">
                            Équipement Concerné
                            <span class="required">*</span>
                        </label>
                        <select id="asset_id" name="asset_id" required>
                            <option value="">-- Sélectionner un équipement --</option>
                            <?php foreach ($equipment_list as $equipment): ?>
                                <option value="<?php echo $equipment['Asset_ID']; ?>">
                                    <?php echo htmlspecialchars($equipment['Asset_Name']); ?>
                                    <?php if (!empty($equipment['Department_Name'])): ?>
                                        (<?php echo htmlspecialchars($equipment['Department_Name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">
                            Description de la Panne
                            <span class="required">*</span>
                        </label>
                        <textarea id="description" name="description" placeholder="Décrivez précisément le problème rencontré..." required></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="severity">Niveau de Sévérité</label>
                            <select id="severity" name="severity">
                                <option value="Faible">🟢 Faible</option>
                                <option value="Normal" selected>🔵 Normal</option>
                                <option value="Élevée">🟠 Élevée</option>
                                <option value="Critique">🔴 Critique</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-submit">✓ Déclarer la Panne</button>
                        <a href="dashboard.php" class="btn btn-cancel">← Annuler</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.3s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 300);
            }, 5000);
        });

        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>