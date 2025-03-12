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
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f3f4f6;
            --text-color: #1f2937;
            --card-bg: #ffffff;
            --card-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            --border-color: #d1d5db;
        }
        /* User-specific styles */
        .user-theme {
            --btn-bg: #d35400; /* Orange from index */
            --btn-hover: #b84500;
            --accent-color: #d35400;
        }
        /* Admin-specific styles */
        .admin-theme {
            --btn-bg: #9a3412; /* Deeper red-orange */
            --btn-hover: #7f2a0d;
            --accent-color: #9a3412;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Noto Sans', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .card {
            background: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 1rem;
            padding: 2.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: 100%;
            max-width: 450px;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }
        .btn-login {
            background: var(--btn-bg);
            color: white;
            padding: 0.875rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background: var(--btn-hover);
            transform: translateY(-2px);
        }
        .input-field {
            border: 1px solid var(--border-color);
            background: #f9fafb;
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            border-radius: 0.5rem;
            width: 100%;
            transition: all 0.3s ease;
        }
        .input-field:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(var(--accent-color-rgb), 0.2);
            outline: none;
        }
        .input-wrapper {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .input-wrapper i {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem;
            border-radius: 0.5rem;
            text-align: center;
        }
        .back-link {
            color: var(--accent-color);
            transition: color 0.3s ease;
        }
        .back-link:hover {
            color: var(--btn-hover);
        }
    </style>
</head>
<body class="<?php echo $role === 'admin' ? 'admin-theme' : 'user-theme'; ?>">
<div class="card animate-fade-in">
    <div class="flex justify-center mb-6">
        <?php if ($role === 'admin'): ?>
            <i class="fas fa-shield-alt text-4xl" style="color: var(--accent-color);"></i>
        <?php else: ?>
            <i class="fas fa-hands-helping text-4xl" style="color: var(--accent-color);"></i>
        <?php endif; ?>
    </div>
    <h2 class="text-2xl font-bold text-center mb-6">
        <?php echo $role === 'admin' ? 'Admin Login' : 'Member Login'; ?> - Maranadhara Samithi
    </h2>
    <?php if (isset($error)): ?>
        <div class="error-message mb-6"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST" class="space-y-6">
        <div class="input-wrapper">
            <i class="fas fa-user"></i>
            <input type="text" id="username" name="username" placeholder="Username" class="input-field" required>
        </div>
        <div class="input-wrapper">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" name="password" placeholder="Password" class="input-field" required>
        </div>
        <button type="submit" class="btn-login w-full">Login</button>
    </form>
    <p class="text-center mt-6"><a href="../index.php" class="back-link">Back to Home</a></p>
</div>
<script>
    window.addEventListener('load', () => {
        document.querySelector('.card').classList.add('animate-fade-in');
    });
</script>
</body>
</html>