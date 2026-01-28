<?php
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';

// Get success/error messages from session
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);

// Fetch technicians
$tech_query = "SELECT t.*, e.First_Name, e.Last_Name, e.Email, e.Phone, e.Status_ID,
                      d.Department_Name
               FROM Technicians t
               INNER JOIN Employees e ON t.Employee_ID = e.Employee_ID
               LEFT JOIN Departments d ON t.Department_ID = d.Department_ID
               ORDER BY e.First_Name ASC";
$technicians = fetch_all($tech_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Techniciens - Ingénieur | CMMS Hôpital</title>
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
        
        .status-inactive {
            background: #ffebee;
            color: #c62828;
        }
        
        .btn-add {
            background: #667eea;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-add:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-action {
            display: inline-block;
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-edit {
            background: #667eea;
        }
        
        .btn-edit:hover {
            background: #5568d3;
        }
        
        .btn-tasks {
            background: #764ba2;
        }
        
        .btn-tasks:hover {
            background: #6a3a8e;
        }
        
        .btn-delete {
            background: #e74c3c;
        }
        
        .btn-delete:hover {
            background: #c0392b;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left-color: #2e7d32;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left-color: #c62828;
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
                <li><a href="users.php" class="active">👥 Techniciens</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Gestion des Techniciens</h1>
                <a href="add_technician.php" class="btn-add">➕ Ajouter un Technicien</a>
            </div>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">✓ <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">✗ <?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Département</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($technicians)): ?>
                            <?php foreach ($technicians as $tech): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($tech['First_Name'] . ' ' . $tech['Last_Name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($tech['Email']); ?></td>
                                    <td><?php echo htmlspecialchars($tech['Phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($tech['Department_Name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo ($tech['Status_ID'] == 1) ? 'active' : 'inactive'; ?>">
                                            <?php echo ($tech['Status_ID'] == 1) ? 'Actif' : 'Inactif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_technician.php?technician_id=<?php echo $tech['Technician_ID']; ?>" class="btn-action btn-edit">Éditer</a>
                                        <a href="tech_tasks.php?technician_id=<?php echo $tech['Technician_ID']; ?>" class="btn-action btn-tasks">Tâches</a>
                                        <button onclick="if(confirm('Êtes-vous sûr de vouloir supprimer ce technicien? Cette action ne peut pas être annulée.')) { window.location.href='delete_technician.php?technician_id=<?php echo $tech['Technician_ID']; ?>'; }" class="btn-action btn-delete">Supprimer</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-data">Aucun technicien trouvé</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
