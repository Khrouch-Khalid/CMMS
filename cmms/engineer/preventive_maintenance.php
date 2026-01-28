<?php
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';

// Handle AJAX GET requests for details
if (isset($_GET['get_details']) && $_GET['get_details'] == 1) {
    header('Content-Type: application/json');
    $maintenance_id = intval($_GET['id']);
    $query = "SELECT Scheduled_Date FROM Maintenance WHERE Maintenance_ID = $maintenance_id";
    $result = fetch_one($query);
    
    if ($result) {
        // Convert to datetime-local format (YYYY-MM-DDTHH:mm)
        $dateTime = date('Y-m-d\TH:i', strtotime($result['Scheduled_Date']));
        echo json_encode(['scheduled_date' => $dateTime]);
    } else {
        echo json_encode(['scheduled_date' => null]);
    }
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    global $conn;
    
    if ($_POST['action'] === 'delete') {
        $maintenance_id = intval($_POST['maintenance_id']);
        
        // First, check if there are any related records in Maintenance_Reports
        $check_query = "SELECT COUNT(*) as count FROM Maintenance_Reports WHERE Maintenance_ID = $maintenance_id";
        $check_result = fetch_one($check_query);
        
        if ($check_result && $check_result['count'] > 0) {
            // Delete related maintenance reports first
            $delete_reports = "DELETE FROM Maintenance_Reports WHERE Maintenance_ID = $maintenance_id";
            if (!$conn->query($delete_reports)) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression des rapports associés: ' . $conn->error]);
                exit();
            }
        }
        
        // Check if there are any related records in Maintenance_Task
        $check_task = "SELECT COUNT(*) as count FROM Maintenance_Task WHERE Maintenance_ID = $maintenance_id";
        $task_result = fetch_one($check_task);
        
        if ($task_result && $task_result['count'] > 0) {
            // Delete related maintenance tasks first
            $delete_tasks = "DELETE FROM Maintenance_Task WHERE Maintenance_ID = $maintenance_id";
            if (!$conn->query($delete_tasks)) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression des tâches associées: ' . $conn->error]);
                exit();
            }
        }
        
        // Now delete the maintenance record
        $delete_query = "DELETE FROM Maintenance WHERE Maintenance_ID = $maintenance_id";
        
        if ($conn->query($delete_query) === TRUE) {
            echo json_encode(['success' => true, 'message' => 'Maintenance supprimée avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $conn->error]);
        }
        exit();
    } elseif ($_POST['action'] === 'update') {
        $maintenance_id = intval($_POST['maintenance_id']);
        $scheduled_date = safe_input($_POST['scheduled_date']);
        
        $update_query = "UPDATE Maintenance SET Scheduled_Date = '$scheduled_date' WHERE Maintenance_ID = $maintenance_id";
        
        if (execute_query($update_query)) {
            echo json_encode(['success' => true, 'message' => 'Date mise à jour avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
        }
        exit();
    }
}

// Fetch preventive maintenance
$pm_query = "SELECT m.*, a.Asset_Name, mt.Type_Name, mp.Priority_Name, 
                    e.First_Name, e.Last_Name, d.Department_Name
             FROM Maintenance m
             INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
             INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
             INNER JOIN Maintenance_Priority mp ON m.Priority_ID = mp.Priority_ID
             LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
             LEFT JOIN Biomedical_Engineers be ON m.Assigned_Engineer_ID = be.Engineer_ID
             LEFT JOIN Employees e ON be.Employee_ID = e.Employee_ID
             WHERE m.Scheduled_Date IS NOT NULL
             ORDER BY m.Scheduled_Date ASC";
$pm_list = fetch_all($pm_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Préventive - Ingénieur | CMMS Hôpital</title>
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
            background: #667eea;
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
            background: #764ba2;
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
        
        .status-pending {
            background: #fff3e0;
            color: #e65100;
        }
        
        .status-scheduled {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
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

        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .modal-content h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            margin-right: 10px;
            margin-top: 10px;
        }

        .btn-success {
            background: #4caf50;
            color: white;
        }

        .btn-success:hover {
            background: #45a049;
        }

        .btn-secondary {
            background: #999;
            color: white;
        }

        .btn-secondary:hover {
            background: #888;
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
                <li><a href="preventive_maintenance.php" class="active">🔧 Prév. Maintenance</a></li>
                <li><a href="corrective_maintenance.php">🚨 Corr. Maintenance</a></li>
                <li><a href="users.php">👥 Techniciens</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1>Maintenance Préventive</h1>
                </div>
                <a href="add_maintenance.php" style="background: #667eea; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">+ Nouvelle Maintenance</a>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Équipement</th>
                            <th>Département</th>
                            <th>Date Prévue</th>
                            <th>Type</th>
                            <th>Priorité</th>
                            <th>Assigné à</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($pm_list)): ?>
                            <?php foreach ($pm_list as $item): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['Asset_Name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['Department_Name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($item['Scheduled_Date'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['Type_Name']); ?></td>
                                    <td>
                                        <span class="status-badge priority-<?php echo strtolower($item['Priority_Name']); ?>">
                                            <?php echo htmlspecialchars($item['Priority_Name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(($item['First_Name'] ?? 'N/A') . ' ' . ($item['Last_Name'] ?? '')); ?></td>
                                    <td>
                                        <span class="status-badge status-scheduled">Prévue</span>
                                    </td>
                                    <td>
                                        <a href="javascript:void(0)" class="action-link" onclick="editPM(<?php echo $item['Maintenance_ID']; ?>)">Éditer</a>
                                        <a href="javascript:void(0)" class="action-link" onclick="deletePM(<?php echo $item['Maintenance_ID']; ?>)">Supprimer</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">Aucune maintenance préventive prévue</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Modifier la Date de Maintenance</h2>
            <form id="editForm">
                <div class="form-group">
                    <label for="scheduledDate">Date et Heure Prévues</label>
                    <input type="datetime-local" id="scheduledDate" required>
                </div>
                <button type="submit" class="btn btn-success">💾 Enregistrer</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Annuler</button>
            </form>
        </div>
    </div>
    
    <script>
        let currentMaintenanceId = null;

        function editPM(id) {
            currentMaintenanceId = id;
            
            // Fetch current maintenance details
            fetch('preventive_maintenance.php?get_details=1&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.scheduled_date) {
                        document.getElementById('scheduledDate').value = data.scheduled_date;
                        document.getElementById('editModal').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors de la récupération des détails');
                });
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            currentMaintenanceId = null;
        }

        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const scheduledDate = document.getElementById('scheduledDate').value;
            
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('maintenance_id', currentMaintenanceId);
            formData.append('scheduled_date', scheduledDate);
            
            fetch('preventive_maintenance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la mise à jour');
            });
        });
        
        function deletePM(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette maintenance?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('maintenance_id', id);
                
                fetch('preventive_maintenance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur HTTP: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur complète:', error);
                    alert('Erreur lors de la suppression: ' + error.message);
                });
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
