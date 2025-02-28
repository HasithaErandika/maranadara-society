<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
require_once '../../classes/Payment.php';
require_once '../../classes/Loan.php';

$member = new Member();
$payment = new Payment();
$loan = new Loan();
$error = $success = '';

$members = $member->getAllMembers();
$society_payments = $payment->getPaymentsByType('Society Issued');
$membership_payments = $payment->getPaymentsByType('Membership Fee');
$loan_payments = $payment->getPaymentsByType('Loan Settlement');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $member_id = $_POST['member_id'];
        $amount = floatval($_POST['amount']);
        $date = $_POST['date'];
        $payment_mode = $_POST['payment_mode'];
        $payment_type = $_POST['payment_type'];
        $receipt_number = $_POST['receipt_number'] ?: null;
        $remarks = $_POST['remarks'] ?: null;
        $loan_id = ($payment_type === 'Loan Settlement' && isset($_POST['loan_id']) && !empty($_POST['loan_id'])) ? intval($_POST['loan_id']) : null;

        if (empty($member_id) || $amount <= 0 || empty($date) || empty($payment_mode) || empty($payment_type)) {
            $error = "All required fields must be filled with valid values.";
        } elseif ($payment_type === 'Loan Settlement' && !$loan_id) {
            $error = "Please select a loan for Loan Settlement.";
        } else {
            if ($payment->addPayment($member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id)) {
                $success = "Payment added successfully!";
                $society_payments = $payment->getPaymentsByType('Society Issued');
                $membership_payments = $payment->getPaymentsByType('Membership Fee');
                $loan_payments = $payment->getPaymentsByType('Loan Settlement');
            } else {
                $error = "Error adding payment.";
            }
        }
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $conn = (new Database())->getConnection();
        $stmt = $conn->prepare("SELECT is_confirmed FROM payments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['is_confirmed']) {
            $error = "Cannot delete a confirmed payment.";
        } else {
            $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Payment deleted successfully!";
                $society_payments = $payment->getPaymentsByType('Society Issued');
                $membership_payments = $payment->getPaymentsByType('Membership Fee');
                $loan_payments = $payment->getPaymentsByType('Loan Settlement');
            } else {
                $error = "Error deleting payment: " . $conn->error;
            }
        }
    } elseif (isset($_POST['confirm'])) {
        $id = $_POST['id'];
        if ($payment->confirmPayment($id, $_SESSION['user'])) {
            $success = "Payment confirmed successfully!";
            $society_payments = $payment->getPaymentsByType('Society Issued');
            $membership_payments = $payment->getPaymentsByType('Membership Fee');
            $loan_payments = $payment->getPaymentsByType('Loan Settlement');
        } else {
            $error = "Error confirming payment or already confirmed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f9fafb;
            --text-color: #111827;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --btn-bg: #ea580c;
            --btn-hover: #c2410c;
            --border-color: #e5e7eb;
            --accent-color: #f97316;
            --sidebar-width: 72px;
            --sidebar-expanded: 260px;
        }
        [data-theme="dark"] {
            --bg-color: #111827;
            --text-color: #f9fafb;
            --card-bg: #1f2937;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            --btn-bg: #f97316;
            --btn-hover: #ea580c;
            --border-color: #374151;
            --accent-color: #fb923c;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s ease;
        }
        .btn-admin {
            background-color: var(--btn-bg);
            color: white;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-admin:hover {
            background-color: var(--btn-hover);
            transform: translateY(-2px);
        }
        .btn-success {
            background-color: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-success:hover {
            background-color: #059669;
            transform: translateY(-2px);
        }
        .btn-delete {
            background-color: #dc2626;
            color: white;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-delete:hover {
            background-color: #b91c1c;
            transform: translateY(-2px);
        }
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 16px;
            transition: width 0.3s ease;
            position: fixed;
            top: 84px;
            left: 16px;
            height: calc(100vh - 104px);
            overflow: hidden;
            z-index: 20;
        }
        .sidebar:hover, .sidebar.expanded {
            width: var(--sidebar-expanded);
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: var(--text-color);
            transition: all 0.2s ease;
        }
        .sidebar-item:hover, .sidebar-item.active {
            background-color: var(--accent-color);
            color: white;
        }
        .sidebar-item i {
            width: 24px;
            text-align: center;
            margin-right: 16px;
        }
        .sidebar-item span {
            display: none;
            white-space: nowrap;
        }
        .sidebar:hover .sidebar-item span, .sidebar.expanded .sidebar-item span {
            display: inline;
        }
        .main-content {
            margin-left: calc(var(--sidebar-width) + 32px);
            transition: margin-left 0.3s ease;
        }
        .sidebar:hover ~ .main-content, .sidebar.expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) + 32px);
        }
        .input-field {
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            width: 100%;
        }
        .input-field:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1);
            outline: none;
        }
        .table-container {
            overflow-x: auto;
        }
        .table th, .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        .table thead th {
            background-color: var(--accent-color);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .table tbody tr:hover {
            background-color: rgba(249, 115, 22, 0.05);
        }
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .tab-btn.active {
            background-color: var(--accent-color);
            color: white;
        }
        .badge-confirmed {
            background-color: #10b981;
            color: white;
            padding: 0.35rem 0.85rem;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
        }
        #loan-section {
            display: none;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                left: 0;
            }
            .sidebar.expanded {
                width: var(--sidebar-expanded);
            }
            .main-content {
                margin-left: 16px;
            }
            .sidebar:hover {
                width: var(--sidebar-width);
            }
            .sidebar:hover ~ .main-content {
                margin-left: calc(var(--sidebar-width) + 32px);
            }
            .card {
                padding: 1.5rem;
            }
        }
        .theme-toggle {
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        .theme-toggle:hover {
            background-color: var(--border-color);
        }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-orange-600 flex items-center" aria-label="Home">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-4">
            <span class="text-gray-700 dark:text-gray-300 hidden md:inline">Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
            <button class="theme-toggle" id="theme-toggle" aria-label="Toggle Theme">
                <i class="fas fa-moon"></i>
            </button>
            <button class="md:hidden text-orange-600" id="sidebar-toggle" aria-label="Toggle Sidebar">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            <a href="../login.php?logout=1" class="text-white btn-admin" aria-label="Logout">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex min-h-screen pt-20">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <ul class="mt-6">
            <li class="sidebar-item"><a href="dashboard.php" class="flex items-center"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <li class="sidebar-item"><a href="add_member.php" class="flex items-center"><i class="fas fa-user-plus"></i><span>Add Member</span></a></li>
            <li class="sidebar-item"><a href="incidents.php?action=add" class="flex items-center"><i class="fas fa-file-alt"></i><span>Record Incident</span></a></li>
            <li class="sidebar-item active"><a href="payments.php" class="flex items-center"><i class="fas fa-money-bill"></i><span>Manage Payments</span></a></li>
            <li class="sidebar-item"><a href="loans.php?action=add" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Add Loan</span></a></li>
            <li class="sidebar-item"><a href="loans.php" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Manage Loans</span></a></li>
            <li class="sidebar-item"><a href="members.php" class="flex items-center"><i class="fas fa-users"></i><span>Manage Members</span></a></li>
            <li class="sidebar-item"><a href="incidents.php" class="flex items-center"><i class="fas fa-file-alt"></i><span>Manage Incidents</span></a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8 main-content" id="main-content">
        <div class="max-w-5xl mx-auto">
            <h1 class="text-4xl font-extrabold mb-8 text-orange-600">Manage Payments</h1>

            <!-- Tabs -->
            <div class="flex flex-wrap space-x-4 mb-6">
                <button class="tab-btn <?php echo !isset($_GET['tab']) || $_GET['tab'] === 'add' ? 'active' : ''; ?>" onclick="showTab('add')">Add Payment</button>
                <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'society' ? 'active' : ''; ?>" onclick="showTab('society')">Society Issued</button>
                <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'membership' ? 'active' : ''; ?>" onclick="showTab('membership')">Membership Fees</button>
                <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'loan' ? 'active' : ''; ?>" onclick="showTab('loan')">Loan Settlements</button>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-8 flex items-center animate-fade-in">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-8 flex items-center animate-fade-in">
                    <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Add Payment Tab -->
            <div id="tab-add" class="card <?php echo isset($_GET['tab']) && $_GET['tab'] !== 'add' ? 'hidden' : ''; ?>">
                <h2 class="text-2xl font-semibold mb-6 text-orange-600">Add New Payment</h2>
                <form method="POST" class="space-y-6" id="add-payment-form">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="member_id" class="block text-sm font-semibold mb-2">Member <span class="text-red-500">*</span></label>
                            <select id="member_id" name="member_id" class="input-field" required onchange="updateLoans()" aria-label="Select Member">
                                <option value="" disabled selected>Select Member</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="amount" class="block text-sm font-semibold mb-2">Amount (LKR) <span class="text-red-500">*</span></label>
                            <input type="number" id="amount" name="amount" step="0.01" class="input-field" required min="0.01" aria-label="Payment Amount">
                        </div>
                        <div>
                            <label for="date" class="block text-sm font-semibold mb-2">Date <span class="text-red-500">*</span></label>
                            <input type="date" id="date" name="date" class="input-field" required aria-label="Payment Date">
                        </div>
                        <div>
                            <label for="payment_mode" class="block text-sm font-semibold mb-2">Payment Mode <span class="text-red-500">*</span></label>
                            <select id="payment_mode" name="payment_mode" class="input-field" required aria-label="Payment Mode">
                                <option value="" disabled selected>Select Mode</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div>
                            <label for="payment_type" class="block text-sm font-semibold mb-2">Payment Type <span class="text-red-500">*</span></label>
                            <select id="payment_type" name="payment_type" class="input-field" required onchange="toggleLoanSection()" aria-label="Payment Type">
                                <option value="" disabled selected>Select Type</option>
                                <option value="Society Issued">Society Issued</option>
                                <option value="Membership Fee">Membership Fee</option>
                                <option value="Loan Settlement">Loan Settlement</option>
                            </select>
                        </div>
                        <div>
                            <label for="receipt_number" class="block text-sm font-semibold mb-2">Receipt Number (Optional)</label>
                            <input type="text" id="receipt_number" name="receipt_number" class="input-field" placeholder="e.g., R12345" aria-label="Receipt Number">
                        </div>
                        <div id="loan-section" class="md:col-span-2">
                            <label for="loan_id" class="block text-sm font-semibold mb-2">Select Loan <span class="text-red-500">*</span></label>
                            <select id="loan_id" name="loan_id" class="input-field" aria-label="Select Loan">
                                <option value="" disabled selected>Select a Loan</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label for="remarks" class="block text-sm font-semibold mb-2">Remarks (Optional)</label>
                            <textarea id="remarks" name="remarks" class="input-field" rows="3" placeholder="Additional details..." aria-label="Remarks"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-center space-x-4">
                        <button type="submit" name="add" class="btn-admin font-semibold">Add Payment</button>
                        <button type="reset" class="btn-delete font-semibold" onclick="toggleLoanSection()">Reset</button>
                    </div>
                </form>
            </div>

            <!-- Society Issued Payments Tab -->
            <div id="tab-society" class="card <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'society' ? 'hidden' : ''; ?>">
                <h2 class="text-2xl font-semibold mb-6 text-orange-600">Society Issued Payments</h2>
                <div class="table-container">
                    <table class="w-full table">
                        <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Amount (LKR)</th>
                            <th>Date</th>
                            <th>Mode</th>
                            <th>Receipt</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($society_payments as $p): ?>
                            <?php $m = $member->getMemberById($p['member_id']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['member_id']); ?></td>
                                <td><?php echo number_format($p['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($p['date']); ?></td>
                                <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($p['is_confirmed']): ?>
                                        <span class="badge-confirmed"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($p['confirmed_by']); ?></span>
                                    <?php else: ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" name="confirm" class="btn-success" onclick="return confirm('Confirm this payment?');" aria-label="Confirm Payment"><i class="fas fa-check"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="flex space-x-2">
                                    <?php if (!$p['is_confirmed']): ?>
                                        <a href="edit_payment.php?id=<?php echo $p['id']; ?>" class="btn-admin px-2 py-1" aria-label="Edit Payment"><i class="fas fa-edit"></i></a>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" name="delete" class="btn-delete px-2 py-1" onclick="return confirm('Are you sure you want to delete this payment?');" aria-label="Delete Payment"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($society_payments)): ?>
                            <tr><td colspan="8" class="py-4 text-center text-gray-500 dark:text-gray-400">No society payments recorded.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Membership Fee Payments Tab -->
            <div id="tab-membership" class="card <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'membership' ? 'hidden' : ''; ?>">
                <h2 class="text-2xl font-semibold mb-6 text-orange-600">Membership Fee Payments</h2>
                <div class="table-container">
                    <table class="w-full table">
                        <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Amount (LKR)</th>
                            <th>Date</th>
                            <th>Mode</th>
                            <th>Receipt</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($membership_payments as $p): ?>
                            <?php $m = $member->getMemberById($p['member_id']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['member_id']); ?></td>
                                <td><?php echo number_format($p['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($p['date']); ?></td>
                                <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($p['is_confirmed']): ?>
                                        <span class="badge-confirmed"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($p['confirmed_by']); ?></span>
                                    <?php else: ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" name="confirm" class="btn-success" onclick="return confirm('Confirm this payment?');" aria-label="Confirm Payment"><i class="fas fa-check"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="flex space-x-2">
                                    <?php if (!$p['is_confirmed']): ?>
                                        <a href="edit_payment.php?id=<?php echo $p['id']; ?>" class="btn-admin px-2 py-1" aria-label="Edit Payment"><i class="fas fa-edit"></i></a>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" name="delete" class="btn-delete px-2 py-1" onclick="return confirm('Are you sure you want to delete this payment?');" aria-label="Delete Payment"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($membership_payments)): ?>
                            <tr><td colspan="8" class="py-4 text-center text-gray-500 dark:text-gray-400">No membership fee payments recorded.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Loan Settlement Payments Tab -->
            <div id="tab-loan" class="card <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'loan' ? 'hidden' : ''; ?>">
                <h2 class="text-2xl font-semibold mb-6 text-orange-600">Loan Settlement Payments</h2>
                <div class="table-container">
                    <table class="w-full table">
                        <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Loan ID</th>
                            <th>Amount (LKR)</th>
                            <th>Date</th>
                            <th>Mode</th>
                            <th>Receipt</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($loan_payments as $p): ?>
                            <?php $m = $member->getMemberById($p['member_id']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['member_id']); ?></td>
                                <td><?php echo htmlspecialchars($p['loan_id'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($p['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($p['date']); ?></td>
                                <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($p['is_confirmed']): ?>
                                        <span class="badge-confirmed"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($p['confirmed_by']); ?></span>
                                    <?php else: ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" name="confirm" class="btn-success" onclick="return confirm('Confirm this payment?');" aria-label="Confirm Payment"><i class="fas fa-check"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="flex space-x-2">
                                    <?php if (!$p['is_confirmed']): ?>
                                        <a href="edit_payment.php?id=<?php echo $p['id']; ?>" class="btn-admin px-2 py-1" aria-label="Edit Payment"><i class="fas fa-edit"></i></a>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" name="delete" class="btn-delete px-2 py-1" onclick="return confirm('Are you sure you want to delete this payment?');" aria-label="Delete Payment"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($loan_payments)): ?>
                            <tr><td colspan="9" class="py-4 text-center text-gray-500 dark:text-gray-400">No loan settlement payments recorded.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-center mt-6"><a href="dashboard.php" class="text-orange-600 hover:underline">Back to Dashboard</a></p>
        </div>
    </main>
</div>

<!-- Footer -->
<footer class="py-6 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6">
        <p class="text-center text-gray-600 dark:text-gray-400 text-sm">© 2025 Maranadhara Samithi. All rights reserved.</p>
    </div>
</footer>

<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const themeToggle = document.getElementById('theme-toggle');
    const form = document.getElementById('add-payment-form');

    // Sidebar toggle for mobile
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('expanded');
    });

    // Theme toggle
    themeToggle.addEventListener('click', () => {
        document.body.dataset.theme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
        themeToggle.querySelector('i').classList.toggle('fa-moon');
        themeToggle.querySelector('i').classList.toggle('fa-sun');
    });

    // Prevent hover on mobile
    if (window.innerWidth <= 768) {
        sidebar.addEventListener('mouseenter', (e) => e.preventDefault());
    }

    // Tab switching
    function showTab(tab) {
        document.querySelectorAll('.card').forEach(card => card.classList.add('hidden'));
        document.getElementById(`tab-${tab}`).classList.remove('hidden');
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`button[onclick="showTab('${tab}')"]`).classList.add('active');
        window.history.pushState({}, '', `?tab=${tab}`);
    }

    // Toggle loan section
    function toggleLoanSection() {
        const paymentType = document.getElementById('payment_type').value;
        const loanSection = document.getElementById('loan-section');
        loanSection.style.display = paymentType === 'Loan Settlement' ? 'block' : 'none';
        if (paymentType === 'Loan Settlement') updateLoans();
    }

    // Update loans dropdown
    function updateLoans() {
        const memberId = document.getElementById('member_id').value;
        const loanDropdown = document.getElementById('loan_id');

        loanDropdown.innerHTML = '<option value="" disabled selected>Loading loans...</option>';

        if (memberId) {
            fetch(`get_loans.php?member_id=${memberId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Failed to fetch loans');
                    return response.json();
                })
                .then(loans => {
                    loanDropdown.innerHTML = '<option value="" disabled selected>Select a Loan</option>';
                    if (loans.length > 0) {
                        loans.forEach(loan => {
                            loanDropdown.innerHTML += `<option value="${loan.id}">Loan #${loan.id} - LKR ${Number(loan.amount).toFixed(2)}</option>`;
                        });
                    } else {
                        loanDropdown.innerHTML += '<option value="">No active loans available</option>';
                    }
                })
                .catch(error => {
                    loanDropdown.innerHTML = '<option value="">Error loading loans</option>';
                    console.error('Error:', error);
                });
        } else {
            loanDropdown.innerHTML = '<option value="" disabled selected>Select a member first</option>';
        }
    }

    // Real-time form validation
    form?.addEventListener('input', (e) => {
        const target = e.target;
        if (target.id === 'amount' && target.value <= 0) {
            target.setCustomValidity('Amount must be greater than 0');
        } else {
            target.setCustomValidity('');
        }
    });

    // Initialize on load
    toggleLoanSection();
</script>
</body>
</html>