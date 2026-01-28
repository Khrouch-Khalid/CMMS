<?php
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';

// Fetch departments
$departments_query = "SELECT * FROM Departments ORDER BY Department_Name ASC";
$departments = fetch_all($departments_query);

// Fetch shift types
$shifts_query = "SELECT * FROM shift_type";
$shifts = fetch_all($shifts_query);

// Fetch specializations
$specializations_query = "SELECT * FROM specializations";
$specializations = fetch_all($specializations_query);

// Fetch engineers (from biomedical_engineers table)
$engineers_query = "SELECT * FROM biomedical_engineers";
$engineers = fetch_all($engineers_query);

// Handle form submission
$error = null;
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name'] ?? '');
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $national_id = mysqli_real_escape_string($conn, $_POST['national_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $department_id = intval($_POST['department_id'] ?? 0);
    $shift_type_id = intval($_POST['shift_type_id'] ?? 0);
    $specialization_id = intval($_POST['specialization_id'] ?? 0);
    $engineer_id = intval($_POST['engineer_id'] ?? 0);
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($national_id) || empty($password)) {
        $error = "Veuillez remplir tous les champs obligatoires";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères";
    } elseif ($shift_type_id <= 0) {
        $error = "Veuillez sélectionner un type de poste";
    } elseif ($specialization_id <= 0) {
        $error = "Veuillez sélectionner une spécialisation";
    } elseif ($engineer_id <= 0) {
        $error = "Veuillez sélectionner un ingénieur responsable";
    } else {
        // Check if National_ID already exists
        $check_query = "SELECT Employee_ID FROM Employees WHERE National_ID = '$national_id'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = "Cet identifiant national existe déjà dans le système. Veuillez entrer un identifiant unique.";
        } else {
            // Create Employee record
            $insert_employee = "INSERT INTO Employees (First_Name, Last_Name, Email, Phone, National_ID, Status_ID, Shift_Type_ID, Date_Of_Birth, Hire_Date, Contract_Start_Date, Contract_End_Date)
                               VALUES ('$first_name', '$last_name', '$email', '$phone', '$national_id', 1, $shift_type_id, '2000-01-01', NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR))";
            
            if (mysqli_query($conn, $insert_employee)) {
                $employee_id = mysqli_insert_id($conn);
                
                // Create Technician record
                $insert_technician = "INSERT INTO Technicians (Employee_ID, Department_ID, Specialization_ID, Managed_ByEngineer_ID)
                                     VALUES ($employee_id, $department_id, $specialization_id, $engineer_id)";
                
                if (mysqli_query($conn, $insert_technician)) {
                    // Create login account in Users table
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insert_user = "INSERT INTO Users (Email, Password, Role, Employee_ID, Is_Active)
                                   VALUES ('$email', '$hashed_password', 'technician', $employee_id, 1)";
                    
                    if (mysqli_query($conn, $insert_user)) {
                        $success = "Technicien créé avec succès! Il peut maintenant se connecter avec son email et son mot de passe.";
                        // Clear form
                        $_POST = [];
                    } else {
                        $error = "Technicien créé mais erreur lors de la création du compte de connexion: " . mysqli_error($conn);
                    }
                } else {
                    $error = "Erreur lors de la création du technicien: " . mysqli_error($conn);
                }
            } else {
                $error = "Erreur lors de la création de l'employé: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Technicien - CMMS Hôpital</title>
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
        
        .back-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 600px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-section:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-row.full {
            flex-direction: column;
        }
        
        .form-group {
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-submit {
            background: #667eea;
            color: white;
        }
        
        .btn-submit:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-cancel {
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-cancel:hover {
            background: #e0e0e0;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
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
                <li><a href="corrective_maintenance.php">🚨 Corr. Maintenance</a></li>
                <li><a href="users.php" class="active">👥 Techniciens</a></li>

                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Ajouter un Technicien</h1>
                <a href="users.php" class="back-link">← Retour aux Techniciens</a>
            </div>
            
            <div class="form-container">
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="success">✓ <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-section">
                        <div class="section-title">👤 Informations Personnelles</div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">Prénom <span class="required">*</span></label>
                                <input type="text" id="first_name" name="first_name" required placeholder="Ex: Hassan" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Nom <span class="required">*</span></label>
                                <input type="text" id="last_name" name="last_name" required placeholder="Ex: Hassani" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="national_id">Numéro d'Identité <span class="required">*</span></label>
                                <input type="text" id="national_id" name="national_id" required placeholder="Ex: AB123456" value="<?php echo htmlspecialchars($_POST['national_id'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="email">Email <span class="required">*</span></label>
                                <input type="email" id="email" name="email" required placeholder="Ex: hassan.hassani@hospital.ma" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="phone">Téléphone</label>
                                <input type="tel" id="phone" name="phone" placeholder="Ex: +212 6XX XXX XXX" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-title">🔐 Compte de Connexion</div>
                        
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="password">Mot de Passe <span class="required">*</span></label>
                                <input type="password" id="password" name="password" required placeholder="Min. 6 caractères" minlength="6">
                                <small style="color: #999; margin-top: 5px; display: block;">Le technicien utilisera cet email et ce mot de passe pour se connecter</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-title">🏢 Affectation</div>
                        
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="engineer_id">Ingénieur Responsable <span class="required">*</span></label>
                                <select id="engineer_id" name="engineer_id" required>
                                    <option value="">-- Sélectionner un ingénieur --</option>
                                    <?php if (!empty($engineers)): ?>
                                        <?php foreach ($engineers as $eng): ?>
                                            <option value="<?php echo $eng['Engineer_ID']; ?>">
                                                <?php echo htmlspecialchars($eng['Engineer_Name'] ?? $eng['Name'] ?? 'Ingénieur'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="shift_type_id">Type de Poste <span class="required">*</span></label>
                                <select id="shift_type_id" name="shift_type_id" required>
                                    <option value="">-- Sélectionner un type de poste --</option>
                                    <?php if (!empty($shifts)): ?>
                                        <?php foreach ($shifts as $shift): ?>
                                            <option value="<?php echo $shift['Shift_Type_ID']; ?>">
                                                <?php echo htmlspecialchars($shift['Shift_Type_Name'] ?? $shift['Name'] ?? $shift['Type'] ?? 'Poste'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="specialization_id">Spécialisation <span class="required">*</span></label>
                                <select id="specialization_id" name="specialization_id" required>
                                    <option value="">-- Sélectionner une spécialisation --</option>
                                    <?php if (!empty($specializations)): ?>
                                        <?php foreach ($specializations as $spec): ?>
                                            <option value="<?php echo $spec['Specialization_ID']; ?>">
                                                <?php echo htmlspecialchars($spec['Specialization_Name'] ?? $spec['Name'] ?? 'Spécialisation'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="department_id">Département</label>
                                <select id="department_id" name="department_id">
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
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-submit">➕ Ajouter Technicien</button>
                        <a href="users.php" class="btn btn-cancel">Annuler</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
