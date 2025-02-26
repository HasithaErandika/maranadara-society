<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') exit;
require_once '../../classes/Member.php';
$member = new Member();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $member->addMember($_POST['name'], $_POST['address'], $_POST['contact']);
    echo '<div class="alert alert-success">Member added successfully!</div>';
}
?>
    <h2 class="mb-4">Add Member</h2>
    <form method="POST" class="card p-4 shadow">
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control" id="name" name="name" placeholder="Enter name" required>
        </div>
        <div class="mb-3">
            <label for="address" class="form-label">Address</label>
            <input type="text" class="form-control" id="address" name="address" placeholder="Enter address" required>
        </div>
        <div class="mb-3">
            <label for="contact" class="form-label">Contact</label>
            <input type="text" class="form-control" id="contact" name="contact" placeholder="Enter contact" required>
        </div>
        <button type="submit" class="btn btn-success">Add Member</button>
    </form>
<?php require_once '../../includes/footer.php'; ?>