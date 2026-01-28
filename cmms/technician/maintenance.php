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

// Handle messages
$message = isset($_GET['message']) ? urldecode($_GET['message']) : '';
$message_type = isset($_GET['type']) ? $_GET['type'] : '';

// Handle search/filter
$search = isset($_GET['search']) ? safe_input($_GET['search']) : '';
$maintenance_type = isset($_GET['type']) ? safe_input($_GET['type']) : '';

// Build query for maintenance - ONLY FOR THIS TECHNICIAN
$query = "SELECT m.Maintenance_ID, a.Asset_Name, mt.Type_Name, mp.Priority_Name, 
                 m.Reported_Date, m.Scheduled_Date, m.Started_Date, m.Completed_Date,
                 m.Description,
                 CASE 
                     WHEN m.Completed_Date IS NOT NULL THEN 'completed'
                     WHEN m.Started_Date IS NOT NULL THEN 'active'
                     ELSE 'pending'
                 END as status_class,
                 CASE 
                     WHEN mt.Is_Planned = 1 THEN 'Préventive'
                     ELSE 'Corrective'
                 END as maintenance_type
          FROM Maintenance m
          INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
          INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
          INNER JOIN Maintenance_Priority mp ON m.Priority_ID = mp.Priority_ID
          WHERE m.Assigned_Technician_ID = $technician_id";

if (!empty($search)) {
    $query .= " AND (a.Asset_Name LIKE '%$search%' OR mt.Type_Name LIKE '%$search%')";
}

if (!empty($maintenance_type)) {
    if ($maintenance_type === 'preventive') {
        $query .= " AND mt.Is_Planned = 1";
    } else {
        $query .= " AND mt.Is_Planned = 0";
    }
}

$query .= " ORDER BY m.Scheduled_Date ASC";

$maintenance_records = fetch_all($query);

// Fetch maintenance types for filter
$types_query = "SELECT DISTINCT Type_ID, Type_Name, Is_Planned FROM Maintenance_Type ORDER BY Type_Name";
$types = fetch_all($types_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Technicien | CMMS Hôpital</title>
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
        
        /* Alert Message */
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
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
        
        .type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .type-preventive {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .type-corrective {
            background: #fce4ec;
            color: #c2185b;
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
        }
        
        .action-view {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .action-view:hover {
            background: #bbdefb;
        }
        
        .action-start {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .action-start:hover {
            background: #c8e6c9;
        }
        
        .action-complete {
            background: #f3e5f5;
            color: #6a1b9a;
        }
        
        .action-complete:hover {
            background: #e1bee7;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-mini {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ff6b6b;
        }
        
        .stat-mini-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .stat-mini-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
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
                <li><a href="maintenance.php" class="active">🔧 Maintenance</a></li>
                <li><a href="spare_parts.php">🛠️ Pièces de Rechange</a></li>
                <li><a href="history.php">📝 Historique</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Maintenance</h1>
            </div>
            
            <!-- Alert Message -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-mini">
                    <div class="stat-mini-label">Préventive Planifiée</div>
                    <div class="stat-mini-value" id="preventive-count">0</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-label">Corrective Urgent</div>
                    <div class="stat-mini-value" id="corrective-count">0</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-label">En Cours</div>
                    <div class="stat-mini-value" id="active-count">0</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-label">Total</div>
                    <div class="stat-mini-value" id="total-count">0</div>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Recherche et Filtrage</h2>
                </div>
                <form method="GET" class="filter-controls">
                    <input type="text" name="search" class="search-box" placeholder="Rechercher équipement, type..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="type" class="filter-select">
                        <option value="">Tous les types</option>
                        <option value="preventive" <?php echo $maintenance_type === 'preventive' ? 'selected' : ''; ?>>Préventive</option>
                        <option value="corrective" <?php echo $maintenance_type === 'corrective' ? 'selected' : ''; ?>>Corrective</option>
                    </select>
                    <button type="submit" class="btn-search">🔍 Chercher</button>
                </form>
            </div>
            
            <!-- Maintenance Table -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Liste de Maintenance</h2>
                    <span style="font-size: 14px; color: #999;"><?php echo count($maintenance_records); ?> résultat(s)</span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Équipement</th>
                                <th>Type</th>
                                <th>Catégorie</th>
                                <th>Priorité</th>
                                <th>Date Prévue</th>
                                <th>Statut</th>
                                
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($maintenance_records)): ?>
                                <?php foreach ($maintenance_records as $record): ?>
                                    <tr>
                                        <td><strong>#<?php echo $record['Maintenance_ID']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($record['Asset_Name']); ?></td>
                                        <td><?php echo htmlspecialchars($record['Type_Name']); ?></td>
                                        <td>
                                            <span class="type-badge type-<?php echo strtolower(str_replace(' ', '-', $record['maintenance_type'])); ?>">
                                                <?php echo htmlspecialchars($record['maintenance_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="priority-<?php echo strtolower($record['Priority_Name']); ?>">
                                                <?php echo htmlspecialchars($record['Priority_Name']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $record['Scheduled_Date'] ? date('Y-m-d H:i', strtotime($record['Scheduled_Date'])) : 'N/A'; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $record['status_class']; ?>">
                                                <?php 
                                                    if ($record['status_class'] === 'completed') {
                                                        echo '✓ Complétée';
                                                    } elseif ($record['status_class'] === 'active') {
                                                        echo '→ En Cours';
                                                    } else {
                                                        echo '⏳ En Attente';
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($record['status_class'] === 'pending'): ?>
                                                <a href="update_maintenance.php?id=<?php echo $record['Maintenance_ID']; ?>&action=start" class="action-btn action-start">▶️ Démarrer</a>
                                            <?php elseif ($record['status_class'] === 'active'): ?>
                                                <a href="update_maintenance.php?id=<?php echo $record['Maintenance_ID']; ?>&action=complete" class="action-btn action-complete">✓ Compléter</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="no-data">Aucun enregistrement de maintenance trouvé</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Calculate statistics
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            let preventive = 0, corrective = 0, active = 0;
            
            rows.forEach(row => {
                const text = row.textContent;
                if (text.includes('Préventive')) preventive++;
                if (text.includes('Corrective')) corrective++;
                if (text.includes('En Cours')) active++;
            });
            
            document.getElementById('preventive-count').textContent = preventive;
            document.getElementById('corrective-count').textContent = corrective;
            document.getElementById('active-count').textContent = active;
            document.getElementById('total-count').textContent = rows.length > 0 ? rows.length : 0;
        });
    </script>
</body>
</html>
