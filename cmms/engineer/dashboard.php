<?php
session_start();

// Include database configuration
require_once '../config/config.php';

// Define missing functions if not already defined
if (!function_exists('get_equipment_count')) {
    function get_equipment_count() {
        $query = "SELECT COUNT(*) as count FROM Assets WHERE Status_ID != 5";
        $result = fetch_one($query);
        return $result['count'] ?? 0;
    }
}

if (!function_exists('get_maintenance_progress_count')) {
    function get_maintenance_progress_count() {
        $query = "SELECT COUNT(*) as count FROM Maintenance WHERE Started_Date IS NOT NULL AND Completed_Date IS NULL";
        $result = fetch_one($query);
        return $result['count'] ?? 0;
    }
}

if (!function_exists('get_active_technicians_count')) {
    function get_active_technicians_count() {
        $query = "SELECT COUNT(*) as count FROM Technicians t 
                  INNER JOIN Employees e ON t.Employee_ID = e.Employee_ID 
                  WHERE e.Status_ID = 1";
        $result = fetch_one($query);
        return $result['count'] ?? 0;
    }
}

if (!function_exists('get_all_equipment')) {
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
}

// Check if user is logged in and has engineer role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';

// Fetch Statistics from Database
// 1. Count Active Equipment
$equipment_count = get_equipment_count();

// 2. Count Maintenance in Progress
$maintenance_in_progress = get_maintenance_progress_count();

// 3. Count Active Technicians
$active_technicians = get_active_technicians_count();

// 4. Calculate Availability Rate
// Operational = Status_ID 1 (Opérationnel)
// Total = All equipment (Status_ID 1, 2, 3, 4, 5)
$availability_query = "SELECT 
                       COUNT(*) as total_equipment,
                       SUM(CASE WHEN Status_ID = 1 THEN 1 ELSE 0 END) as operational,
                       SUM(CASE WHEN Status_ID = 2 THEN 1 ELSE 0 END) as in_maintenance,
                       SUM(CASE WHEN Status_ID = 3 THEN 1 ELSE 0 END) as out_of_service,
                       SUM(CASE WHEN Status_ID = 4 THEN 1 ELSE 0 END) as backup,
                       SUM(CASE WHEN Status_ID = 5 THEN 1 ELSE 0 END) as retired
                       FROM Assets";
$availability_data = fetch_one($availability_query);
$total_equipment = $availability_data['total_equipment'] ?? 0;
$operational = $availability_data['operational'] ?? 0;
$in_maintenance = $availability_data['in_maintenance'] ?? 0;
$out_of_service = $availability_data['out_of_service'] ?? 0;
$availability_rate = $total_equipment > 0 ? round(($operational / $total_equipment) * 100) : 0;

// Fetch Recent Maintenance Activities
$recent_maintenance_query = "SELECT m.Maintenance_ID, a.Asset_Name, mt.Type_Name, mp.Priority_Name, 
                            a.Status_ID,
                            e.First_Name, e.Last_Name, m.Completed_Date,
                            CASE 
                                WHEN m.Completed_Date IS NOT NULL THEN 'completed'
                                WHEN m.Started_Date IS NOT NULL THEN 'active'
                                ELSE 'pending'
                            END as status_class
                            FROM Maintenance m
                            INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
                            INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
                            INNER JOIN Maintenance_Priority mp ON m.Priority_ID = mp.Priority_ID
                            LEFT JOIN Biomedical_Engineers be ON m.Assigned_Engineer_ID = be.Engineer_ID
                            LEFT JOIN Employees e ON be.Employee_ID = e.Employee_ID
                            ORDER BY m.Reported_Date DESC LIMIT 4";
$recent_activities = fetch_all($recent_maintenance_query);

