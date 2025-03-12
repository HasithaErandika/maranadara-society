<?php
define('APP_START', true);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../classes/User.php';
$user = new User();

$role = isset($_GET['role']) && $_GET['role'] === 'admin' ? 'admin' : 'user';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_role = $user->login($_POST['username'], $_POST['password']);
    if ($login_role) {
        $_SESSION['role'] = $login_role;
        $_SESSION['user'] = $_POST['username'];
        header("Location: " . ($login_role == 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php'));
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}
if (isset($_GET['logout'])) {
    $user->logout();
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-orange: #f97316;    /* Vibrant Orange */
            --secondary-orange: #fb923c;  /* Light Orange */
            --dark-orange: #ea580c;      /* Deep Orange */
            --bg-color: #fff7ed;         /* Warm Off-White */
            --text-color: #1e293b;       /* Slate Gray */
            --card-bg: #ffffff;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        body {
            background: linear-gradient(135deg, var(--bg-color) 0%, #fef3c7 100%);
            color: var(--text-color);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card {
            background: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 1.5rem;
            padding: 2.5rem;
            width: 100%;
            max-width: 480px;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .btn-login {
            background: var(--primary-orange);
            color: white;
            padding: 1rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background: var(--dark-orange);
            transform: scale(1.02);
        }

        .input-field {
            border: 2px solid #e5e7eb;
            background: #fafafa;
            padding: 1rem 1rem 1rem 3rem;
            border-radius: 0.75rem;
            width: 100%;
            transition: all 0.3s ease;
        }

        .input-field:focus {
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1);
            outline: none;
        }

        .input-wrapper {
            position: relative;
            margin-bottom: 1.75rem;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            transition: color 0.3s ease;
        }

        .input-field:focus + i {
            color: var(--primary-orange);
        }

        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border-left: 4px solid #dc2626;
            margin-bottom: 1.5rem;
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

        .header-icon {
            background: var(--secondary-orange);
            padding: 1rem;
            border-radius: 50%;
            color: white;
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
    <div class="flex justify-center mb-8">
        <?php if ($role === 'admin'): ?>
            <i class="fas fa-shield-alt text-2xl header-icon"></i>
        <?php else: ?>
            <i class="fas fa-hands-helping text-2xl header-icon"></i>
        <?php endif; ?>
    </div>
    <h2 class="text-3xl font-bold text-center mb-2">
        <?php echo $role === 'admin' ? 'Admin Portal' : 'Member Portal'; ?>
    </h2>
    <p class="text-center text-gray-600 mb-8">Maranadhara Samithi</p>

    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <div class="input-wrapper">
            <input type="text" id="username" name="username" placeholder="Username" class="input-field" required>
            <i class="fas fa-user"></i>
        </div>
        <div class="input-wrapper">
            <input type="password" id="password" name="password" placeholder="Password" class="input-field" required>
            <i class="fas fa-lock"></i>
        </div>
        <button type="submit" class="btn-login w-full">Sign In</button>
    </form>
    <p class="text-center mt-6 text-sm">
        <a href="../index.php" class="back-link">Return to Home</a>
    </p>
</div>
</body>
</html>