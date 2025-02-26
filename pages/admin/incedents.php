<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') exit;
require_once '../../classes/Incident.php';
$incident = new Incident();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $incident->addIncident($_POST['member_id'], $_POST['type'], $_POST['date'], $_POST['payment']);
    header("Location: dashboard.php");
    exit;
}
require_once '../../classes/Member.php';
$member = new Member();
$members = $member->getMembers();
?>
<h2>Record Incident</h2>
<form method="POST">
    <select name="member_id" required>
        <?php foreach ($members as $m) {
            echo "<option value='{$m['id']}'>{$m['name']}</option>";
        } ?>
    </select><br>
    <input type="text" name="type" placeholder="Incident Type" required><br>
    <input type="date" name="date" required><br>
    <input type="number" name="payment" placeholder="Payment" step="0.01" required><br>
    <button type="submit">Record</button>
</form>
<?php require_once '../../includes/footer.php'; ?>