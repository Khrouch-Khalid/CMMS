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

// Get part ID from URL
$part_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($part_id === 0) {
    header('Location: spare_parts.php');
    exit();
}

// Fetch Part Details
$part_query = "SELECT sp.Part_ID, sp.Part_Name, sp.Unit_Price, sp.Current_Stock, sp.Is_Critical,
                      pc.Category_Name,
                      sup.Supplier_ID, sup.Supplier_Name, sup.Phone_Number, sup.Email, sup.Address, sup.City, sup.Country
               FROM Spare_Parts sp
               INNER JOIN Parts_Category pc ON sp.Category_ID = pc.Category_ID
               INNER JOIN Suppliers sup ON sp.Supplier_ID = sup.Supplier_ID
               WHERE sp.Part_ID = $part_id";

$part = fetch_one($part_query);

if (!$part) {
    header('Location: spare_parts.php');
    exit();
}

// Fetch equipment that uses this part
$equipment_query = "SELECT DISTINCT a.Asset_ID, a.Asset_Name, d.Department_Name
                   FROM Maintenance_Task mt
                   INNER JOIN Task_Parts_Usage tpu ON mt.Task_ID = tpu.Task_ID
                   INNER JOIN Maintenance m ON mt.Maintenance_ID = m.Maintenance_ID
                   INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
                   LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
                   WHERE tpu.Spare_Part_ID = $part_id
                   GROUP BY a.Asset_ID, a.Asset_Name, d.Department_Name
                   ORDER BY a.Asset_Name";

$equipment_list = fetch_all($equipment_query);

// Fetch recent usage history
$usage_query = "SELECT tpu.Usage_ID, tpu.Quantity, tpu.Used_Date, tpu.Notes,
                       a.Asset_Name, e.First_Name, e.Last_Name
                FROM Task_Parts_Usage tpu
                INNER JOIN Maintenance_Task mt ON tpu.Task_ID = mt.Task_ID
                INNER JOIN Maintenance m ON mt.Maintenance_ID = m.Maintenance_ID
                INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
                LEFT JOIN Biomedical_Engineers be ON tpu.Used_ByEngineer_ID = be.Engineer_ID
                LEFT JOIN Employees e ON be.Employee_ID = e.Employee_ID
                WHERE tpu.Spare_Part_ID = $part_id
                ORDER BY tpu.Used_Date DESC
                LIMIT 10";

$usage_history = fetch_all($usage_query);

// Calculate statistics
$total_used = 0;
$total_cost = 0;
foreach ($usage_history as $usage) {
    $total_used += $usage['Quantity'];
    $total_cost += $usage['Quantity'] * $part['Unit_Price'];
}

