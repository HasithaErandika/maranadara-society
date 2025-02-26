<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') exit;
require_once '../../classes/Payment.php';
$payment = new Payment();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment->addPayment($_POST['member_id'], $_POST['amount'], $_POST['date']);
    header("Location: dashboard.php");
    exit;
}
require_once '../../classes/Member.php';
$member = new Member();
$members = $member->getMembers();
?>
<h2>Manage Payments</h2>
<form method="POST">
    <select name="member_id" required>
        <?php foreach ($members as $m) {
            echo "<option value='{$m['id']}'>{$m['name']}</option>";
        } ?>
    </select><br>
    <input type="number" name="amount" placeholder="Amount" step="0.01" required><br>
    <input type="date" name="date" required><br>
    <button type="submit">Add Payment</button>
</form>
<?php require_once '../../includes/footer.php'; ?>