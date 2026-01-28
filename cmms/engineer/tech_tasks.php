<?php
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';

// Get technician ID from URL
$technician_id = intval($_GET['technician_id'] ?? 0);

if ($technician_id <= 0) {
    header('Location: users.php');
    exit();
}

// Fetch technician info
$tech_query = "SELECT e.First_Name, e.Last_Name, e.Email, e.Phone
               FROM Technicians t
               INNER JOIN Employees e ON t.Employee_ID = e.Employee_ID
               WHERE t.Technician_ID = $technician_id";
$tech_result = mysqli_query($conn, $tech_query);
$technician = mysqli_fetch_assoc($tech_result);

if (!$technician) {
    header('Location: users.php');
    exit();
}

// Get the employee ID for this technician
$employee_query = "SELECT Employee_ID FROM Technicians WHERE Technician_ID = $technician_id";
$employee_result = mysqli_query($conn, $employee_query);
$employee_row = mysqli_fetch_assoc($employee_result);
$employee_id = $employee_row['Employee_ID'] ?? 0;

// Fetch maintenance tasks assigned to this specific technician
$tasks_query = "SELECT m.*, a.Asset_Name, a.Asset_ID, mt.Type_Name, mp.Priority_Level
                FROM Maintenance m
                INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
                INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
                LEFT JOIN Maintenance_Priority mp ON m.Priority_ID = mp.Priority_ID
                WHERE m.Assigned_Technician_ID = $technician_id
                ORDER BY m.Scheduled_Date DESC
                LIMIT 50";
$tasks = fetch_all($tasks_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tâches du Technicien - CMMS Hôpital</title>
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
        
        .tech-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #667eea;
        }
        
        .tech-info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .tech-info-label {
            width: 100px;
            font-weight: 600;
            color: #666;
            font-size: 13px;
        }
        
        .tech-info-value {
            flex: 1;
            color: #333;
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
        
        .priority-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-in-progress {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .no-data {
            text-align: center;
            padding: 40px 15px;
            color: #999;
        }
        
        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            flex: 1;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .stat-card-count {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-card-label {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
            text-transform: uppercase;
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
                <h1>Tâches Assignées</h1>
                <a href="users.php" class="back-link">← Retour aux Techniciens</a>
            </div>
            
            <div class="tech-info">
                <div class="tech-info-row">
                    <span class="tech-info-label">Technicien:</span>
                    <span class="tech-info-value"><strong><?php echo htmlspecialchars($technician['First_Name'] . ' ' . $technician['Last_Name']); ?></strong></span>
                </div>
                <div class="tech-info-row">
                    <span class="tech-info-label">Email:</span>
                    <span class="tech-info-value"><?php echo htmlspecialchars($technician['Email']); ?></span>
                </div>
                <div class="tech-info-row">
                    <span class="tech-info-label">Téléphone:</span>
                    <span class="tech-info-value"><?php echo htmlspecialchars($technician['Phone'] ?? 'N/A'); ?></span>
                </div>
            </div>
            
            <?php if (!empty($tasks)): ?>
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-card-count"><?php echo count($tasks); ?></div>
                        <div class="stat-card-label">Total des Tâches</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-count">
                            <?php 
                            $pending = count(array_filter($tasks, function($t) { 
                                return empty($t['Started_Date']) && empty($t['Completed_Date']);
                            }));
                            echo $pending;
                            ?>
                        </div>
                        <div class="stat-card-label">En Attente</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-count">
                            <?php 
                            $inprogress = count(array_filter($tasks, function($t) { 
                                return !empty($t['Started_Date']) && empty($t['Completed_Date']);
                            }));
                            echo $inprogress;
                            ?>
                        </div>
                        <div class="stat-card-label">En Cours</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Équipement</th>
                            <th>Type</th>
                            <th>Priorité</th>
                            <th>Statut</th>
                            <th>Date Prévue</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tasks)): ?>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($task['Asset_Name'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($task['Type_Name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="priority-badge" style="background-color: #667eea;">
                                            <?php echo htmlspecialchars($task['Priority_Level'] ?? 'Normal'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = 'En Attente';
                                        $status_class = 'status-pending';
                                        if (!empty($task['Completed_Date'])) {
                                            $status = 'Complétée';
                                            $status_class = 'status-completed';
                                        } elseif (!empty($task['Started_Date'])) {
                                            $status = 'En Cours';
                                            $status_class = 'status-in-progress';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($task['Scheduled_Date'])) {
                                            echo date('d/m/Y', strtotime($task['Scheduled_Date']));
                                        } else {
                                            echo 'Non planifiée';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($task['Description'] ?? '', 0, 50)); ?>...</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-data">Aucune tâche assignée à ce technicien</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