$stock_value = $part['Current_Stock'] * $part['Unit_Price'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Pièce - Technicien | CMMS Hôpital</title>
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
        
        /* Content Section */
        .content-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .section-header {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 2px solid #ff6b6b;
            padding-bottom: 10px;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ff6b6b;
        }
        
        .info-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .info-value.highlight {
            color: #ff6b6b;
        }
        
        /* Stock Bar */
        .stock-bar {
            background: #f0f0f0;
            height: 30px;
            border-radius: 15px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .stock-fill {
            height: 100%;
            background: linear-gradient(90deg, #4caf50, #8bc34a);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
            transition: width 0.3s;
        }
        
        .stock-fill.low {
            background: linear-gradient(90deg, #ff9800, #ffb74d);
        }
        
        .stock-fill.critical {
            background: linear-gradient(90deg, #f44336, #ef5350);
        }
        
        /* Supplier Card */
        .supplier-card {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .supplier-name {
            font-size: 16px;
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .supplier-info {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
        }
        
        .supplier-info strong {
            display: block;
            color: #333;
            margin-top: 8px;
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
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        .empty-message {
            text-align: center;
            padding: 30px;
            color: #999;
            font-style: italic;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
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
        
        .btn-secondary {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-secondary:hover {
            background: #bbdefb;
        }
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn-success:hover {
            background: #45a049;
        }
        
        .critical-badge {
            display: inline-block;
            padding: 6px 12px;
            background: #ffcdd2;
            color: #b71c1c;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-in-stock {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-low {
            background: #fff3e0;
            color: #e65100;
        }
        
        .status-out {
            background: #ffebee;
            color: #c62828;
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
                <li><a href="maintenance.php">🔧 Maintenance</a></li>
                <li><a href="spare_parts.php" class="active">🛠️ Pièces de Rechange</a></li>
                <li><a href="history.php">📝 Historique</a></li>
                <li><a href="downtime.php">⏱️ Indisponibilité</a></li>
            </ul>
            <a href="../auth/logout.php" class="logout-btn">🚪 Déconnexion</a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1><?php echo htmlspecialchars($part['Part_Name']); ?></h1>
                <a href="spare_parts.php" class="back-link">← Retour</a>
            </div>
            
            <!-- Part Information -->
            <div class="content-section">
                <div class="section-header">📦 Informations Pièce</div>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Catégorie</div>
                        <div class="info-value"><?php echo htmlspecialchars($part['Category_Name']); ?></div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Identifiant</div>
                        <div class="info-value">#<?php echo $part['Part_ID']; ?></div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Prix Unitaire</div>
                        <div class="info-value highlight"><?php echo number_format($part['Unit_Price'], 2, ',', ' '); ?> DT</div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Critique</div>
                        <div class="info-value">
                            <?php if ($part['Is_Critical'] === 1): ?>
                                <span class="critical-badge">🔴 Critique</span>
                            <?php else: ?>
                                <span style="color: #999;">Non</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stock Information -->
            <div class="content-section">
                <div class="section-header">📊 État du Stock</div>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Stock Actuel</div>
                        <div class="info-value" style="color: <?php echo $part['Current_Stock'] > 10 ? '#2e7d32' : ($part['Current_Stock'] > 0 ? '#e65100' : '#c62828'); ?>;">
                            <?php echo $part['Current_Stock']; ?> unité(s)
                        </div>
                        <div class="stock-bar">
                            <div class="stock-fill <?php echo $part['Current_Stock'] === 0 ? 'critical' : ($part['Current_Stock'] <= 5 ? 'low' : ''); ?>" 
                                 style="width: <?php echo min($part['Current_Stock'] * 5, 100); ?>%;">
                                <?php echo $part['Current_Stock'] > 0 ? $part['Current_Stock'] : '0'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Valeur Stock</div>
                        <div class="info-value highlight"><?php echo number_format($stock_value, 2, ',', ' '); ?> DT</div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Utilisées (10 derniers)</div>
                        <div class="info-value"><?php echo $total_used; ?> unité(s)</div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Coût Total (10 derniers)</div>
                        <div class="info-value highlight"><?php echo number_format($total_cost, 2, ',', ' '); ?> DT</div>
                    </div>
                </div>
            </div>
            
            <!-- Supplier Information -->
            <div class="content-section">
                <div class="section-header">🏢 Informations Fournisseur</div>
                <div class="supplier-card">
                    <div class="supplier-name"><?php echo htmlspecialchars($part['Supplier_Name']); ?></div>
                    <div class="supplier-info">
                        <strong>📞 Téléphone:</strong>
                        <?php echo htmlspecialchars($part['Phone_Number'] ?? 'Non disponible'); ?>
                        
                        <strong>📧 Email:</strong>
                        <?php echo htmlspecialchars($part['Email'] ?? 'Non disponible'); ?>
                        
                        <strong>📍 Adresse:</strong>
                        <?php 
                            $address_parts = array_filter([
                                $part['Address'],
                                $part['City'],
                                $part['Country']
                            ]);
                            echo htmlspecialchars(implode(', ', $address_parts)) ?: 'Non disponible';
                        ?>
                    </div>
                </div>
                <div class="action-buttons">
                    <a href="mailto:<?php echo htmlspecialchars($part['Email']); ?>" class="btn btn-secondary">📧 Contacter Fournisseur</a>
                </div>
            </div>
            
            <!-- Equipment Using This Part -->
            <div class="content-section">
                <div class="section-header">🔧 Équipements Utilisant Cette Pièce</div>
                <?php if (!empty($equipment_list)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Équipement</th>
                                    <th>Département</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipment_list as $equipment): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($equipment['Asset_Name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($equipment['Department_Name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a href="../engineer/equipment.php?search=<?php echo urlencode($equipment['Asset_Name']); ?>" style="color: #1976d2; text-decoration: none; font-weight: 600;">
                                                👁️ Voir
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-message">Aucun équipement enregistré utilisant cette pièce</div>
                <?php endif; ?>
            </div>
            
            <!-- Usage History -->
            <div class="content-section">
                <div class="section-header">📝 Historique d'Utilisation</div>
                <?php if (!empty($usage_history)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Équipement</th>
                                    <th>Quantité</th>
                                    <th>Technicien</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usage_history as $usage): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i', strtotime($usage['Used_Date'])); ?></td>
                                        <td><?php echo htmlspecialchars($usage['Asset_Name']); ?></td>
                                        <td><strong><?php echo $usage['Quantity']; ?></strong></td>
                                        <td><?php echo htmlspecialchars(($usage['First_Name'] ?? '') . ' ' . ($usage['Last_Name'] ?? 'N/A')); ?></td>
                                        <td><?php echo htmlspecialchars($usage['Notes'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-message">Aucun historique d'utilisation disponible</div>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons -->
            <div class="content-section">
                <div class="section-header">⚙️ Actions</div>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="alert('Fonctionnalité à venir: Demander le réapprovisionnement')">📦 Demander Réappro</button>
                    <button class="btn btn-success" onclick="alert('Fonctionnalité à venir: Réserver cette pièce')">🔒 Réserver</button>
                    <button class="btn btn-secondary" onclick="alert('Fonctionnalité à venir: Imprimer les détails')">🖨️ Imprimer</button>
                    <a href="spare_parts.php" class="btn btn-secondary">← Retour à la Liste</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
