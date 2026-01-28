<?php
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';

// Fetch maintenance priorities
$priority_query = "SELECT * FROM Maintenance_Priority ORDER BY Priority_Name ASC";
$priorities = fetch_all($priority_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Priorités - Ingénieur | CMMS Hôpital</title>
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
        
        .priority-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .priority-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border-left: 5px solid #667eea;
        }
        
        .priority-card.high {
            border-left-color: #f44336;
        }
        
        .priority-card.medium {
            border-left-color: #ff9800;
        }
        
        .priority-card.low {
            border-left-color: #4caf50;
        }
        
        .priority-level {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .priority-desc {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .priority-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        .info-item {
            background: #f5f7fa;
            padding: 10px;
            border-radius: 5px;
        }
        
        .info-label {
            color: #999;
            font-weight: 600;
        }
        
        .info-value {
            color: #333;
            font-weight: bold;
        }
        
        .action-links {
            display: flex;
            gap: 10px;
        }
        
        .action-link {
            flex: 1;
            padding: 8px 12px;
            text-align: center;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            border: none;
            transition: all 0.3s;
        }
        
        .action-link:hover {
            background: #764ba2;
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
                <li><a href="priority.php" class="active">⚡ Priorités</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Niveaux de Priorité</h1>
            </div>
            
            <div class="priority-grid">
                <?php if (!empty($priorities)): ?>
                    <?php foreach ($priorities as $priority): ?>
                        <div class="priority-card <?php echo strtolower($priority['Priority_Name']); ?>">
                            <div class="priority-level">⚡ <?php echo htmlspecialchars($priority['Priority_Name']); ?></div>
                            <div class="priority-desc">Niveau de priorité pour les tâches de maintenance</div>
                            <div class="priority-info">
                                <div class="info-item">
                                    <div class="info-label">ID Priorité</div>
                                    <div class="info-value"><?php echo $priority['Priority_ID']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Statut</div>
                                    <div class="info-value">Actif</div>
                                </div>
                            </div>
                            <div class="action-links">
                                <button class="action-link" onclick="editPriority(<?php echo $priority['Priority_ID']; ?>)">Éditer</button>
                                <button class="action-link" onclick="viewPriority(<?php echo $priority['Priority_ID']; ?>)">Voir</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #999;">
                        Aucun niveau de priorité trouvé
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        function editPriority(id) {
            alert('Éditer la priorité ID: ' + id);
        }
        
        function viewPriority(id) {
            alert('Détails de la priorité ID: ' + id);
        }
    </script>
</body>
</html>
