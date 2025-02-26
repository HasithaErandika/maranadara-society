<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
require_once '../../classes/Loan.php';

$member = new Member();
$loan = new Loan();
$conn = (new Database())->getConnection();
$error = $success = $monthly_payment = '';

$members = $member->getAllMembers();
$loans = $loan->getAllLoans();

$action = isset($_GET['action']) ? $_GET['action'] : 'view';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $member_id = $_POST['member_id'];
        $amount = $_POST['amount'];
        $interest_rate = $_POST['interest_rate'];
        $duration = $_POST['duration'];

        if ($loan->addLoan($member_id, $amount, $interest_rate, $duration)) {
            $success = "Loan added successfully!";
            $loans = $loan->getAllLoans(); // Refresh list
        } else {
            $error = "Error adding loan.";
        }
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM loans WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "Loan deleted successfully!";
            $loans = $loan->getAllLoans(); // Refresh list
        } else {
            $error = "Error deleting loan: " . $conn->error;
        }
    } elseif (isset($_POST['update'])) {
        $id = $_POST['id'];
        $amount = $_POST['amount'];
        $interest_rate = $_POST['interest_rate'];
        $duration = $_POST['duration'];
        $monthly_payment = $loan->calculateMonthlyPayment($amount, $interest_rate, $duration);

        $stmt = $conn->prepare("UPDATE loans SET amount = ?, interest_rate = ?, duration = ?, monthly_payment = ? WHERE id = ?");
        $stmt->bind_param("ddidi", $amount, $interest_rate, $duration, $monthly_payment, $id);
        if ($stmt->execute()) {
            $success = "Loan updated successfully!";
            $loans = $loan->getAllLoans(); // Refresh list
        } else {
            $error = "Error updating loan: " . $conn->error;
        }
    } elseif (isset($_POST['calculate'])) {
        $amount = $_POST['amount'];
        $interest_rate = $_POST['interest_rate'];
        $duration = $_POST['duration'];
        $monthly_payment = $loan->calculateMonthlyPayment($amount, $interest_rate, $duration);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Loans - Maranadhara Samithi</title>
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
        .btn-delete {
            background-color: #dc2626;
        }
        .btn-delete:hover {
            background-color: #b91c1c;
        }
        .table-hover tbody tr:hover {
            background-color: #fef5e7;
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
    <div class="card p-6 rounded-xl">
        <h1 class="text-2xl font-bold mb-6 text-orange-600">Manage Loans</h1>

        <?php if ($action == 'add'): ?>
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6"><?php echo htmlspecialchars($success); ?> Redirecting...</div>
            <?php endif; ?>

            <form method="POST" class="space-y-6" id="loan-form">
                <!-- Loan Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="member_id" class="block font-medium mb-1">Member</label>
                        <select id="member_id" name="member_id" class="input-field w-full px-4 py-2 rounded-lg" required>
                            <option value="">Select Member</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['id']); ?>">
                                    <?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="amount" class="block font-medium mb-1">Loan Amount (LKR)</label>
                        <input type="number" id="amount" name="amount" step="0.01" class="input-field w-full px-4 py-2 rounded-lg" required>
                    </div>
                    <div>
                        <label for="interest_rate" class="block font-medium mb-1">Interest Rate (%)</label>
                        <input type="number" id="interest_rate" name="interest_rate" step="0.01" class="input-field w-full px-4 py-2 rounded-lg" required>
                    </div>
                    <div>
                        <label for="duration" class="block font-medium mb-1">Duration (Months)</label>
                        <input type="number" id="duration" name="duration" class="input-field w-full px-4 py-2 rounded-lg" required>
                    </div>
                </div>

                <!-- Loan Calculator -->
                <div class="card p-4 bg-gray-50 dark:bg-gray-800">
                    <h2 class="text-lg font-semibold mb-2">Loan Calculator</h2>
                    <button type="button" id="calculate-loan" class="text-white px-4 py-2 rounded-lg btn-admin mb-2">Calculate Monthly Payment</button>

                    <div id="loan-results" class="hidden mt-2">
                        <p class="text-green-600 font-medium">Estimated Monthly Payment: LKR <span id="monthly-payment">0.00</span></p>
                        <p class="text-gray-700">Total Interest Payable: LKR <span id="total-interest">0.00</span></p>
                        <p class="text-gray-700">Total Repayment Amount: LKR <span id="total-repayment">0.00</span></p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="text-center">
                    <button type="submit" name="add" id="submit-loan" class="text-white px-6 py-3 rounded-lg font-semibold btn-admin">Add Loan</button>
                </div>
            </form>

        <?php else: ?>
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-gray-600">
                        <th class="py-2 px-4 text-left">Member ID</th>
                        <th class="py-2 px-4 text-left">Amount (LKR)</th>
                        <th class="py-2 px-4 text-left">Interest Rate (%)</th>
                        <th class="py-2 px-4 text-left">Duration (Months)</th>
                        <th class="py-2 px-4 text-left">Monthly Payment (LKR)</th>
                        <th class="py-2 px-4 text-left">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($loans as $l): ?>
                        <?php $m = $member->getMemberById($l['member_id']); ?>
                        <tr class="border-b dark:border-gray-600">
                            <td class="py-2 px-4"><?php echo htmlspecialchars($m['member_id']); ?></td>
                            <td class="py-2 px-4"><?php echo number_format($l['amount'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo number_format($l['interest_rate'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo $l['duration']; ?></td>
                            <td class="py-2 px-4"><?php echo number_format($l['monthly_payment'], 2); ?></td>
                            <td class="py-2 px-4 flex space-x-2">
                                <form method="POST">
                                    <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                    <button type="submit" name="delete" class="text-white px-2 py-1 rounded-lg btn-delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="text-center mt-4"><a href="dashboard.php" class="text-orange-600 hover:underline">Back to Dashboard</a></p>
    </div>
</div>

<!-- JavaScript for Loan Calculation -->
<script>
    document.getElementById("calculate-loan").addEventListener("click", function() {
        let P = parseFloat(document.getElementById("amount").value);
        let r = parseFloat(document.getElementById("interest_rate").value) / 12 / 100;
        let n = parseInt(document.getElementById("duration").value);

        if (P > 0 && r > 0 && n > 0) {
            let M = P * r * Math.pow(1 + r, n) / (Math.pow(1 + r, n) - 1);
            let totalInterest = (M * n) - P;
            let totalRepayment = M * n;

            document.getElementById("monthly-payment").textContent = M.toFixed(2);
            document.getElementById("total-interest").textContent = totalInterest.toFixed(2);
            document.getElementById("total-repayment").textContent = totalRepayment.toFixed(2);

            document.getElementById("loan-results").classList.remove("hidden");
        }
    });
</script>

</body>
</html>