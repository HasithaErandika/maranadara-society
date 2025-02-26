<?php
define('APP_START', true);
require_once '../classes/User.php';
$user = new User();

// Get role from URL, default to 'user' if not set
$role = isset($_GET['role']) && $_GET['role'] === 'admin' ? 'admin' : 'user';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_role = $user->login($_POST['username'], $_POST['password']);
    if ($login_role) {
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
    <style>
        :root {
            --bg-color: #f3f4f6;
            --text-color: #1f2937;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --btn-bg-admin: #d35400;
            --btn-hover-admin: #b84500;
            --btn-bg-user: #2ecc71;
            --btn-hover-user: #27ae60;
            --border-color: #d1d5db;
        }
        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #f3f4f6;
            --card-bg: #374151;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            --btn-bg-admin: #e67e22;
            --btn-hover-admin: #f39c12;
            --btn-bg-user: #27ae60;
            --btn-hover-user: #219653;
            --border-color: #4b5563;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
            font-family: 'Noto Sans', sans-serif;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
        }
        .btn-admin {
            background-color: var(--btn-bg-admin);
        }
        .btn-admin:hover {
            background-color: var(--btn-hover-admin);
        }
        .btn-user {
            background-color: var(--btn-bg-user);
        }
        .btn-user:hover {
            background-color: var(--btn-hover-user);
        }
        .input-field {
            border: 1px solid var(--border-color);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        <?php if ($role === 'admin'): ?>
        .input-field:focus {
            border-color: #d35400;
            box-shadow: 0 0 0 3px rgba(211, 84, 0, 0.2);
        }
        <?php else: ?>
        .input-field:focus {
            border-color: #2ecc71;
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.2);
        }
        <?php endif; ?>
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">
<div class="container mx-auto px-6">
    <div class="max-w-md mx-auto card rounded-xl p-6">
        <h2 class="text-2xl font-bold text-center mb-6">
            <?php echo $role === 'admin' ? 'Admin Login' : 'User Login'; ?> - Maranadhara Samithi
        </h2>
        <?php if (isset($error)): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label for="username" class="block font-medium mb-1">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter username" class="input-field w-full px-4 py-2 rounded-lg" required>
            </div>
            <div>
                <label for="password" class="block font-medium mb-1">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter password" class="input-field w-full px-4 py-2 rounded-lg" required>
            </div>
            <button type="submit" class="w-full text-white px-6 py-3 rounded-lg font-semibold <?php echo $role === 'admin' ? 'btn-admin' : 'btn-user'; ?> transition-all">
                Login
            </button>
        </form>
        <p class="text-center mt-4"><a href="../index.php" class="text-orange-600 hover:underline">Back to Home</a></p>
    </div>
</div>
</body>
</html>