// Fetch Upcoming Maintenance (Next 7 days)
$upcoming_query = "SELECT a.Asset_Name, d.Department_Name, m.Scheduled_Date, 
                   mt.Type_Name, 
                   CEIL(EXTRACT(HOUR FROM (m.Scheduled_Date - NOW()))/24) as duration_hours
                   FROM Maintenance m
                   INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
                   INNER JOIN Departments d ON a.Department_ID = d.Department_ID
                   INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
                   WHERE m.Scheduled_Date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                   AND m.Completed_Date IS NULL
                   ORDER BY m.Scheduled_Date ASC LIMIT 5";
$upcoming_maintenance = fetch_all($upcoming_query);

// Get engineer ID based on logged-in user's name
$engineer_id = 0;
$engineer_name = isset($_SESSION['name']) ? $_SESSION['name'] : '';

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

// Fetch Pending Panne Declarations for this engineer's technicians
$pending_pannes = array();
if ($engineer_id > 0) {
    $panne_query = "SELECT pd.Panne_Declaration_ID, pd.Technician_ID, pd.Asset_ID, 
                    pd.Description, pd.Severity, pd.Reported_Date,
                    a.Asset_Name, e.First_Name, e.Last_Name, t.Employee_ID,
                    d.Department_Name
                    FROM Panne_Declarations pd
                    INNER JOIN Assets a ON pd.Asset_ID = a.Asset_ID
                    INNER JOIN Technicians t ON pd.Technician_ID = t.Technician_ID
                    INNER JOIN Employees e ON t.Employee_ID = e.Employee_ID
                    INNER JOIN Departments d ON a.Department_ID = d.Department_ID
                    WHERE pd.Status = 'En attente'
                    AND t.Managed_ByEngineer_ID = " . intval($engineer_id) . "
                    ORDER BY pd.Reported_Date DESC";
    $pending_pannes = fetch_all($panne_query);
}

