<?php
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';

// Fetch downtime records
$downtime_query = "SELECT m.*, a.Asset_Name, d.Department_Name,
                          e.First_Name, e.Last_Name,
                          TIMESTAMPDIFF(HOUR, m.Started_Date, COALESCE(m.Completed_Date, NOW())) as duration_hours
                   FROM Maintenance m
                   INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
                   LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
                   LEFT JOIN Biomedical_Engineers be ON m.Assigned_Engineer_ID = be.Engineer_ID
                   LEFT JOIN Employees e ON be.Employee_ID = e.Employee_ID
                   WHERE m.Started_Date IS NOT NULL
                   ORDER BY m.Started_Date DESC
                   LIMIT 50";
$downtime_list = fetch_all($downtime_query);

// Calculate total downtime
$total_downtime = 0;
foreach ($downtime_list as $item) {
    if ($item['Completed_Date']) {
        $total_downtime += (strtotime($item['Completed_Date']) - strtotime($item['Started_Date'])) / 3600;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indisponibilité - Ingénieur | CMMS Hôpital</title>
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
            margin-bottom: 20px;
        }
        
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
        }
        
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
            background: #ffe0e0;
            color: #c62828;
        }
        
        .status-resolved {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .duration-high {
            color: #c62828;
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
            <div class="logo">⚙️ <span>CMMS</span></div>
            <ul class="nav-menu">
                <li><a href="dashboard.php">📊 Tableau de Bord</a></li>
                <li><a href="equipment.php">⚙️ Équipements</a></li>
                <li><a href="preventive_maintenance.php">🔧 Prév. Maintenance</a></li>
                <li><a href="corrective_maintenance.php">🚨 Corr. Maintenance</a></li>
                <li><a href="users.php">👥 Techniciens</a></li>
                <li><a href="downtime.php" class="active">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Analyse d'Indisponibilité</h1>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">⏱️</div>
                    <div class="stat-label">Temps d'arrêt Total</div>
                    <div class="stat-value"><?php echo number_format($total_downtime, 1); ?>h</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-label">Incidents Enregistrés</div>
                    <div class="stat-value"><?php echo count($downtime_list); ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-label">En Cours</div>
                    <div class="stat-value"><?php echo count(array_filter($downtime_list, function($item) { return !$item['Completed_Date']; })); ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">✓</div>
                    <div class="stat-label">Résolu</div>
                    <div class="stat-value"><?php echo count(array_filter($downtime_list, function($item) { return $item['Completed_Date']; })); ?></div>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Équipement</th>
                            <th>Département</th>
                            <th>Date de Début</th>
                            <th>Date de Fin</th>
                            <th>Durée (h)</th>
                            <th>Assigné à</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($downtime_list)): ?>
                            <?php foreach ($downtime_list as $item): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['Asset_Name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['Department_Name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($item['Started_Date'])); ?></td>
                                    <td><?php echo $item['Completed_Date'] ? date('Y-m-d H:i', strtotime($item['Completed_Date'])) : 'En cours'; ?></td>
                                    <td class="<?php echo $item['duration_hours'] > 8 ? 'duration-high' : ''; ?>">
                                        <?php echo number_format($item['duration_hours'], 1); ?>h
                                    </td>
                                    <td><?php echo htmlspecialchars(($item['First_Name'] ?? 'N/A') . ' ' . ($item['Last_Name'] ?? '')); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $item['Completed_Date'] ? 'resolved' : 'active'; ?>">
                                            <?php echo $item['Completed_Date'] ? '✓ Résolu' : '⚠️ En Cours'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-data">Aucun incident d'indisponibilité enregistré</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
