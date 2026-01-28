<?php
session_start();

// Include database configuration
require_once '../config/config.php';

// Demo users for testing
$demo_users = [
    [
        'email' => 'engineer@hospital.ma',
        'password' => 'engineer123',
        'role' => 'engineer',
        'name' => 'Melkaoui Monsif'
    ],
    [
        'email' => 'technician@hospital.ma',
        'password' => 'technician123',
        'role' => 'technician',
        'name' => 'Hassan Hassani'
    ]
];

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        $user_found = false;
        
        // First check database users
        $user_query = "SELECT u.*, e.First_Name, e.Last_Name FROM Users u
                       LEFT JOIN Employees e ON u.Employee_ID = e.Employee_ID
                       WHERE u.Email = '" . mysqli_real_escape_string($GLOBALS['conn'], $email) . "' AND u.Is_Active = 1";
        $user_result = mysqli_query($GLOBALS['conn'], $user_query);
        $db_user = mysqli_fetch_assoc($user_result);
        
        if ($db_user && password_verify($password, $db_user['Password'])) {
            // Database user found and password is correct
            $_SESSION['user_id'] = $db_user['User_ID'];
            $_SESSION['employee_id'] = $db_user['Employee_ID'];
            $_SESSION['email'] = $db_user['Email'];
            $_SESSION['name'] = $db_user['First_Name'] . ' ' . $db_user['Last_Name'];
            $_SESSION['role'] = $db_user['Role'];
            $_SESSION['logged_in'] = true;
            
            // Update last login time
            $update_login = "UPDATE Users SET Last_Login = NOW() WHERE User_ID = " . intval($db_user['User_ID']);
            mysqli_query($GLOBALS['conn'], $update_login);
            
            $user_found = true;
            
            // Redirect to role-based dashboard
            if ($db_user['Role'] === 'engineer') {
                header('Location: ../engineer/dashboard.php');
            } else if ($db_user['Role'] === 'technician') {
                header('Location: ../technician/dashboard.php');
            } else {
                header('Location: ../engineer/dashboard.php');
            }
            exit();
        } else {
            // Check against demo users
            foreach ($demo_users as $user) {
                if ($user['email'] === $email && $user['password'] === $password) {
                    // Set session variables
                    $_SESSION['user_id'] = md5($user['email']);
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    
                    // Get employee_id from database based on name
                    $name_parts = explode(' ', trim($user['name']));
                    $first_name = $name_parts[0] ?? '';
                    $last_name = isset($name_parts[1]) ? implode(' ', array_slice($name_parts, 1)) : '';
                    
                    $emp_query = "SELECT Employee_ID FROM Employees WHERE First_Name = '" . mysqli_real_escape_string($GLOBALS['conn'], $first_name) . "' AND Last_Name = '" . mysqli_real_escape_string($GLOBALS['conn'], $last_name) . "'";
                    $emp_result = mysqli_query($GLOBALS['conn'], $emp_query);
                    $emp_data = mysqli_fetch_assoc($emp_result);
                    if ($emp_data) {
                        $_SESSION['employee_id'] = $emp_data['Employee_ID'];
                    }
                    
                    $user_found = true;
                    
                    // Redirect to role-based dashboard
                    if ($user['role'] === 'engineer') {
                        header('Location: ../engineer/dashboard.php');
                    } else {
                        header('Location: ../technician/dashboard.php');
                    }
                    exit();
                }
            }
        }
        
        if (!$user_found) {
            $error = 'Email ou mot de passe incorrect';
        }
    }
}

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'engineer') {
        header('Location: ../engineer/dashboard.php');
    } else {
        header('Location: ../technician/dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - GMAO Hôpital</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .hospital-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .login-header img {
            width: 180px;
            height: auto;
            margin-bottom: 15px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .error {
            background-color: #fee;
            color: #c33;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c33;
        }
        
        .success {
            background-color: #efe;
            color: #3c3;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #3c3;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .demo-credentials {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .demo-credentials h3 {
            color: #333;
            font-size: 14px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .demo-user {
            background: #f9f9f9;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        
        .demo-user strong {
            display: block;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .demo-user p {
            color: #666;
            margin: 3px 0;
        }
        
        .role-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .role-engineer {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .role-technician {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        .remember-me {
            margin: 15px 0;
            font-size: 14px;
        }
        
        .remember-me input {
            margin-right: 5px;
        }
        
        .footer-text {
            text-align: center;
            color: #999;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="../assets/images/logo1.png" alt="logo">

            <h1>GMAO Hôpital</h1>
            <p>Système de Gestion de Maintenance</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required placeholder="votre@email.ma">
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>
            
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember" style="display: inline; margin-bottom: 0; font-weight: normal;">Se souvenir de moi</label>
            </div>
            
            <button type="submit" class="btn-login">Se connecter</button>
        </form>
        
        <div class="demo-credentials">
            <h3>Comptes de Démonstration</h3>
            
            <div class="demo-user">
                <strong>👨‍💼 Ingénieur Biomédical</strong>
                <p><strong>Email :</strong> engineer@hospital.ma</p>
                <p><strong>Mot de passe :</strong> engineer123</p>
                <span class="role-badge role-engineer">Ingénieur</span>
            </div>
            
            <div class="demo-user">
                <strong>👨‍🔧 Technicien de Maintenance</strong>
                <p><strong>Email :</strong> technician@hospital.ma</p>
                <p><strong>Mot de passe :</strong> technician123</p>
                <span class="role-badge role-technician">Technicien</span>
            </div>
        </div>
        
        <div class="footer-text">
            © 2026 CMMS Hôpital - Tous droits réservés
        </div>
    </div>
</body>
</html>