// Fetch all equipment for quick stats
$all_equipment = get_all_equipment();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Ingénieur | CMMS Hôpital</title>
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
        
        /* Sidebar Navigation */
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
        
        .logo span {
            margin-left: 10px;
            font-size: 18px;
        }
        
        .user-info {
            background: rgba(255, 255, 255, 0.15);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .user-info p {
            margin: 5px 0;
        }
        
        .role-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.3);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-top: 8px;
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
        
        .nav-menu a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding-left: 20px;
        }
        
        .nav-menu a i {
            margin-right: 10px;
            font-size: 16px;
        }
        
        .nav-section-title {
            padding: 15px 0 10px 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 10px;
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
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 32px;
            color: #333;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .search-box {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 250px;
            font-size: 14px;
        }
        
        .notification-icon {
            font-size: 20px;
            cursor: pointer;
        }
        
        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .welcome-card h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .welcome-card p {
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .welcome-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: white;
            color: #667eea;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid white;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }
        
        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-change {
            font-size: 12px;
            color: #999;
        }
        
        .stat-change.positive {
            color: #4caf50;
        }
        
        .stat-change.negative {
            color: #f44336;
        }
        
        /* Content Sections */
        .content-section {
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 15px;
        }
        
        .section-header h2 {
            font-size: 20px;
            color: #333;
        }
        
        .view-all {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        
        .view-all:hover {
            text-decoration: underline;
        }
        
        /* Recent Activities Table */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background-color: #f8f9fa;
            border-bottom: 2px solid #ddd;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #e65100;
        }
        
        .status-completed {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        /* Action Links */
        .action-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }
        
        .action-link:hover {
            text-decoration: underline;
        }
        
        /* Module Grid */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .module-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            text-decoration: none;
            color: #333;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
            color: #667eea;
        }
        
        .module-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .module-name {
            font-weight: 600;
            font-size: 14px;
        }
        
        /* No data message */
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 10px;
            }
            
            .logo span {
                display: none;
            }
            
            .user-info,
            .nav-section-title,
            .nav-menu a i + * {
                display: none;
            }
            
            .main-content {
                margin-left: 80px;
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-right {
                width: 100%;
                margin-top: 15px;
            }
            
            .search-box {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <img src="../assets/images/logo1.png" alt="GMAO Logo" style="width: 50px; height: auto;">
                <span>GMAO</span>
            </div>
            
            <div class="user-info">
                <p><strong><?php echo htmlspecialchars($user_name); ?></strong></p>
                <p style="font-size: 12px; opacity: 0.8;">👨‍💼 Ingénieur Biomédical</p>
                <span class="role-badge">Administrateur</span>
            </div>
            
            <nav class="nav-menu">
                <div class="nav-section-title">🏠 Navigation Principale</div>
                <li><a href="dashboard.php"><i>📊</i> Tableau de Bord</a></li>
                <li><a href="equipment.php"><i>⚙️</i> Équipements</a></li>
                <li><a href="preventive_maintenance.php"><i>🔧</i> Maint. Préventive</a></li>
                <li><a href="corrective_maintenance.php"><i>🚨</i> Maint. Corrective</a></li>
                
                <div class="nav-section-title">⚡ Gestion</div>
                <li><a href="users.php"><i>👥</i> Techniciens</a></li>
                
                <li><a href="downtime.php"><i>⏱️</i> Downtime</a></li>
                <li><a href="maintenance_reports.php"><i>📝</i> Rapports d'Intervention</a></li>
            </nav>
            
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Tableau de Bord</h1>
                <div class="header-right">
                    <input type="text" class="search-box" placeholder="Rechercher équipement...">
                    <span class="notification-icon">🔔</span>
                </div>
            </div>
            
            <!-- Welcome Card -->
            <div class="welcome-card">
                <h2>Bienvenue, <?php echo htmlspecialchars($user_name); ?>! 👋</h2>
                <p>Gérez efficacement la maintenance de tous les équipements médicaux de l'hôpital</p>
                <div class="welcome-actions">
                    <a href="equipment.php" class="btn btn-primary">Consulter Équipements</a>
                    <a href="preventive_maintenance.php" class="btn btn-secondary">Planifier Maintenance</a>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">⚙️</div>
                    <div class="stat-label">Équipements Actifs</div>
                    <div class="stat-value"><?php echo $equipment_count; ?></div>
                    <div class="stat-change positive">✓ Tous opérationnels</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🔧</div>
                    <div class="stat-label">Maintenances en Cours</div>
                    <div class="stat-value"><?php echo $maintenance_in_progress; ?></div>
                    <div class="stat-change">En cours de traitement</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-label">Techniciens Actifs</div>
                    <div class="stat-value"><?php echo $active_technicians; ?></div>
                    <div class="stat-change">Disponibles</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-label">Taux de Disponibilité</div>
                    <div class="stat-value"><?php echo $availability_rate; ?>%</div>
                    <div class="stat-change positive">
                        <?php echo $operational; ?>/<?php echo $total_equipment; ?> opérationnels
                    </div>
                </div>
            </div>
            
            <!-- Quick Access Modules -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Accès Rapide aux Modules</h2>
                </div>
                <div class="modules-grid">
                    <a href="equipment.php" class="module-card">
                        <div class="module-icon">⚙️</div>
                        <div class="module-name">Équipements</div>
                    </a>
                    <a href="preventive_maintenance.php" class="module-card">
                        <div class="module-icon">🔧</div>
                        <div class="module-name">Prév. Maint.</div>
                    </a>
                    <a href="corrective_maintenance.php" class="module-card">
                        <div class="module-icon">🚨</div>
                        <div class="module-name">Corr. Maint.</div>
                    </a>
                    <a href="users.php" class="module-card">
                        <div class="module-icon">👥</div>
                        <div class="module-name">Techniciens</div>
                    </a>
                    <a href="maintenance_reports.php" class="module-card">
                        <div class="module-icon">📝</div>
                        <div class="module-name">Rapports d'Intervention</div>
                    </a>
                </div>
            </div>
            
            <!-- Pending Panne Declarations -->
            <div class="content-section">
                <div class="section-header">
                    <h2>🔴 Déclarations de Pannes en Attente</h2>
                    <a href="corrective_maintenance.php" class="view-all">Gérer les pannes →</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Équipement</th>
                                <th>Département</th>
                                <th>Technicien</th>
                                <th>Description</th>
                                <th>Sévérité</th>
                                <th>Date de Signalement</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pending_pannes)): ?>
                                <?php foreach ($pending_pannes as $panne): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($panne['Asset_Name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($panne['Department_Name']); ?></td>
                                        <td><?php echo htmlspecialchars($panne['First_Name'] . ' ' . $panne['Last_Name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($panne['Description'], 0, 50)) . (strlen($panne['Description']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <span class="status-badge" style="
                                                background: <?php 
                                                    switch($panne['Severity']) {
                                                        case 'Critique': echo '#ffebee'; break;
                                                        case 'Élevée': echo '#fff3e0'; break;
                                                        case 'Normal': echo '#e3f2fd'; break;
                                                        case 'Faible': echo '#e8f5e9'; break;
                                                        default: echo '#f0f0f0';
                                                    }
                                                ?>;
                                                color: <?php 
                                                    switch($panne['Severity']) {
                                                        case 'Critique': echo '#c62828'; break;
                                                        case 'Élevée': echo '#e65100'; break;
                                                        case 'Normal': echo '#1976d2'; break;
                                                        case 'Faible': echo '#2e7d32'; break;
                                                        default: echo '#666';
                                                    }
                                                ?>;
                                            ">
                                                <?php echo htmlspecialchars($panne['Severity']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($panne['Reported_Date'])); ?></td>
                                        <td>
                                            <form method="POST" action="accept_panne.php" style="display: inline;">
                                                <input type="hidden" name="panne_id" value="<?php echo $panne['Panne_Declaration_ID']; ?>">
                                                <button type="submit" class="action-link" style="background: none; border: none; cursor: pointer; color: #4caf50; text-decoration: none;">✓ Accepter</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="no-data">✓ Aucune déclaration de panne en attente</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Maintenance Activities -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Activités de Maintenance Récentes</h2>
                    <a href="preventive_maintenance.php" class="view-all">Voir tout →</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Équipement</th>
                                <th>Type</th>
                                <th>Priorité</th>
                                <th>Statut</th>
                                <th>Assigné à</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_activities)): ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($activity['Asset_Name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($activity['Type_Name']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['Priority_Name']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $activity['status_class']; ?>">
                                                <?php 
                                                    if ($activity['status_class'] === 'completed') {
                                                        echo '✓ Complétée';
                                                    } elseif ($activity['status_class'] === 'active') {
                                                        echo '→ En Cours';
                                                    } else {
                                                        echo '⏳ En Attente';
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['First_Name'] ?? 'N/A') . ' ' . htmlspecialchars($activity['Last_Name'] ?? ''); ?></td>
                                        <td><?php echo $activity['Completed_Date'] ? date('Y-m-d', strtotime($activity['Completed_Date'])) : 'En cours'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data">Aucune activité de maintenance enregistrée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Upcoming Maintenance Schedule -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Maintenances à Venir (7 Prochains Jours)</h2>
                    <a href="preventive_maintenance.php" class="view-all">Voir calendrier →</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Équipement</th>
                                <th>Département</th>
                                <th>Date Prévue</th>
                                <th>Type</th>
                                <th>Durée Est.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($upcoming_maintenance)): ?>
                                <?php foreach ($upcoming_maintenance as $upcoming): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($upcoming['Asset_Name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($upcoming['Department_Name']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($upcoming['Scheduled_Date'])); ?></td>
                                        <td><?php echo htmlspecialchars($upcoming['Type_Name']); ?></td>
                                        <td><?php echo htmlspecialchars($upcoming['duration_hours'] ?? '2') . 'h'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-data">Aucune maintenance prévue pour les 7 prochains jours</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
