<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';

$conn = (new Database())->getConnection();
$error = $success = '';

// Fetch all members for dropdown
$stmt = $conn->query("SELECT id, member_id, full_name FROM members");
$members = $stmt->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Generate unique incident_id
    $last_incident = $conn->query("SELECT incident_id FROM incidents ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $last_num = $last_incident ? (int)substr($last_incident['incident_id'], 4) : 0;
    $new_incident_id = 'INC-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);

    $member_id = $_POST['member_id'];
    $incident_type = $_POST['incident_type'];
    $incident_datetime = $_POST['incident_datetime'];
    $reporter_name = $_POST['reporter_name'];
    $reporter_member_id = $_POST['reporter_member_id'] ?: null;
    $payment_status = $_POST['payment_status'];
    $amount_paid = $_POST['amount_paid'] ?: null;
    $payment_date = $_POST['payment_date'] ?: null;
    $payment_method = $_POST['payment_method'];
    $paid_by = $_POST['paid_by'] ?: null;
    $approved_by = $_POST['approved_by'] ?: null;
    $receipt_number = $_POST['receipt_number'] ?: null;
    $notes = $_POST['notes'] ?: null;

    $stmt = $conn->prepare("INSERT INTO incidents (incident_id, member_id, incident_type, incident_datetime, reporter_name, reporter_member_id, payment_status, amount_paid, payment_date, payment_method, paid_by, approved_by, receipt_number, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisssssdssssss", $new_incident_id, $member_id, $incident_type, $incident_datetime, $reporter_name, $reporter_member_id, $payment_status, $amount_paid, $payment_date, $payment_method, $paid_by, $approved_by, $receipt_number, $notes);

    if ($stmt->execute()) {
        $success = "Incident '$new_incident_id' recorded successfully!";
        header("Refresh: 2; url=dashboard.php");
    } else {
        $error = "Error recording incident: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Incident - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f3f4f6;
            --text-color: #1f2937;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --btn-bg: #d35400;
            --btn-hover: #b84500;
            --border-color: #d1d5db;
        }
        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #f3f4f6;
            --card-bg: #374151;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            --btn-bg: #e67e22;
            --btn-hover: #f39c12;
            --border-color: #4b5563;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Noto Sans', sans-serif;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
        }
        .btn-admin {
            background-color: var(--btn-bg);
            transition: all 0.3s ease;
        }
        .btn-admin:hover {
            background-color: var(--btn-hover);
            transform: scale(1.05);
        }
        .input-field {
            border: 1px solid var(--border-color);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .input-field:focus {
            border-color: #d35400;
            box-shadow: 0 0 0 3px rgba(211, 84, 0, 0.2);
            outline: none;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100">
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-orange-600 flex items-center">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-6">
            <span class="text-gray-700 dark:text-gray-300">Welcome, <?php echo $_SESSION['user']; ?></span>
            <a href="../login.php?logout=1" class="text-white px-5 py-2 rounded-lg btn-admin">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container mx-auto px-6 py-20">
    <div class="max-w-3xl mx-auto card p-6 rounded-xl">
        <h1 class="text-2xl font-bold mb-6 text-orange-600">Record Incident</h1>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6"><?php echo $success; ?> Redirecting...</div>
        <?php endif; ?>
        <form method="POST" class="space-y-6">
            <!-- Incident Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="member_id" class="block font-medium mb-1">Member</label>
                    <select id="member_id" name="member_id" class="input-field w-full px-4 py-2 rounded-lg" required>
                        <option value="">Select Member</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="incident_type" class="block font-medium mb-1">Incident Type</label>
                    <select id="incident_type" name="incident_type" class="input-field w-full px-4 py-2 rounded-lg" required>
                        <option value="Accident">Accident</option>
                        <option value="Death">Death</option>
                        <option value="Fraud">Fraud</option>
                        <option value="Payment Issue">Payment Issue</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label for="incident_datetime" class="block font-medium mb-1">Incident Date & Time</label>
                    <input type="datetime-local" id="incident_datetime" name="incident_datetime" class="input-field w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label for="reporter_name" class="block font-medium mb-1">Reporter’s Name</label>
                    <input type="text" id="reporter_name" name="reporter_name" class="input-field w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label for="reporter_member_id" class="block font-medium mb-1">Reporter’s Membership ID (Optional)</label>
                    <input type="text" id="reporter_member_id" name="reporter_member_id" class="input-field w-full px-4 py-2 rounded-lg">
                </div>
            </div>

            <!-- Payment Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="payment_status" class="block font-medium mb-1">Payment Status</label>
                    <select id="payment_status" name="payment_status" class="input-field w-full px-4 py-2 rounded-lg" required>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Paid">Paid</option>
                    </select>
                </div>
                <div>
                    <label for="amount_paid" class="block font-medium mb-1">Amount Paid (LKR, Optional)</label>
                    <input type="number" id="amount_paid" name="amount_paid" step="0.01" class="input-field w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label for="payment_date" class="block font-medium mb-1">Payment Date (Optional)</label>
                    <input type="date" id="payment_date" name="payment_date" class="input-field w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label for="payment_method" class="block font-medium mb-1">Payment Method</label>
                    <select id="payment_method" name="payment_method" class="input-field w-full px-4 py-2 rounded-lg" required>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Mobile Payment">Mobile Payment</option>
                    </select>
                </div>
                <div>
                    <label for="paid_by" class="block font-medium mb-1">Paid By (Optional)</label>
                    <input type="text" id="paid_by" name="paid_by" class="input-field w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label for="approved_by" class="block font-medium mb-1">Approved By (Optional)</label>
                    <input type="text" id="approved_by" name="approved_by" class="input-field w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label for="receipt_number" class="block font-medium mb-1">Receipt Number (Optional)</label>
                    <input type="text" id="receipt_number" name="receipt_number" class="input-field w-full px-4 py-2 rounded-lg">
                </div>
                <div class="md:col-span-2">
                    <label for="notes" class="block font-medium mb-1">Notes (Optional)</label>
                    <textarea id="notes" name="notes" class="input-field w-full px-4 py-2 rounded-lg" rows="3"></textarea>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="text-center">
                <button type="submit" class="text-white px-6 py-3 rounded-lg font-semibold btn-admin">Record Incident</button>
            </div>
        </form>
        <p class="text-center mt-4"><a href="dashboard.php" class="text-orange-600 hover:underline">Back to Dashboard</a></p>
    </div>
</div>
</body>
</html>