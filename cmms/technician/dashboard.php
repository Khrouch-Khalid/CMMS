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

// Get technician ID from employee_id in session
$employee_id = isset($_SESSION['employee_id']) ? intval($_SESSION['employee_id']) : 0;
$technician_id = 0;

if ($employee_id > 0) {
    $tech_query = "SELECT Technician_ID FROM Technicians WHERE Employee_ID = $employee_id";
    $tech_result = fetch_one($tech_query);
    $technician_id = $tech_result['Technician_ID'] ?? 0;
}

// Fetch Statistics from Database - FILTERED BY ASSIGNED TECHNICIAN
// 1. Count Work Orders Assigned to This Technician
$work_orders_query = "SELECT COUNT(*) as count FROM Maintenance WHERE Assigned_Technician_ID = $technician_id";
$work_orders_count = fetch_one($work_orders_query);
$assigned_tasks = $work_orders_count['count'] ?? 0;

// 2. Count Completed Tasks This Month for This Technician
$completed_query = "SELECT COUNT(*) as count FROM Maintenance WHERE Assigned_Technician_ID = $technician_id AND Completed_Date IS NOT NULL AND MONTH(Completed_Date) = MONTH(NOW())";
$completed_data = fetch_one($completed_query);
$completed_this_month = $completed_data['count'] ?? 0;

// 3. Count In Progress Tasks for This Technician
$in_progress_query = "SELECT COUNT(*) as count FROM Maintenance WHERE Assigned_Technician_ID = $technician_id AND Started_Date IS NOT NULL AND Completed_Date IS NULL";
$in_progress_data = fetch_one($in_progress_query);
$in_progress_tasks = $in_progress_data['count'] ?? 0;

// 4. Count Pending Tasks for This Technician
$pending_query = "SELECT COUNT(*) as count FROM Maintenance WHERE Assigned_Technician_ID = $technician_id AND Started_Date IS NULL AND Completed_Date IS NULL";
$pending_data = fetch_one($pending_query);
$pending_tasks = $pending_data['count'] ?? 0;

// Fetch Recent Work Orders Assigned to This Technician
$recent_orders_query = "SELECT m.Maintenance_ID, a.Asset_Name, mt.Type_Name, mp.Priority_Name, 
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
                        WHERE m.Assigned_Technician_ID = $technician_id
                        ORDER BY m.Reported_Date DESC LIMIT 5";
$recent_work_orders = fetch_all($recent_orders_query);

// Fetch Assigned Tasks for This Week for This Technician
$week_query = "SELECT a.Asset_Name, m.Scheduled_Date, mt.Type_Name, mp.Priority_Name,
                      CASE 
                          WHEN m.Completed_Date IS NOT NULL THEN 'completed'
                          WHEN m.Started_Date IS NOT NULL THEN 'active'
                          ELSE 'pending'
                      END as status_class
               FROM Maintenance m
               INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
               INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
               INNER JOIN Maintenance_Priority mp ON m.Priority_ID = mp.Priority_ID
               WHERE m.Assigned_Technician_ID = $technician_id AND m.Scheduled_Date BETWEEN DATE_SUB(NOW(), INTERVAL 7 DAY) AND DATE_ADD(NOW(), INTERVAL 7 DAY)
               ORDER BY m.Scheduled_Date ASC LIMIT 7";
