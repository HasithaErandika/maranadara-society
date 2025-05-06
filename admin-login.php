<?php
define('APP_START', true);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db_config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_username = trim($_POST['db_username'] ?? '');
    $db_password = trim($_POST['db_password'] ?? '');

    if (empty($db_username) || empty($db_password)) {
        $error = 'Please fill in all fields.';
    } else {
        // Test database connection
        $conn = @new mysqli(DB_HOST, $db_username, $db_password, DB_NAME);
        if ($conn->connect_error) {
            $error = 'Database connection failed: ' . htmlspecialchars($conn->connect_error);
        } else {
            $conn->close();
            $_SESSION['db_username'] = $db_username;
            $_SESSION['db_password'] = $db_password;
            $_SESSION['role'] = 'admin';
            header('Location: pages/admin/dashboard.php');
            exit;
        }
    }
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: admin-login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<<<<<<< HEAD
    <title>Database Admin Access - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
=======
    <title>Admin Access - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
    <style>
        :root {
            --primary-orange: #f97316;
            --secondary-orange: #fb923c;
            --dark-orange: #ea580c;
<<<<<<< HEAD
            --light-orange: #ffedd5;
            --error-color: #dc2626;
            --success-color: #059669;
            --bg-color: #fff7ed;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
        }
        body {
            background: linear-gradient(135deg, var(--bg-color) 0%, #fef3c7 50%, #ffedd5 100%);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
=======
            --bg-color: #fff7ed;
            --text-color: #1e293b;
            --card-bg: #ffffff;
            --card-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        body {
            background: linear-gradient(135deg, var(--bg-color) 0%, #fef3c7 100%);
            color: var(--text-color);
            font-family: 'Poppins', sans-serif;
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
<<<<<<< HEAD
            padding: 1rem;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at top right, rgba(249, 115, 22, 0.1), transparent 50%),
                        radial-gradient(circle at bottom left, rgba(251, 146, 60, 0.1), transparent 50%);
            pointer-events: none;
        }
        .card {
            background: var(--card-bg);
            box-shadow: 0 8px 32px rgba(249, 115, 22, 0.1);
            border-radius: 1rem;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-orange), var(--secondary-orange));
=======
        }
        .card {
            background: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 1.5rem;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-8px);
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
        }
        .btn-login {
            background: var(--primary-orange);
            color: white;
<<<<<<< HEAD
            padding: 0.875rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        .btn-login:hover {
            background: var(--dark-orange);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.9;
        }
        .btn-login .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }
        .btn-login.loading .spinner {
            display: block;
        }
        .input-field {
            border: 1px solid var(--border-color);
            background: #f8fafc;
            padding: 0.875rem 0.875rem 0.875rem 2.75rem;
            border-radius: 0.5rem;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 0.9375rem;
=======
            padding: 0.75rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        .btn-login:hover {
            background: var(--dark-orange);
            transform: scale(1.02);
        }
        .input-field {
            border: 1px solid #d1d5db;
            background: #f9fafb;
            padding: 0.75rem 0.75rem 0.75rem 2.5rem;
            border-radius: 0.5rem;
            width: 100%;
            transition: all 0.3s ease;
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
        }
        .input-field:focus {
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
            outline: none;
<<<<<<< HEAD
            background: #ffffff;
=======
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
        }
        .input-wrapper {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .input-wrapper i {
            position: absolute;
<<<<<<< HEAD
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
=======
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            transition: color 0.3s ease;
        }
        .input-field:focus + i {
            color: var(--primary-orange);
        }
        .error-message {
            background: #fef2f2;
<<<<<<< HEAD
            color: var(--error-color);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border-left: 4px solid var(--error-color);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: shake 0.5s ease-in-out;
        }
        .success-message {
            background: #ecfdf5;
            color: var(--success-color);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border-left: 4px solid var(--success-color);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideIn 0.5s ease-out;
        }
        .logo {
            max-width: 120px;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }
        .security-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        .password-toggle {
            position: absolute;
            right: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .password-toggle:hover {
            color: var(--primary-orange);
        }
        @keyframes spin {
            to { transform: translateY(-50%) rotate(360deg); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.4s ease-out;
        }
        .error-icon {
            color: var(--error-color);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
=======
            color: #dc2626;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border-left: 4px solid #dc2626;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        .logo {
            max-width: 100px;
            margin-bottom: 1rem;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
        }
    </style>
</head>
<body>
<div class="card animate-fade-in">
<<<<<<< HEAD
    <div class="flex flex-col items-center mb-8">
        <img src="assets/images/logo.png" alt="Maranadhara Logo" class="logo">
        <h2 class="text-2xl font-bold text-center">Database Admin Access</h2>
        <p class="text-center text-gray-500 text-sm mt-1">Secure Database Management System</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle error-icon"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" id="loginForm" class="space-y-4" onsubmit="return handleSubmit(event)">
        <div class="input-wrapper">
            <input type="text" 
                   id="db_username" 
                   name="db_username" 
                   placeholder="Database Username" 
                   class="input-field" 
                   required 
                   autocomplete="username"
                   pattern="[a-zA-Z0-9_]+"
                   title="Only letters, numbers, and underscores are allowed"
                   oninvalid="this.setCustomValidity('Please enter a valid database username (letters, numbers, and underscores only)')"
                   oninput="this.setCustomValidity('')">
            <i class="fas fa-database"></i>
        </div>
        <div class="input-wrapper">
            <input type="password" 
                   id="db_password" 
                   name="db_password" 
                   placeholder="Database Password" 
                   class="input-field" 
                   required 
                   autocomplete="current-password"
                   minlength="6"
                   oninvalid="this.setCustomValidity('Password must be at least 6 characters long')"
                   oninput="this.setCustomValidity('')">
            <i class="fas fa-key"></i>
            <span class="password-toggle" onclick="togglePassword()">
                <i class="fas fa-eye"></i>
            </span>
        </div>
        <button type="submit" class="btn-login">
            <span>Sign In</span>
            <div class="spinner"></div>
        </button>
    </form>

    <div class="security-badge">
        <i class="fas fa-shield-alt"></i>
        <span>Secure Connection • SSL Encrypted</span>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('db_password');
    const icon = document.querySelector('.password-toggle i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function handleSubmit(event) {
    const form = event.target;
    const button = form.querySelector('.btn-login');
    const username = form.querySelector('#db_username').value;
    const password = form.querySelector('#db_password').value;

    // Enhanced validation
    if (!username || !password) {
        showError('Please fill in all fields');
        return false;
    }

    if (!/^[a-zA-Z0-9_]+$/.test(username)) {
        showError('Username can only contain letters, numbers, and underscores');
        return false;
    }

    if (password.length < 6) {
        showError('Password must be at least 6 characters long');
        return false;
    }

    // Show loading state
    button.classList.add('loading');
    button.querySelector('span').textContent = 'Signing in...';

    return true;
}

function showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle error-icon"></i><span>${message}</span>`;
    
    const existingError = document.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    const form = document.getElementById('loginForm');
    form.insertBefore(errorDiv, form.firstChild);
}
</script>
=======
    <div class="flex flex-col items-center mb-6">
        <img src="assets/images/logo.png" alt="Maranadhara Logo" class="logo">
        <h2 class="text-2xl font-bold text-center">Admin Access</h2>
        <p class="text-center text-gray-500 text-sm mt-1">Maranadhara Samithi</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div class="input-wrapper">
            <input type="text" id="db_username" name="db_username" placeholder="Database Username" class="input-field" required>
            <i class="fas fa-database"></i>
        </div>
        <div class="input-wrapper">
            <input type="password" id="db_password" name="db_password" placeholder="Database Password" class="input-field" required>
            <i class="fas fa-key"></i>
        </div>
        <button type="submit" class="btn-login">Sign In</button>
    </form>
</div>
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
</body>
</html>