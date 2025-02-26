<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit;
}
?>
<h2>User Dashboard</h2>
<p>Welcome, <?php echo $_SESSION['user']; ?>!</p>
<a href="../login.php?logout=1">Logout</a>
<?php require_once '../../includes/footer.php'; ?>