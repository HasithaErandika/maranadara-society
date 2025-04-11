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
    <title>Admin Access - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-orange: #f97316;
            --secondary-orange: #fb923c;
            --dark-orange: #ea580c;
            --bg-color: #fff7ed;
            --text-color: #1e293b;
            --card-bg: #ffffff;
            --card-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        body {
            background: linear-gradient(135deg, var(--bg-color) 0%, #fef3c7 100%);
            color: var(--text-color);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
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
        }
        .btn-login {
            background: var(--primary-orange);
            color: white;
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
        }
        .input-field:focus {
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
            outline: none;
        }
        .input-wrapper {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .input-wrapper i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            transition: color 0.3s ease;
        }
        .input-field:focus + i {
            color: var(--primary-orange);
        }
        .error-message {
            background: #fef2f2;
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
        }
    </style>
</head>
<body>
<div class="card animate-fade-in">
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
</body>
</html>