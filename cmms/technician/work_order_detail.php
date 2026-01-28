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

// Get maintenance ID from URL
$maintenance_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($maintenance_id === 0) {
    header('Location: work_orders.php');
    exit();
}

// Fetch Work Order Details
$query = "SELECT m.Maintenance_ID, a.Asset_Name, a.Asset_ID, mt.Type_Name, mp.Priority_Name, 
                 m.Reported_Date, m.Scheduled_Date, m.Started_Date, m.Completed_Date,
                 m.Description, a.Model, a.Status_ID,
                 d.Department_Name, cl.Level_Name,
                 CASE 
                     WHEN m.Completed_Date IS NOT NULL THEN 'completed'
                     WHEN m.Started_Date IS NOT NULL THEN 'active'
                     ELSE 'pending'
                 END as status_class
          FROM Maintenance m
          INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
          INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
          INNER JOIN Maintenance_Priority mp ON m.Priority_ID = mp.Priority_ID
          LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
          LEFT JOIN Criticality_Level cl ON a.Criticality_Level_ID = cl.Level_ID
          WHERE m.Maintenance_ID = $maintenance_id";

$work_order = fetch_one($query);

if (!$work_order) {
    header('Location: work_orders.php');
    exit();
}

// Handle status updates
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? safe_input($_POST['action']) : '';
    
    if ($action === 'start') {
        $update_query = "UPDATE Maintenance SET Started_Date = NOW() WHERE Maintenance_ID = $maintenance_id";
        if (execute_query($update_query)) {
            $message = 'Maintenance démarrée avec succès!';
            $message_type = 'success';
            // Refresh data
            $work_order = fetch_one($query);
        } else {
            $message = 'Erreur lors du démarrage de la maintenance';
            $message_type = 'error';
        }
    } elseif ($action === 'complete') {
        $update_query = "UPDATE Maintenance SET Completed_Date = NOW() WHERE Maintenance_ID = $maintenance_id";
        if (execute_query($update_query)) {
            // Get asset ID from maintenance record
            $asset_query = "SELECT Asset_ID FROM Maintenance WHERE Maintenance_ID = $maintenance_id";
            $asset_result = fetch_one($asset_query);
            if ($asset_result) {
                $asset_id = $asset_result['Asset_ID'];
                // Update equipment status back to Opérationnel (Status_ID = 1)
                $update_equipment = "UPDATE Assets SET Status_ID = 1 WHERE Asset_ID = $asset_id";
                execute_query($update_equipment);
            }
            $message = 'Maintenance complétée avec succès!';
            $message_type = 'success';
            // Refresh data
            $work_order = fetch_one($query);
        } else {
            $message = 'Erreur lors de la complétion de la maintenance';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Ordre de Travail - Technicien | CMMS Hôpital</title>
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
            font-size: 28px;
            color: #333;
        }
        
        .back-link {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-link:hover {
            text-decoration: underline;
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
        
        /* Card Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        
        .detail-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .detail-value.empty {
            color: #999;
            font-style: italic;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 5px;
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
        
        /* Description Section */
        .description-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 2px solid #ff6b6b;
            padding-bottom: 10px;
        }
        
        .description-text {
            font-size: 14px;
            line-height: 1.6;
            color: #666;
            white-space: pre-wrap;
        }
        
        .description-text.empty {
            color: #999;
            font-style: italic;
        }
        
        /* Action Buttons */
        .actions-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #ff6b6b;
            color: white;
        }
        
        .btn-primary:hover {
            background: #ee5a6f;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn-success:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #999;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #888;
        }
        
        .btn-disabled {
            background: #ccc;
            color: #999;
            cursor: not-allowed;
        }
        
        /* Timeline */
        .timeline {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        
        .timeline-item {
            display: flex;
            margin-bottom: 20px;
            position: relative;
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        
        .timeline-icon {
            width: 40px;
            height: 40px;
            background: #ff6b6b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .timeline-date {
            font-size: 12px;
            color: #999;
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
                <li><a href="work_orders.php" class="active">📋 Ordres de Travail</a></li>
                <li><a href="maintenance.php">🔧 Maintenance</a></li>
                <li><a href="spare_parts.php">🛠️ Pièces de Rechange</a></li>
                <li><a href="history.php">📝 Historique</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Détails Ordre de Travail #<?php echo $work_order['Maintenance_ID']; ?></h1>
                <a href="work_orders.php" class="back-link">← Retour</a>
            </div>
            
            <!-- Alert Message -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Main Details -->
            <div class="details-grid">
                <div class="detail-card">
                    <div class="detail-label">Équipement</div>
                    <div class="detail-value"><?php echo htmlspecialchars($work_order['Asset_Name']); ?></div>
                    <div style="font-size: 12px; color: #999; margin-top: 5px;">Modèle: <?php echo htmlspecialchars($work_order['Model'] ?? 'N/A'); ?></div>
                </div>
                
                <div class="detail-card">
                    <div class="detail-label">Type de Maintenance</div>
                    <div class="detail-value"><?php echo htmlspecialchars($work_order['Type_Name']); ?></div>
                </div>
                
                <div class="detail-card">
                    <div class="detail-label">Priorité</div>
                    <div class="detail-value">
                        <span class="priority-<?php echo strtolower($work_order['Priority_Name']); ?>">
                            <?php echo htmlspecialchars($work_order['Priority_Name']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="detail-card">
                    <div class="detail-label">Département</div>
                    <div class="detail-value"><?php echo htmlspecialchars($work_order['Department_Name'] ?? 'N/A'); ?></div>
                </div>
                
                <div class="detail-card">
                    <div class="detail-label">Niveau de Criticité</div>
                    <div class="detail-value"><?php echo htmlspecialchars($work_order['Level_Name'] ?? 'N/A'); ?></div>
                </div>
                
                <div class="detail-card">
                    <div class="detail-label">Statut</div>
                    <span class="status-badge status-<?php echo $work_order['status_class']; ?>">
                        <?php 
                            if ($work_order['status_class'] === 'completed') {
                                echo '✓ Complétée';
                            } elseif ($work_order['status_class'] === 'active') {
                                echo '→ En Cours';
                            } else {
                                echo '⏳ En Attente';
                            }
                        ?>
                    </span>
                </div>
            </div>
            
            <!-- Description Section -->
            <div class="description-section">
                <div class="section-title">📝 Description</div>
                <div class="description-text <?php echo empty($work_order['Description']) ? 'empty' : ''; ?>">
                    <?php echo htmlspecialchars($work_order['Description'] ?? 'Aucune description fournie'); ?>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="actions-section">
                <div class="section-title">Actions</div>
                <div class="action-buttons">
                    <?php if ($work_order['status_class'] === 'pending'): ?>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="start">
                            <button type="submit" class="btn btn-success">▶️ Démarrer la Maintenance</button>
                        </form>
                    <?php elseif ($work_order['status_class'] === 'active'): ?>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="complete">
                            <button type="submit" class="btn btn-success">✓ Compléter la Maintenance</button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-disabled">✓ Complétée</button>
                        <a href="maintenance_report.php?maintenance_id=<?php echo $work_order['Maintenance_ID']; ?>" class="btn btn-success">📝 Remplir le Rapport</a>
                    <?php endif; ?>
                    <a href="work_orders.php" class="btn btn-secondary">← Retour à la liste</a>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="timeline">
                <div class="section-title">📅 Chronologie</div>
                
                <div class="timeline-item">
                    <div class="timeline-icon">📋</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Maintenance Signalée</div>
                        <div class="timeline-date"><?php echo date('Y-m-d H:i', strtotime($work_order['Reported_Date'])); ?></div>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-icon">📅</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Date Prévue</div>
                        <div class="timeline-date">
                            <?php 
                                if ($work_order['Scheduled_Date']) {
                                    echo date('Y-m-d H:i', strtotime($work_order['Scheduled_Date']));
                                } else {
                                    echo 'Non planifiée';
                                }
                            ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($work_order['Started_Date']): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon">▶️</div>
                        <div class="timeline-content">
                            <div class="timeline-title">Maintenance Démarrée</div>
                            <div class="timeline-date"><?php echo date('Y-m-d H:i', strtotime($work_order['Started_Date'])); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($work_order['Completed_Date']): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon">✓</div>
                        <div class="timeline-content">
                            <div class="timeline-title">Maintenance Complétée</div>
                            <div class="timeline-date"><?php echo date('Y-m-d H:i', strtotime($work_order['Completed_Date'])); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
