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

// Handle search/filter
$search = isset($_GET['search']) ? safe_input($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? safe_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? safe_input($_GET['date_to']) : '';
$status_filter = isset($_GET['status']) ? safe_input($_GET['status']) : '';

// Build query for maintenance history - ONLY FOR THIS TECHNICIAN
$query = "SELECT m.Maintenance_ID, a.Asset_Name, mt.Type_Name, mp.Priority_Name, 
                 m.Reported_Date, m.Scheduled_Date, m.Started_Date, m.Completed_Date,
                 m.Description, e.First_Name, e.Last_Name,
                 CASE 
                     WHEN m.Completed_Date IS NOT NULL THEN 'completed'
                     WHEN m.Started_Date IS NOT NULL THEN 'active'
                     ELSE 'pending'
                 END as status_class,
                 CASE 
                     WHEN m.Completed_Date IS NOT NULL 
                     THEN TIMESTAMPDIFF(HOUR, m.Started_Date, m.Completed_Date)
                     ELSE NULL
                 END as hours_spent
          FROM Maintenance m
          INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
          INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
          INNER JOIN Maintenance_Priority mp ON m.Priority_ID = mp.Priority_ID
          LEFT JOIN Biomedical_Engineers be ON m.Assigned_Engineer_ID = be.Engineer_ID
          LEFT JOIN Employees e ON be.Employee_ID = e.Employee_ID
          WHERE m.Completed_Date IS NOT NULL AND m.Assigned_Technician_ID = $technician_id";

if (!empty($search)) {
    $query .= " AND (a.Asset_Name LIKE '%$search%' OR mt.Type_Name LIKE '%$search%')";
}

if (!empty($date_from)) {
    $query .= " AND m.Completed_Date >= '$date_from 00:00:00'";
}

if (!empty($date_to)) {
    $query .= " AND m.Completed_Date <= '$date_to 23:59:59'";
}

if ($status_filter === 'completed') {
    $query .= " AND m.Completed_Date IS NOT NULL";
}

$query .= " ORDER BY m.Completed_Date DESC";

$history_records = fetch_all($query);

// Calculate statistics
$stats_query = "SELECT 
                    COUNT(*) as total_completed,
                    AVG(TIMESTAMPDIFF(HOUR, m.Started_Date, m.Completed_Date)) as avg_hours,
                    COUNT(DISTINCT m.Asset_ID) as unique_equipment
                FROM Maintenance m
                WHERE m.Completed_Date IS NOT NULL";

$stats = fetch_one($stats_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique - Technicien | CMMS Hôpital</title>
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
        
        .nav-menu a:hover,
        .nav-menu a.active {
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
        
        /* Content Section */
        .content-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 20px;
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
        
        /* Filter Controls */
        .filter-controls {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box,
        .filter-input,
        .filter-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .search-box {
            width: 250px;
        }
        
        .filter-input,
        .filter-select {
            width: 150px;
        }
        
        .filter-select {
            cursor: pointer;
        }
        
        .btn-search {
            padding: 10px 20px;
            background: #ff6b6b;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-search:hover {
            background: #ee5a6f;
            transform: translateY(-2px);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
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
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
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
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .priority-high {
            color: #d32f2f;
            font-weight: 600;
        }
        
        .priority-medium {
            color: #f57c00;
            font-weight: 600;
        }
        
        .priority-low {
            color: #388e3c;
            font-weight: 600;
        }
        
        .action-btn {
            padding: 6px 12px;
            margin: 0 4px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .action-btn:hover {
            background: #bbdefb;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }
        
        .duration {
            font-weight: 600;
            color: #ff6b6b;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="logo">🔧 <span>CMMS</span></div>
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
                <li><a href="history.php" class="active">📝 Historique</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Historique de Maintenance</h1>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">✓</div>
                    <div class="stat-label">Total Complétées</div>
                    <div class="stat-value"><?php echo $stats['total_completed'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⏱️</div>
                    <div class="stat-label">Durée Moyenne</div>
                    <div class="stat-value"><?php echo round($stats['avg_hours'] ?? 0, 1); ?>h</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-label">Équipements Maintenus</div>
                    <div class="stat-value"><?php echo $stats['unique_equipment'] ?? 0; ?></div>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Recherche et Filtrage</h2>
                </div>
                <form method="GET" class="filter-controls">
                    <input type="text" name="search" class="search-box" placeholder="Rechercher équipement, type..." value="<?php echo htmlspecialchars($search); ?>">
                    <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($date_from); ?>">
                    <span style="color: #999;">à</span>
                    <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($date_to); ?>">
                    <button type="submit" class="btn-search">🔍 Chercher</button>
                </form>
            </div>
            
            <!-- History Table -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Maintenance Complétée</h2>
                    <span style="font-size: 14px; color: #999;"><?php echo count($history_records); ?> résultat(s)</span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Équipement</th>
                                <th>Type</th>
                                <th>Priorité</th>
                                <th>Signalée</th>
                                <th>Complétée</th>
                                <th>Durée</th>
                                <th>Technicien</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($history_records)): ?>
                                <?php foreach ($history_records as $record): ?>
                                    <tr>
                                        <td><strong>#<?php echo $record['Maintenance_ID']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($record['Asset_Name']); ?></td>
                                        <td><?php echo htmlspecialchars($record['Type_Name']); ?></td>
                                        <td>
                                            <span class="priority-<?php echo strtolower($record['Priority_Name']); ?>">
                                                <?php echo htmlspecialchars($record['Priority_Name']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($record['Reported_Date'])); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($record['Completed_Date'])); ?></td>
                                        <td>
                                            <span class="duration">
                                                <?php echo $record['hours_spent'] ?? 'N/A'; ?> h
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars(($record['First_Name'] ?? '') . ' ' . ($record['Last_Name'] ?? '')); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="no-data">Aucun historique de maintenance trouvé</td>
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
