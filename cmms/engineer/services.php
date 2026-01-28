<?php
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - Ingénieur | CMMS Hôpital</title>
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
        
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .service-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }
        
        .service-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }
        
        .service-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .service-desc {
            font-size: 13px;
            color: #999;
            line-height: 1.6;
        }
        
        .btn-action {
            margin-top: 15px;
            padding: 8px 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .btn-action:hover {
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
                <li><a href="priority.php">⚡ Priorités</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Services & Support</h1>
            </div>
            
            <div class="service-grid">
                <div class="service-card">
                    <div class="service-icon">🔧</div>
                    <div class="service-name">Maintenance de Routine</div>
                    <div class="service-desc">Services d'entretien régulier des équipements médicaux</div>
                    <button class="btn-action" onclick="manageService('maintenance')">Gérer</button>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">⚡</div>
                    <div class="service-name">Support Urgent</div>
                    <div class="service-desc">Assistance d'urgence pour les pannes critiques</div>
                    <button class="btn-action" onclick="manageService('urgent')">Gérer</button>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">🔍</div>
                    <div class="service-name">Inspections Préventives</div>
                    <div class="service-desc">Contrôles réguliers pour prévenir les défaillances</div>
                    <button class="btn-action" onclick="manageService('inspection')">Gérer</button>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">📊</div>
                    <div class="service-name">Rapports de Service</div>
                    <div class="service-desc">Documentation détaillée de tous les services fournis</div>
                    <button class="btn-action" onclick="manageService('reports')">Consulter</button>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">🎓</div>
                    <div class="service-name">Formation du Personnel</div>
                    <div class="service-desc">Formation en utilisation et entretien des équipements</div>
                    <button class="btn-action" onclick="manageService('training')">Organiser</button>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">📞</div>
                    <div class="service-name">Support Technique</div>
                    <div class="service-desc">Assistance technique et consultation d'experts</div>
                    <button class="btn-action" onclick="manageService('support')">Contacter</button>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function manageService(service) {
            alert('Gestion du service: ' + service);
        }
    </script>
</body>
</html>
