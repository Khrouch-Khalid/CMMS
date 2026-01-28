<?php
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';

// Fetch corrective maintenance and panne declarations
$cm_query = "SELECT m.Maintenance_ID, 'maintenance' as source_type, m.Asset_ID, a.Asset_Name, mt.Type_Name, mp.Priority_Name, 
                    e.First_Name, e.Last_Name, d.Department_Name, m.Reported_Date, 
                    m.Started_Date, m.Completed_Date, m.Description
             FROM Maintenance m
             INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
             INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
             INNER JOIN Maintenance_Priority mp ON m.Priority_ID = mp.Priority_ID
             LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
             LEFT JOIN Biomedical_Engineers be ON m.Assigned_Engineer_ID = be.Engineer_ID
             LEFT JOIN Employees e ON be.Employee_ID = e.Employee_ID
             WHERE m.Scheduled_Date IS NULL OR m.Completed_Date IS NULL
             
             UNION ALL
             
             SELECT pd.Panne_Declaration_ID, 'panne' as source_type, pd.Asset_ID, a.Asset_Name, 'Panne Déclarée' as Type_Name, pd.Severity as Priority_Name,
                    NULL as First_Name, NULL as Last_Name, d.Department_Name, pd.Reported_Date,
                    NULL as Started_Date, NULL as Completed_Date, pd.Description
             FROM Panne_Declarations pd
             INNER JOIN Assets a ON pd.Asset_ID = a.Asset_ID
             LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
             WHERE pd.Status = 'En attente'
             
             ORDER BY Reported_Date DESC";
$cm_list = fetch_all($cm_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Corrective - Ingénieur | CMMS Hôpital</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 32px;
            color: #333;
        }
        
        .btn-new {
            background: #f44336;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-new:hover {
            background: #d32f2f;
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
        
        .status-urgent {
            background: #ffebee;
            color: #c62828;
        }
        
        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #e65100;
        }
        
        .priority-high {
            background: #ffebee;
            color: #c62828;
        }
        
        .priority-medium {
            background: #fff3e0;
            color: #e65100;
        }
        
        .priority-low {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .action-link {
            color: #667eea;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
            margin-right: 10px;
        }
        
        .action-link:hover {
            text-decoration: underline;
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
                <li><a href="corrective_maintenance.php" class="active">🚨 Corr. Maintenance</a></li>
                <li><a href="users.php">👥 Techniciens</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1>Maintenance Corrective</h1>
                </div>
                <a href="add_intervention.php" style="background: #f44336; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">+ Nouvelle Intervention</a>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Équipement</th>
                            <th>Département</th>
                            <th>Date Signalée</th>
                            <th>Type</th>
                            <th>Priorité</th>
                            <th>Assigné à</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($cm_list)): ?>
                            <?php foreach ($cm_list as $item): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['Asset_Name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['Department_Name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($item['Reported_Date'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['Type_Name']); ?></td>
                                    <td>
                                        <span class="status-badge priority-<?php echo strtolower(str_replace(' ', '-', $item['Priority_Name'])); ?>">
                                            <?php echo htmlspecialchars($item['Priority_Name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(($item['First_Name'] ?? 'N/A') . ' ' . ($item['Last_Name'] ?? '')); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo ($item['Started_Date'] ?? false) ? 'active' : 'pending'; ?>">
                                            <?php echo ($item['Started_Date'] ?? false) ? 'En Cours' : 'En Attente'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($item['source_type'] === 'panne'): ?>
                                            <a href="add_intervention.php?panne_id=<?php echo $item['Maintenance_ID']; ?>&asset_id=<?php echo $item['Asset_ID']; ?>" class="action-link">➕ Assigner</a>
                                        <?php else: ?>
                                            <a href="add_intervention.php?maintenance_id=<?php echo $item['Maintenance_ID']; ?>" class="action-link">➕ Nouvelle Intervention</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">Aucune maintenance corrective en cours</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script>
        function newCM() {
            alert('Signaler une nouvelle intervention corrective');
        }
        
        function editCM(id) {
            alert('Éditer l\'intervention ID: ' + id);
        }
        
        function completeCM(id) {
            if (confirm('Marquer cette intervention comme complétée?')) {
                alert('Intervention ID ' + id + ' complétée');
            }
        }
    </script>
</body>
</html>
