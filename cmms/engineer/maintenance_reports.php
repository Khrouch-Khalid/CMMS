<?php
session_start();

require_once '../config/config.php';

// Check if user is logged in and has engineer role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

// Fetch all maintenance reports
$reports_query = "SELECT mr.*, m.Maintenance_ID, a.Asset_Name, mt.Type_Name, 
                          e.First_Name AS Tech_First_Name, e.Last_Name AS Tech_Last_Name,
                          eng_e.First_Name, eng_e.Last_Name
                   FROM Maintenance_Reports mr
                   INNER JOIN Maintenance m ON mr.Maintenance_ID = m.Maintenance_ID
                   INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
                   INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
                   LEFT JOIN Employees e ON mr.Technician_ID = e.Employee_ID
                   LEFT JOIN Biomedical_Engineers be ON m.Assigned_Engineer_ID = be.Engineer_ID
                   LEFT JOIN Employees eng_e ON be.Employee_ID = eng_e.Employee_ID
                   ORDER BY mr.Report_Date DESC";

$reports = fetch_all($reports_query);

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $report_id = intval($_GET['id']);
    $report_query = "SELECT mr.*, m.Maintenance_ID, a.Asset_Name, a.Model, a.Asset_ID, mt.Type_Name,
                            d.Department_Name, e.First_Name AS Tech_First_Name, e.Last_Name AS Tech_Last_Name,
                            eng_e.First_Name, eng_e.Last_Name
                     FROM Maintenance_Reports mr
                     INNER JOIN Maintenance m ON mr.Maintenance_ID = m.Maintenance_ID
                     INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
                     INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
                     LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
                     LEFT JOIN Employees e ON mr.Technician_ID = e.Employee_ID
                     LEFT JOIN Biomedical_Engineers be ON m.Assigned_Engineer_ID = be.Engineer_ID
                     LEFT JOIN Employees eng_e ON be.Employee_ID = eng_e.Employee_ID
                     WHERE mr.Report_ID = $report_id";
    
    $single_report = fetch_one($report_query);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports de Maintenance | CMMS Hôpital</title>
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
            text-decoration: none;
            display: block;
            text-align: center;
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
            font-size: 28px;
            color: #333;
        }
        
        .reports-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .reports-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .reports-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .reports-table th,
        .reports-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .reports-table tbody tr:hover {
            background: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #e65100;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            gap: 5px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #764ba2;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 15px;
        }
        
        .modal-header h2 {
            font-size: 22px;
            color: #333;
        }
        
        .close-modal {
            font-size: 28px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            background: none;
            border: none;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .report-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 15px;
            color: #333;
        }
        
        .section {
            margin-top: 20px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .section-title {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 14px;
        }
        
        .no-reports {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">⚙️ <span>CMMS</span></div>
            <ul class="nav-menu">
                <li><a href="dashboard.php">📊 Tableau de Bord</a></li>
                <li><a href="equipment.php">🏥 Équipements</a></li>
                <li><a href="add_equipment.php">➕ Ajouter Équipement</a></li>
                <li><a href="add_maintenance.php">➕ Ajouter Maintenance</a></li>
                <li><a href="preventive_maintenance.php">🛡️ Maintenance Préventive</a></li>
                <li><a href="corrective_maintenance.php">🔴 Maintenance Corrective</a></li>
                <li><a href="users.php">👥 Techniciens</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>📊 Rapports de Maintenance</h1>
            </div>
            
            <?php if (empty($reports)): ?>
                <div class="reports-container">
                    <div class="no-reports">
                        <p style="font-size: 48px; margin-bottom: 10px;">📭</p>
                        <p>Aucun rapport de maintenance disponible pour le moment.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="reports-container">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Équipement</th>
                                <th>Type</th>
                                <th>Technicien</th>
                                <th>Date du Rapport</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($report['Asset_Name']); ?></td>
                                    <td><?php echo htmlspecialchars($report['Type_Name']); ?></td>
                                    <td><?php echo htmlspecialchars($report['Tech_First_Name'] . ' ' . $report['Tech_Last_Name']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($report['Report_Date'])); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo ($report['Work_Completed'] ? 'status-completed' : 'status-pending'); ?>">
                                            <?php echo $report['Work_Completed'] ? '✓ Complété' : '⏳ En Attente'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary" onclick="openReport(<?php echo $report['Report_ID']; ?>)">👁️ Voir</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Report Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📋 Détails du Rapport</h2>
                <button class="close-modal" onclick="closeReport()">&times;</button>
            </div>
            
            <div id="reportContent">
                <!-- Report details will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        function openReport(reportId) {
            // Fetch report details via AJAX and display in modal
            fetch(`get_report.php?id=${reportId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('reportContent').innerHTML = html;
                    document.getElementById('reportModal').classList.add('show');
                })
                .catch(error => console.error('Error:', error));
        }
        
        function closeReport() {
            document.getElementById('reportModal').classList.remove('show');
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('reportModal');
            if (event.target === modal) {
                modal.classList.remove('show');
            }
        }
    </script>
</body>
</html>
