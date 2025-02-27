<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
require_once '../../classes/Loan.php';
require_once '../../classes/Payment.php';

$member = new Member();
$loan = new Loan();
$payment = new Payment();
$conn = (new Database())->getConnection();
$error = $success = $monthly_payment = '';

$members = $member->getAllMembers();
$loans = $loan->getAllLoans();
$loan_settlement_payments = $payment->getPaymentsByType('Loan Settlement');

$action = isset($_GET['action']) ? $_GET['action'] : 'view';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $member_id = $_POST['member_id'];
        $amount = $_POST['amount'];
        $interest_rate = $_POST['interest_rate'];
        $duration = $_POST['duration'];

        if ($loan->addLoan($member_id, $amount, $interest_rate, $duration)) {
            $success = "Loan added successfully!";
            $loans = $loan->getAllLoans();
        } else {
            $error = "Error adding loan.";
        }
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("SELECT is_confirmed FROM loans WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['is_confirmed']) {
            $error = "Cannot delete a confirmed loan.";
        } else {
            $stmt = $conn->prepare("DELETE FROM loans WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Loan deleted successfully!";
                $loans = $loan->getAllLoans();
            } else {
                $error = "Error deleting loan: " . $conn->error;
            }
        }
    } elseif (isset($_POST['update'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("SELECT is_confirmed FROM loans WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['is_confirmed']) {
            $error = "Cannot update a confirmed loan.";
        } else {
            $amount = $_POST['amount'];
            $interest_rate = $_POST['interest_rate'];
            $duration = $_POST['duration'];
            $monthly_payment = $loan->calculateMonthlyPayment($amount, $interest_rate, $duration);

            $stmt = $conn->prepare("UPDATE loans SET amount = ?, interest_rate = ?, duration = ?, monthly_payment = ? WHERE id = ?");
            $stmt->bind_param("ddidi", $amount, $interest_rate, $duration, $monthly_payment, $id);
            if ($stmt->execute()) {
                $success = "Loan updated successfully!";
                $loans = $loan->getAllLoans();
            } else {
                $error = "Error updating loan: " . $conn->error;
            }
        }
    } elseif (isset($_POST['calculate'])) {
        $amount = $_POST['amount'];
        $interest_rate = $_POST['interest_rate'];
        $duration = $_POST['duration'];
        $monthly_payment = $loan->calculateMonthlyPayment($amount, $interest_rate, $duration);
    } elseif (isset($_POST['confirm'])) {
        $id = $_POST['id'];
        if ($loan->confirmLoan($id, $_SESSION['user'])) {
            $success = "Loan confirmed successfully!";
            $loans = $loan->getAllLoans();
        } else {
            $error = "Error confirming loan or already confirmed.";
        }
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
            --card-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
            --btn-bg: #d35400;
            --btn-hover: #b84500;
            --border-color: #d1d5db;
            --accent-color: #f97316;
            --confirm-bg: #10b981;
            --confirm-hover: #059669;
        }
        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #f3f4f6;
            --card-bg: #374151;
            --card-shadow: 0 6px 18px rgba(0, 0, 0, 0.3);
            --btn-bg: #e67e22;
            --btn-hover: #f39c12;
            --border-color: #4b5563;
            --accent-color: #fb923c;
            --confirm-bg: #34d399;
            --confirm-hover: #6ee7b7;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Noto Sans', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 0.75rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.15);
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
            transition: all 0.3s ease;
        }
        .btn-delete:hover {
            background-color: #b91c1c;
            transform: scale(1.05);
        }
        .btn-confirm {
            background-color: var(--confirm-bg);
            transition: all 0.3s ease;
        }
        .btn-confirm:hover {
            background-color: var(--confirm-hover);
            transform: scale(1.05);
        }
        .table-hover tbody tr:hover {
            background-color: #fef5e7;
        }
        .input-field {
            border: 1px solid var(--border-color);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            border-radius: 0.375rem;
        }
        .input-field:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
            outline: none;
        }
        .settled-badge {
            background-color: #10b981;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            margin-right: 0.5rem;
        }
        .confirmed-badge {
            background-color: var(--confirm-bg);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
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
    <div class="card p-6">
        <h1 class="text-3xl font-extrabold mb-6 text-orange-600">Manage Loans</h1>
        <?php if ($action == 'add'): ?>
            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6"><?php echo $success; ?> Redirecting...</div>
            <?php endif; ?>
            <form method="POST" class="space-y-6">
                <!-- Loan Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="member_id" class="block font-medium mb-1 text-gray-700">Member</label>
                        <select id="member_id" name="member_id" class="input-field w-full px-4 py-2" required>
                            <option value="">Select Member</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="amount" class="block font-medium mb-1 text-gray-700">Amount (LKR)</label>
                        <input type="number" id="amount" name="amount" step="0.01" class="input-field w-full px-4 py-2" required>
                    </div>
                    <div>
                        <label for="interest_rate" class="block font-medium mb-1 text-gray-700">Interest Rate (%)</label>
                        <input type="number" id="interest_rate" name="interest_rate" step="0.01" class="input-field w-full px-4 py-2" required>
                    </div>
                    <div>
                        <label for="duration" class="block font-medium mb-1 text-gray-700">Duration (Months)</label>
                        <input type="number" id="duration" name="duration" class="input-field w-full px-4 py-2" required>
                    </div>
                </div>

                <!-- Loan Calculator -->
                <div class="card p-4 bg-gray-50 dark:bg-gray-800">
                    <h2 class="text-lg font-semibold mb-2 text-gray-700 dark:text-gray-300">Loan Calculator</h2>
                    <button type="submit" name="calculate" class="text-white px-4 py-2 rounded-lg btn-admin mb-2">Calculate Monthly Payment</button>
                    <?php if ($monthly_payment): ?>
                        <p class="text-green-600">Estimated Monthly Payment: LKR <?php echo number_format($monthly_payment, 2); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Submit Button -->
                <div class="text-center">
                    <button type="submit" name="add" class="text-white px-6 py-3 rounded-lg font-semibold btn-admin">Add Loan</button>
                </div>
            </form>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6"><?php echo $success; ?></div>
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
                        <th class="py-2 px-4 text-left">Status</th>
                        <th class="py-2 px-4 text-left">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($loans as $l): ?>
                        <?php
                        $m = $member->getMemberById($l['member_id']);
                        $settled_payments = array_filter($loan_settlement_payments, fn($p) => $p['member_id'] == $l['member_id']);
                        $total_settled = array_sum(array_column($settled_payments, 'amount'));
                        $total_loan_cost = $l['monthly_payment'] * $l['duration'];
                        $is_settled = $total_settled >= $total_loan_cost;
                        ?>
                        <tr class="border-b dark:border-gray-600">
                            <td class="py-2 px-4">
                                <?php if ($is_settled): ?>
                                    <span class="settled-badge"><i class="fas fa-check mr-1"></i>Settled</span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($m['member_id']); ?>
                            </td>
                            <td class="py-2 px-4">
                                <?php if ($l['is_confirmed']): ?>
                                    <?php echo number_format($l['amount'], 2); ?>
                                <?php else: ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                    <input type="number" name="amount" value="<?php echo htmlspecialchars($l['amount']); ?>" step="0.01" class="input-field w-full px-2 py-1">
                                    <?php endif; ?>
                            </td>
                            <td class="py-2 px-4">
                                <?php if ($l['is_confirmed']): ?>
                                    <?php echo number_format($l['interest_rate'], 2); ?>
                                <?php else: ?>
                                    <input type="number" name="interest_rate" value="<?php echo htmlspecialchars($l['interest_rate']); ?>" step="0.01" class="input-field w-full px-2 py-1">
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4">
                                <?php if ($l['is_confirmed']): ?>
                                    <?php echo $l['duration']; ?>
                                <?php else: ?>
                                    <input type="number" name="duration" value="<?php echo htmlspecialchars($l['duration']); ?>" class="input-field w-full px-2 py-1">
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4"><?php echo number_format($l['monthly_payment'], 2); ?></td>
                            <td class="py-2 px-4">
                                <?php if ($l['is_confirmed']): ?>
                                    <span class="confirmed-badge"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($l['confirmed_by']); ?></span>
                                <?php else: ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                        <button type="submit" name="confirm" class="btn-confirm text-white px-3 py-1 rounded-lg"><i class="fas fa-check"></i> Confirm</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4 flex space-x-2">
                                <?php if (!$l['is_confirmed']): ?>
                                    <button type="submit" name="update" class="text-white px-2 py-1 rounded-lg btn-admin"><i class="fas fa-save"></i></button>
                                    <button type="submit" name="delete" class="text-white px-2 py-1 rounded-lg btn-delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($loans)): ?>
                        <tr><td colspan="7" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No loans recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="text-center mt-4"><a href="dashboard.php" class="text-orange-600 hover:underline">Back to Dashboard</a></p>
    </div>
</div>
</body>
</html>