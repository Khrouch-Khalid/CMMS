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

// Handle search/filter
$search = isset($_GET['search']) ? safe_input($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? intval($_GET['department']) : 0;

// Build query for equipment downtime analysis
$query = "SELECT a.Asset_ID, a.Asset_Name, d.Department_Name, a.Installation_Date,
                 DATE_ADD(a.Installation_Date, INTERVAL 1 YEAR) as warranty_expiry_date,
                 COUNT(m.Maintenance_ID) as maintenance_count,
                 MAX(m.Completed_Date) as last_maintenance,
                 CASE 
                     WHEN MAX(m.Completed_Date) IS NOT NULL 
                     THEN DATEDIFF(NOW(), MAX(m.Completed_Date))
                     ELSE DATEDIFF(NOW(), a.Installation_Date)
                 END as days_since_maintenance,
                 CASE 
                     WHEN DATE_ADD(a.Installation_Date, INTERVAL 1 YEAR) >= NOW() THEN 'active'
                     ELSE 'expired'
                 END as warranty_status,
                 CASE 
                     WHEN DATE_ADD(a.Installation_Date, INTERVAL 1 YEAR) >= NOW() 
                     THEN DATEDIFF(DATE_ADD(a.Installation_Date, INTERVAL 1 YEAR), NOW())
                     ELSE DATEDIFF(NOW(), DATE_ADD(a.Installation_Date, INTERVAL 1 YEAR))
                 END as warranty_days_remaining,
                 CASE 
                     WHEN COUNT(CASE WHEN m.Completed_Date IS NOT NULL THEN 1 END) > 0 
                     THEN 'completed'
                     WHEN COUNT(CASE WHEN m.Started_Date IS NOT NULL AND m.Completed_Date IS NULL THEN 1 END) > 0 
                     THEN 'maintenance'
                     ELSE 'operational'
                 END as operational_status
          FROM Assets a
          LEFT JOIN Maintenance m ON a.Asset_ID = m.Asset_ID
          LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND a.Asset_Name LIKE '%$search%'";
}

if ($department_filter > 0) {
    $query .= " AND a.Department_ID = $department_filter";
}

$query .= " GROUP BY a.Asset_ID, a.Asset_Name, d.Department_Name, a.Status_ID, a.Installation_Date
           ORDER BY maintenance_count DESC";

$downtime_records = fetch_all($query);

// Fetch departments for filter
$departments_query = "SELECT Department_ID, Department_Name FROM Departments ORDER BY Department_Name";
$departments = fetch_all($departments_query);

// Calculate overall statistics
$stats_query = "SELECT 
                    COUNT(DISTINCT a.Asset_ID) as total_equipment,
                    COUNT(DISTINCT CASE WHEN m.Completed_Date IS NOT NULL THEN a.Asset_ID END) as maintained_equipment,
                    SUM(CASE WHEN m.Completed_Date IS NOT NULL THEN 1 ELSE 0 END) as total_maintenance_events,
                    AVG(CASE WHEN m.Completed_Date IS NOT NULL THEN TIMESTAMPDIFF(HOUR, m.Started_Date, m.Completed_Date) END) as avg_downtime_hours
                FROM Assets a
                LEFT JOIN Maintenance m ON a.Asset_ID = m.Asset_ID";

