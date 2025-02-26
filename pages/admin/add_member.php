<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') exit;
require_once '../../classes/Member.php';
$member = new Member();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $member->addMember($_POST['name'], $_POST['address'], $_POST['contact']);
    header("Location: dashboard.php");
    exit;
}
?>
<h2>Add Member</h2>
<form method="POST">
    <input type="text" name="name" placeholder="Name" required><br>
    <input type="text" name="address" placeholder="Address" required><br>
    <input type="text" name="contact" placeholder="Contact" required><br>
    <button type="submit">Add</button>
</form>
<?php require_once '../../includes/footer.php'; ?>