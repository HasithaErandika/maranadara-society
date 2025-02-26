<?php
define('APP_START', true); // Add this to allow access
require_once '../classes/User.php';
$user = new User();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = $user->login($_POST['username'], $_POST['password']);
    if ($role) {
        header("Location: " . ($role == 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php'));
        exit;
    } else {
        $error = "Invalid credentials!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<h2>Login</h2>
<?php if (isset($error)) echo "<p>$error</p>"; ?>
<form method="POST">
    <input type="text" name="username" placeholder="Username" required><br>
    <input type="password" name="password" placeholder="Password" required><br>
    <button type="submit">Login</button>
</form>
</body>
</html>