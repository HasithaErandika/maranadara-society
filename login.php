<?php
define('APP_START', true);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'classes/User.php';
require_once 'config/db_config.php';

$error = '';

if (!isset($_SESSION['db_username']) || !isset($_SESSION['db_password'])) {
    $error = 'System not initialized. Please contact an administrator.';
} else {
    $user = new User($_SESSION['db_username'], $_SESSION['db_password']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            if ($user->verifyUser($username, $password)) {
                $_SESSION['role'] = 'user';
                $_SESSION['username'] = $username;
                header('Location: pages/user/dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['role']);
    unset($_SESSION['username']);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - Maranadhara Samithi</title>
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
        .back-link {
            color: var(--primary-orange);
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .back-link:hover {
            color: var(--dark-orange);
            text-decoration: underline;
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
        <h2 class="text-2xl font-bold text-center">Member Portal</h2>
        <p class="text-center text-gray-500 text-sm mt-1">Maranadhara Samithi</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div class="input-wrapper">
            <input type="text" id="username" name="username" placeholder="Username" class="input-field" required>
            <i class="fas fa-user"></i>
        </div>
        <div class="input-wrapper">
            <input type="password" id="password" name="password" placeholder="Password" class="input-field" required>
            <i class="fas fa-lock"></i>
        </div>
        <button type="submit" class="btn-login">Sign In</button>
    </form>
    <p class="text-center mt-4 text-sm">
        <a href="index.php" class="back-link">Return to Home</a>
    </p>
</div>
</body>
</html>