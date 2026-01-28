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

// Handle search/filter
$search = isset($_GET['search']) ? safe_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$stock_filter = isset($_GET['stock']) ? safe_input($_GET['stock']) : '';

// Build query for spare parts
$query = "SELECT sp.Part_ID, sp.Part_Name, pc.Category_Name, sp.Unit_Price, 
                 sp.Current_Stock, sp.Is_Critical,
                 sup.Supplier_Name, sup.Phone_Number, sup.Email
          FROM Spare_Parts sp
          INNER JOIN Parts_Category pc ON sp.Category_ID = pc.Category_ID
          INNER JOIN Suppliers sup ON sp.Supplier_ID = sup.Supplier_ID
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (sp.Part_Name LIKE '%$search%' OR pc.Category_Name LIKE '%$search%')";
}

if ($category_filter > 0) {
    $query .= " AND sp.Category_ID = $category_filter";
}

if ($stock_filter === 'low') {
    $query .= " AND sp.Current_Stock <= 5";
} elseif ($stock_filter === 'out') {
    $query .= " AND sp.Current_Stock = 0";
}

$query .= " ORDER BY sp.Part_Name ASC";

$spare_parts = fetch_all($query);

// Fetch categories for filter
$categories_query = "SELECT Category_ID, Category_Name FROM Parts_Category ORDER BY Category_Name";
$categories = fetch_all($categories_query);

// Calculate statistics
$stats_query = "SELECT 
                    COUNT(*) as total_parts,
                    SUM(CASE WHEN Current_Stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
                    SUM(CASE WHEN Current_Stock <= 5 THEN 1 ELSE 0 END) as low_stock,
                    SUM(CASE WHEN Is_Critical = 1 THEN 1 ELSE 0 END) as critical_parts
                FROM Spare_Parts";
$stats = fetch_one($stats_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pièces de Rechange - Technicien | CMMS Hôpital</title>
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }
        
        .stat-icon {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
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
        
        .stock-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .stock-in {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .stock-low {
            background: #fff3e0;
            color: #e65100;
        }
        
        .stock-out {
            background: #ffebee;
            color: #c62828;
        }
        
        .critical-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #ffcdd2;
            color: #b71c1c;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .category-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1565c0;
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
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }
        
        .price {
            color: #ff6b6b;
            font-weight: 600;
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
                <h1>Pièces de Rechange</h1>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-label">Total Pièces</div>
                    <div class="stat-value"><?php echo $stats['total_parts'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-label">Stock Critique</div>
                    <div class="stat-value" style="color: #d32f2f;"><?php echo $stats['critical_parts'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📉</div>
                    <div class="stat-label">Stock Faible</div>
                    <div class="stat-value" style="color: #f57c00;"><?php echo $stats['low_stock'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">❌</div>
                    <div class="stat-label">Rupture Stock</div>
                    <div class="stat-value" style="color: #c62828;"><?php echo $stats['out_of_stock'] ?? 0; ?></div>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Recherche et Filtrage</h2>
                </div>
                <form method="GET" class="filter-controls">
                    <input type="text" name="search" class="search-box" placeholder="Rechercher pièce, catégorie..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="category" class="filter-select">
                        <option value="0">Toutes les catégories</option>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['Category_ID']; ?>" <?php echo $category_filter === $cat['Category_ID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['Category_Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <select name="stock" class="filter-select">
                        <option value="">Tous les stocks</option>
                        <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Stock Faible</option>
                        <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Rupture Stock</option>
                    </select>
                    <button type="submit" class="btn-search">🔍 Chercher</button>
                </form>
            </div>
            
            <!-- Spare Parts Table -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Inventaire des Pièces</h2>
                    <span style="font-size: 14px; color: #999;"><?php echo count($spare_parts); ?> résultat(s)</span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom de la Pièce</th>
                                <th>Catégorie</th>
                                <th>Stock Actuel</th>
                                <th>Prix Unitaire</th>
                                <th>Fournisseur</th>
                                <th>Critique</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($spare_parts)): ?>
                                <?php foreach ($spare_parts as $part): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($part['Part_Name']); ?></strong></td>
                                        <td>
                                            <span class="category-badge">
                                                <?php echo htmlspecialchars($part['Category_Name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="stock-badge stock-<?php 
                                                if ($part['Current_Stock'] === 0) {
                                                    echo 'out';
                                                } elseif ($part['Current_Stock'] <= 5) {
                                                    echo 'low';
                                                } else {
                                                    echo 'in';
                                                }
                                            ?>">
                                                <?php echo $part['Current_Stock']; ?> unité(s)
                                            </span>
                                        </td>
                                        <td>
                                            <span class="price">
                                                <?php echo number_format($part['Unit_Price'], 2, ',', ' '); ?> DT
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size: 13px;">
                                                <div><strong><?php echo htmlspecialchars($part['Supplier_Name']); ?></strong></div>
                                                <div style="color: #999; font-size: 11px;">
                                                    <?php echo htmlspecialchars($part['Phone_Number'] ?? 'N/A'); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($part['Is_Critical'] === 1): ?>
                                                <span class="critical-badge">🔴 Critique</span>
                                            <?php else: ?>
                                                <span style="color: #999; font-size: 12px;">Non</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="part_detail.php?id=<?php echo $part['Part_ID']; ?>" class="action-btn action-view">👁️ Voir</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="no-data">Aucune pièce de rechange trouvée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
