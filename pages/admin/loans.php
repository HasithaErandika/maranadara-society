<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') exit;
require_once '../../classes/Loan.php';
$loan = new Loan();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $loan->addLoan($_POST['member_id'], $_POST['amount'], $_POST['interest_rate'], $_POST['duration']);
    header("Location: dashboard.php");
    exit;
}
require_once '../../classes/Member.php';
$member = new Member();
$members = $member->getMembers();
?>
<h2>Manage Loans</h2>
<form method="POST">
    <select name="member_id" required>
        <?php foreach ($members as $m) {
            echo "<option value='{$m['id']}'>{$m['name']}</option>";
        } ?>
    </select><br>
    <input type="number" name="amount" placeholder="Amount" step="0.01" required><br>
    <input type="number" name="interest_rate" placeholder="Interest Rate (%)" step="0.01" required><br>
    <input type="number" name="duration" placeholder="Duration (months)" required><br>
    <button type="submit">Add Loan</button>
</form>
<?php require_once '../../includes/footer.php'; ?>