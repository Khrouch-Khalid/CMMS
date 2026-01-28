<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); 
define('DB_NAME', 'cmms');


// Application Configuration
define('APP_NAME', 'CMMS Hôpital');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/cmms/');

// Create database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <title>Erreur de Connexion à la Base de Données</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f5f5f5;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                }
                .error-container {
                    background: white;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    max-width: 500px;
                    text-align: center;
                }
                .error-icon {
                    font-size: 48px;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #d32f2f;
                    margin: 0 0 10px 0;
                }
                p {
                    color: #666;
                    line-height: 1.6;
                    margin: 10px 0;
                }
                .error-details {
                    background: #fff3cd;
                    border: 1px solid #ffc107;
                    border-radius: 4px;
                    padding: 15px;
                    margin: 20px 0;
                    text-align: left;
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    color: #333;
                }
                .solution {
                    background: #e8f5e9;
                    border: 1px solid #4caf50;
                    border-radius: 4px;
                    padding: 15px;
                    margin: 20px 0;
                    text-align: left;
                }
                .solution h3 {
                    margin: 0 0 10px 0;
                    color: #2e7d32;
                }
                .solution ol {
                    margin: 10px 0;
                    padding-left: 20px;
                }
                .solution li {
                    margin: 8px 0;
                }
                code {
                    background: #f5f5f5;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-family: 'Courier New', monospace;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">🔴</div>
                <h1>Erreur de Connexion à la Base de Données</h1>
                <p>Impossible de se connecter à la base de données MySQL</p>
                
                <div class="error-details">
                    <strong>Erreur:</strong><br>
                    <?php echo htmlspecialchars($conn->connect_error); ?>
                </div>
                
                <div class="solution">
                    <h3>✓ Solutions:</h3>
                    <ol>
                        <li><strong>Vérifiez que MySQL est en cours d'exécution</strong><br>
                            Ouvrez XAMPP Control Panel et démarrez le service MySQL
                        </li>
                        
                        <li><strong>Vérifiez les paramètres de connexion</strong><br>
                            Éditer: <code>config/config.php</code>
                            <ul style="margin-top: 8px;">
                                <li>DB_HOST: <code>localhost</code></li>
                                <li>DB_USER: <code>root</code></li>
                                <li>DB_PASS: <code>''</code> (vide pour XAMPP par défaut)</li>
                                <li>DB_NAME: <code>cmms</code></li>
                            </ul>
                        </li>
                        
                        <li><strong>Si vous avez défini un mot de passe pour root</strong><br>
                            Modifiez la ligne dans config.php:<br>
                            <code>define('DB_PASS', 'votre_mot_de_passe');</code>
                        </li>
                        
                        <li><strong>Vérifiez que la base de données 'cmms' existe</strong><br>
                            Allez à phpMyAdmin: <code>http://localhost/phpmyadmin</code>
                        </li>
                    </ol>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
    
    // Set charset to UTF-8
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Erreur de connexion: " . $e->getMessage());
}

// Function to escape input
function safe_input($data) {
    global $conn;
    return $conn->real_escape_string(trim($data));
}

// Function to fetch all records
function fetch_all($query) {
    global $conn;
    $result = $conn->query($query);
    if ($result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

// Function to fetch single record
function fetch_one($query) {
    global $conn;
    $result = $conn->query($query);
    if ($result) {
        return $result->fetch_assoc();
    }
    return null;
}

// Function to execute insert/update/delete
function execute_query($query) {
    global $conn;
    if ($conn->query($query) === TRUE) {
        return true;
    }
    return false;
}

// Function to get last inserted ID
function get_last_id() {
    global $conn;
    return $conn->insert_id;
}

// Function to get error message
function get_error() {
    global $conn;
    return $conn->error;
}

// Function to get equipment count
function get_equipment_count() {
    $query = "SELECT COUNT(*) as count FROM Assets WHERE Status_ID != 5";
    $result = fetch_one($query);
    return $result['count'] ?? 0;
}

// Function to get maintenance in progress count
function get_maintenance_progress_count() {
    $query = "SELECT COUNT(*) as count FROM Maintenance WHERE Started_Date IS NOT NULL AND Completed_Date IS NULL";
    $result = fetch_one($query);
    return $result['count'] ?? 0;
}

// Function to get active technicians count
function get_active_technicians_count() {
    $query = "SELECT COUNT(*) as count FROM Technicians t 
              INNER JOIN Employees e ON t.Employee_ID = e.Employee_ID 
              WHERE e.Status_ID = 1";
    $result = fetch_one($query);
    return $result['count'] ?? 0;
}

// Function to get all equipment
function get_all_equipment() {
    $query = "SELECT a.*, at.Type_Name, d.Department_Name, s.Status_Name, c.Level_Name 
              FROM Assets a 
              LEFT JOIN Asset_Types at ON a.Asset_Type_ID = at.Type_ID
              LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
              LEFT JOIN Asset_Status s ON a.Status_ID = s.Status_ID
              LEFT JOIN Criticality_Level c ON a.Criticality_Level_ID = c.Level_ID
              ORDER BY a.Asset_Name";
    return fetch_all($query);
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>

