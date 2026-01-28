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
    <title>Rapports - Ingénieur | CMMS Hôpital</title>
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
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }
        
        .report-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }
        
        .report-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .report-desc {
            font-size: 13px;
            color: #999;
        }
        
        .btn-export {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .btn-export:hover {
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

                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Rapports & Analyses</h1>
            </div>
            
            <div class="report-grid">
                <div class="report-card" onclick="generateReport('maintenance')">
                    <div class="report-icon">📊</div>
                    <div class="report-name">Rapport Maintenance</div>
                    <div class="report-desc">Activités de maintenance complétées</div>
                    <button class="btn-export">Générer</button>
                </div>
                
                <div class="report-card" onclick="generateReport('availability')">
                    <div class="report-icon">⏱️</div>
                    <div class="report-name">Disponibilité Équipements</div>
                    <div class="report-desc">Taux de disponibilité par équipement</div>
                    <button class="btn-export">Générer</button>
                </div>
                
                <div class="report-card" onclick="generateReport('downtime')">
                    <div class="report-icon">⚠️</div>
                    <div class="report-name">Rapport Indisponibilité</div>
                    <div class="report-desc">Analyse des temps d'arrêt</div>
                    <button class="btn-export">Générer</button>
                </div>
                
                <div class="report-card" onclick="generateReport('technician')">
                    <div class="report-icon">👥</div>
                    <div class="report-name">Performance Techniciens</div>
                    <div class="report-desc">Tâches complétées par technicien</div>
                    <button class="btn-export">Générer</button>
                </div>
                
                <div class="report-card" onclick="generateReport('cost')">
                    <div class="report-icon">💰</div>
                    <div class="report-name">Coûts Maintenance</div>
                    <div class="report-desc">Analyse des coûts de maintenance</div>
                    <button class="btn-export">Générer</button>
                </div>
                
                <div class="report-card" onclick="generateReport('preventive')">
                    <div class="report-icon">🔧</div>
                    <div class="report-name">Maintenance Préventive</div>
                    <div class="report-desc">Historique de maintenance préventive</div>
                    <button class="btn-export">Générer</button>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function generateReport(type) {
            alert('Génération du rapport: ' + type);
        }
    </script>
</body>
</html>
