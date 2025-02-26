<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php'; // Adjusted from ../ to ../../
$member = new Member();
$members = $member->getMembers();
?>
    <h2>Admin Dashboard</h2>
    <p>Welcome, <?php echo $_SESSION['user']; ?>!</p>
    <a href="add_member.php">Add Member</a> |
    <a href="incidents.php">Record Incident</a> |
    <a href="payments.php">Manage Payments</a> |
    <a href="loans.php">Manage Loans</a> |
    <a href="../login.php?logout=1">Logout</a>
    <h3>Members</h3>
    <ul>
        <?php foreach ($members as $m) {
            echo "<li>{$m['name']} - {$m['contact']}</li>";
        } ?>
    </ul>
<?php require_once '../../includes/footer.php'; ?>