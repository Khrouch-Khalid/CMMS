<?php
session_start();

require_once '../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Ingénieur';

// Handle AJAX GET requests for equipment details
if (isset($_GET['get_details']) && $_GET['get_details'] == 1) {
    header('Content-Type: application/json');
    $asset_id = intval($_GET['id']);
    $query = "SELECT a.*, at.Type_Name, d.Department_Name, s.Status_Name, c.Level_Name, sup.Supplier_Name
              FROM Assets a 
              LEFT JOIN Asset_Types at ON a.Asset_Type_ID = at.Type_ID
              LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
              LEFT JOIN Asset_Status s ON a.Status_ID = s.Status_ID
              LEFT JOIN Criticality_Level c ON a.Criticality_Level_ID = c.Level_ID
              LEFT JOIN Suppliers sup ON a.Supplier_ID = sup.Supplier_ID
              WHERE a.Asset_ID = $asset_id";
    $equipment = fetch_one($query);
    
    if ($equipment) {
        echo json_encode(['success' => true, 'equipment' => $equipment]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Équipement non trouvé']);
    }
    exit();
}

// Fetch all equipment
$equipment_query = "SELECT a.*, at.Type_Name, d.Department_Name, s.Status_Name, c.Level_Name, sup.Supplier_Name
                    FROM Assets a 
                    LEFT JOIN Asset_Types at ON a.Asset_Type_ID = at.Type_ID
                    LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
                    LEFT JOIN Asset_Status s ON a.Status_ID = s.Status_ID
                    LEFT JOIN Criticality_Level c ON a.Criticality_Level_ID = c.Level_ID
                    LEFT JOIN Suppliers sup ON a.Supplier_ID = sup.Supplier_ID
                    ORDER BY a.Asset_Name";
$equipment_list = fetch_all($equipment_query);

// Handle search/filter
$search = isset($_GET['search']) ? safe_input($_GET['search']) : '';
if ($search) {
    $equipment_list = array_filter($equipment_list, function($item) use ($search) {
        return stripos($item['Asset_Name'], $search) !== false || 
               stripos($item['Department_Name'], $search) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Équipements - Ingénieur | CMMS Hôpital</title>
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
        
        .search-box {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 250px;
            font-size: 14px;
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
        
        .criticality-high {
            background: #ffebee;
            color: #c62828;
        }
        
        .criticality-medium {
            background: #fff3e0;
            color: #e65100;
        }
        
        .criticality-low {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .action-link {
            color: #667eea;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
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
            overflow-y: auto;
        }

        .modal-content {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            margin: 20px auto;
        }

        .modal-content h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 15px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            margin-top: -40px;
        }

        .close:hover {
            color: #000;
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
                <li><a href="equipment.php" class="active">⚙️ Équipements</a></li>
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
                <div>
                    <h1>Gestion des Équipements</h1>
                </div>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <input type="text" class="search-box" id="searchBox" placeholder="Rechercher équipement..." onkeyup="filterTable()">
                    <a href="add_equipment.php" style="background: #667eea; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; white-space: nowrap;">+ Ajouter Équipement</a>
                </div>
            </div>
            
            <div class="table-container">
                <table id="equipmentTable">
                    <thead>
                        <tr>
                            <th>Nom Équipement</th>
                            <th>Type</th>
                            <th>Département</th>
                            <th>Statut</th>
                            <th>Criticité</th>
                            <th>Modèle</th>
                            <th>Fabricant</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($equipment_list)): ?>
                            <?php foreach ($equipment_list as $item): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['Asset_Name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['Type_Name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($item['Department_Name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo ($item['Status_ID'] == 1) ? 'active' : 'inactive'; ?>">
                                            <?php echo htmlspecialchars($item['Status_Name'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge criticality-<?php echo strtolower($item['Level_Name'] ?? 'medium'); ?>">
                                            <?php echo htmlspecialchars($item['Level_Name'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['Model'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($item['Supplier_Name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a href="javascript:void(0)" class="action-link" onclick="viewEquipment(<?php echo $item['Asset_ID']; ?>)">Voir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">Aucun équipement trouvé</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Equipment Details Modal -->
    <div id="detailsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeDetailsModal()">&times;</span>
            <h2 id="equipmentName">Détails de l'Équipement</h2>
            <div id="equipmentDetails" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        function filterTable() {
            const input = document.getElementById('searchBox');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('equipmentTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const text = rows[i].textContent || rows[i].innerText;
                rows[i].style.display = text.toUpperCase().indexOf(filter) > -1 ? '' : 'none';
            }
        }
        
        function viewEquipment(id) {
            // Fetch equipment details
            fetch('equipment.php?get_details=1&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayEquipmentDetails(data.equipment);
                        document.getElementById('detailsModal').style.display = 'block';
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors de la récupération des détails');
                });
        }

        function displayEquipmentDetails(equipment) {
            document.getElementById('equipmentName').textContent = equipment.Asset_Name;
            
            const detailsDiv = document.getElementById('equipmentDetails');
            detailsDiv.innerHTML = `
                <div>
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Numéro de Série</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px;">${equipment.Serial_Number || 'N/A'}</div>
                </div>
                <div>
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Type d'Équipement</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px;">${equipment.Type_Name || 'N/A'}</div>
                </div>
                <div>
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Modèle</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px;">${equipment.Model || 'N/A'}</div>
                </div>
                <div>
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Département</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px;">${equipment.Department_Name || 'N/A'}</div>
                </div>
                <div>
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Fournisseur</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px;">${equipment.Supplier_Name || 'N/A'}</div>
                </div>
                <div>
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Statut</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px;">${equipment.Status_Name || 'N/A'}</div>
                </div>
                <div>
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Niveau de Criticité</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px;">${equipment.Level_Name || 'N/A'}</div>
                </div>
                <div>
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Date d'Installation</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px;">${equipment.Installation_Date || 'N/A'}</div>
                </div>
                <div>
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Date d'Expiration Garantie</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px;">${equipment.Warranty_Expiry || 'N/A'}</div>
                </div>
                <div>
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Coût d'Achat (MAD)</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px;">${equipment.Purchase_Cost ? equipment.Purchase_Cost + ' MAD' : 'N/A'}</div>
                </div>
                <div>
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Dernière Maintenance</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px;">${equipment.Last_Maintenance_Date || 'Pas encore'}</div>
                </div>
                <div>
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Prochaine Maintenance</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px;">${equipment.Next_Maintenance_Date || 'À déterminer'}</div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <label style="color: #999; font-size: 12px; text-transform: uppercase; font-weight: 600;">Notes Additionnelles</label>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px; padding: 10px; background: #f5f5f5; border-radius: 6px; min-height: 60px;">${equipment.Additional_Notes || 'Aucune note'}</div>
                </div>
            `;
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('detailsModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