$stats = fetch_one($stats_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indisponibilité - Technicien | CMMS Hôpital</title>
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
        }
        
        .search-box,
        .filter-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .search-box {
            width: 250px;
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
        }
        
        .status-operational {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-unavailable {
            background: #ffebee;
            color: #c62828;
        }
        
        .status-maintenance {
            background: #fff3e0;
            color: #e65100;
        }
        
        .status-completed {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .warranty-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .warranty-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .warranty-expiring {
            background: #fff3e0;
            color: #e65100;
        }
        
        .warranty-expired {
            background: #ffebee;
            color: #c62828;
        }
        
        .department-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .count-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #f5f5f5;
            border-radius: 4px;
            font-weight: 600;
            color: #ff6b6b;
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
                <li><a href="history.php">📝 Historique</a></li>
                <li><a href="downtime.php" class="active">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Analyse d'Indisponibilité</h1>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-label">Total Équipements</div>
                    <div class="stat-value"><?php echo $stats['total_equipment'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🔧</div>
                    <div class="stat-label">Équipements Maintenus</div>
                    <div class="stat-value"><?php echo $stats['maintained_equipment'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⚙️</div>
                    <div class="stat-label">Maintenance Événements</div>
                    <div class="stat-value"><?php echo $stats['total_maintenance_events'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⏱️</div>
                    <div class="stat-label">Durée Moyenne (h)</div>
                    <div class="stat-value"><?php echo round($stats['avg_downtime_hours'] ?? 0, 1); ?></div>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Recherche et Filtrage</h2>
                </div>
                <form method="GET" class="filter-controls">
                    <input type="text" name="search" class="search-box" placeholder="Rechercher équipement..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="department" class="filter-select">
                        <option value="0">Tous les départements</option>
                        <?php if (!empty($departments)): ?>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['Department_ID']; ?>" <?php echo $department_filter === $dept['Department_ID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['Department_Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <button type="submit" class="btn-search">🔍 Chercher</button>
                </form>
            </div>
            
            <!-- Downtime Table -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Analyse d'Indisponibilité par Équipement</h2>
                    <span style="font-size: 14px; color: #999;"><?php echo count($downtime_records); ?> résultat(s)</span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Équipement</th>
                                <th>Département</th>
                                <th>Événements Maintenance</th>
                                <th>Dernière Maintenance</th>
                                <th>Jours Depuis Maintenance</th>
                                <th>Garantie</th>
                                <th>Statut Opérationnel</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($downtime_records)): ?>
                                <?php foreach ($downtime_records as $record): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($record['Asset_Name']); ?></strong></td>
                                        <td>
                                            <span class="department-badge">
                                                <?php echo htmlspecialchars($record['Department_Name'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="count-badge">
                                                <?php echo $record['maintenance_count']; ?> fois
                                            </span>
                                        </td>
                                        <td><?php echo $record['last_maintenance'] ? date('Y-m-d', strtotime($record['last_maintenance'])) : 'Jamais'; ?></td>
                                        <td>
                                            <span style="color: <?php echo $record['days_since_maintenance'] > 30 ? '#d32f2f' : '#388e3c'; ?>; font-weight: 600;">
                                                <?php echo $record['days_since_maintenance']; ?> jours
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($record['warranty_status'] === 'active'): ?>
                                                <span class="warranty-badge warranty-<?php echo $record['warranty_days_remaining'] <= 30 ? 'expiring' : 'active'; ?>">
                                                    <?php 
                                                        if ($record['warranty_days_remaining'] <= 30 && $record['warranty_days_remaining'] > 0) {
                                                            echo '⚠️ ' . $record['warranty_days_remaining'] . 'j restants';
                                                        } else {
                                                            echo '✓ Fabricant (' . $record['warranty_days_remaining'] . 'j)';
                                                        }
                                                    ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="warranty-badge warranty-expired">
                                                    ❌ Hôpital (depuis <?php echo abs($record['warranty_days_remaining']); ?>j)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $record['operational_status']; ?>">
                                                <?php 
                                                    if ($record['operational_status'] === 'completed') {
                                                        echo '✓ Maintenance Complétée';
                                                    } elseif ($record['operational_status'] === 'maintenance') {
                                                        echo '🔧 En Maintenance';
                                                    } else {
                                                        echo '✓ Opérationnel';
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="equipment_detail.php?id=<?php echo $record['Asset_ID']; ?>" class="action-btn">👁️ Détails</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="no-data">Aucun enregistrement d'indisponibilité trouvé</td>
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