$week_tasks = fetch_all($week_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Technicien | CMMS Hôpital</title>
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
            position: relative;
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #f44336;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .notification-dropdown {
            display: none;
            position: absolute;
            top: 40px;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-dropdown.show {
            display: block;
        }
        
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background: #f9f9f9;
        }
        
        .notification-item.unread {
            background: #fff3e0;
        }
        
        .notification-item-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .notification-item-message {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .notification-item-time {
            font-size: 12px;
            color: #999;
        }
        
        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
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
            color: #ff6b6b;
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
            border-bottom: 2px solid #ff6b6b;
            padding-bottom: 15px;
        }
        
        .section-header h2 {
            font-size: 20px;
            color: #333;
        }
        
        .view-all {
            color: #ff6b6b;
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
            color: #ff6b6b;
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
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }
        
        .module-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .module-name {
            font-size: 14px;
            font-weight: 600;
        }
        
        .no-data {
            text-align: center;
            padding: 40px 15px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="logo"><img src="../assets/images/logo1.png" alt="CMMS Logo" style="width: 50px; height: auto;"> <span>CMMS</span></div>
            <div class="user-info">
                <p><strong><?php echo htmlspecialchars($user_name); ?></strong></p>
                <p>Technicien</p>
                <span class="role-badge">Actif</span>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php">📊 Tableau de Bord</a></li>
                <li><a href="work_orders.php">📋 Ordres de Travail</a></li>
                <li><a href="maintenance.php">🔧 Maintenance</a></li>
                <li><a href="spare_parts.php">🛠️ Pièces de Rechange</a></li>
                <li><a href="history.php">📝 Historique</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
                <li><a href="declare_panne.php">🚨 Déclarer une Panne</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Tableau de Bord</h1>
                <div class="header-right">
                    <input type="text" class="search-box" placeholder="Rechercher travail...">
                    <span class="notification-icon">🔔</span>
                </div>
            </div>
            
            <!-- Welcome Card -->
            <div class="welcome-card">
                <h2>Bienvenue, <?php echo htmlspecialchars($user_name); ?>! 👋</h2>
                <p>Consultez vos ordres de travail et maintenez les équipements en bon état</p>
                <div class="welcome-actions">
                    <a href="work_orders.php" class="btn btn-primary">Mes Ordres</a>
                    <a href="maintenance.php" class="btn btn-secondary">Maintenance</a>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-label">Tâches Assignées</div>
                    <div class="stat-value"><?php echo $assigned_tasks; ?></div>
                    <div class="stat-change">À traiter</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-label">En Cours</div>
                    <div class="stat-value"><?php echo $in_progress_tasks; ?></div>
                    <div class="stat-change">Actuellement</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⏰</div>
                    <div class="stat-label">En Attente</div>
                    <div class="stat-value"><?php echo $pending_tasks; ?></div>
                    <div class="stat-change">À démarrer</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">✓</div>
                    <div class="stat-label">Complétées (ce mois)</div>
                    <div class="stat-value"><?php echo $completed_this_month; ?></div>
                    <div class="stat-change positive">↑ Performance</div>
                </div>
            </div>
            
            <!-- Quick Access Modules -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Accès Rapide aux Modules</h2>
                </div>
                <div class="modules-grid">
                    <a href="work_orders.php" class="module-card">
                        <div class="module-icon">📋</div>
                        <div class="module-name">Ordres Travail</div>
                    </a>
                    <a href="maintenance.php" class="module-card">
                        <div class="module-icon">🔧</div>
                        <div class="module-name">Maintenance</div>
                    </a>
                    <a href="spare_parts.php" class="module-card">
                        <div class="module-icon">🛠️</div>
                        <div class="module-name">Pièces</div>
                    </a>
                    <a href="history.php" class="module-card">
                        <div class="module-icon">📝</div>
                        <div class="module-name">Historique</div>
                    </a>
                    <a href="downtime.php" class="module-card">
                        <div class="module-icon">⏱️</div>
                        <div class="module-name">Indisponibilité</div>
                    </a>
                </div>
            </div>
            
            <!-- Recent Work Orders -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Ordres de Travail Récents</h2>
                    <a href="work_orders.php" class="view-all">Voir tout →</a>
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
                            <?php if (!empty($recent_work_orders)): ?>
                                <?php foreach ($recent_work_orders as $order): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($order['Asset_Name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($order['Type_Name']); ?></td>
                                        <td><?php echo htmlspecialchars($order['Priority_Name']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status_class']; ?>">
                                                <?php 
                                                    if ($order['status_class'] === 'completed') {
                                                        echo '✓ Complétée';
                                                    } elseif ($order['status_class'] === 'active') {
                                                        echo '→ En Cours';
                                                    } else {
                                                        echo '⏳ En Attente';
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['First_Name'] ?? 'N/A') . ' ' . htmlspecialchars($order['Last_Name'] ?? ''); ?></td>
                                        <td><?php echo $order['Completed_Date'] ? date('Y-m-d', strtotime($order['Completed_Date'])) : 'En cours'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data">Aucun ordre de travail enregistré</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Week Schedule -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Tâches de la Semaine</h2>
                    <a href="work_orders.php" class="view-all">Voir calendrier →</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Équipement</th>
                                <th>Date Prévue</th>
                                <th>Type</th>
                                <th>Priorité</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($week_tasks)): ?>
                                <?php foreach ($week_tasks as $task): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($task['Asset_Name']); ?></strong></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($task['Scheduled_Date'])); ?></td>
                                        <td><?php echo htmlspecialchars($task['Type_Name']); ?></td>
                                        <td><?php echo htmlspecialchars($task['Priority_Name']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $task['status_class']; ?>">
                                                <?php 
                                                    if ($task['status_class'] === 'completed') {
                                                        echo '✓ Complétée';
                                                    } elseif ($task['status_class'] === 'active') {
                                                        echo '→ En Cours';
                                                    } else {
                                                        echo '⏳ En Attente';
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-data">Aucune tâche cette semaine</td>
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
