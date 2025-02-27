<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
require_once '../../classes/Payment.php';

$member = new Member();
$payment = new Payment();
$error = $success = '';

$members = $member->getAllMembers();
$society_payments = $payment->getPaymentsByType('Society Issued');
$membership_payments = $payment->getPaymentsByType('Membership Fee');
$loan_payments = $payment->getPaymentsByType('Loan Settlement');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $member_id = $_POST['member_id'];
        $amount = $_POST['amount'];
        $date = $_POST['date'];
        $payment_mode = $_POST['payment_mode'];
        $payment_type = $_POST['payment_type'];
        $receipt_number = $_POST['receipt_number'] ?: null;
        $remarks = $_POST['remarks'] ?: null;

        if ($payment->addPayment($member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks)) {
            $success = "Payment added successfully!";
            $society_payments = $payment->getPaymentsByType('Society Issued');
            $membership_payments = $payment->getPaymentsByType('Membership Fee');
            $loan_payments = $payment->getPaymentsByType('Loan Settlement');
        } else {
            $error = "Error adding payment.";
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
            --bg-color: #f3f4f6;
            --text-color: #1f2937;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --btn-bg: #d35400;
            --btn-hover: #b84500;
            --border-color: #e5e7eb;
            --accent-color: #f97316;
            --confirm-bg: #10b981;
            --confirm-hover: #059669;
            --sidebar-width: 64px;
            --sidebar-expanded: 240px;
        }
        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #f3f4f6;
            --card-bg: #374151;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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
            border-radius: 12px;
            padding: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }
        .btn-admin {
            background-color: var(--btn-bg);
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-admin:hover {
            background-color: var(--btn-hover);
            transform: scale(1.03);
        }
        .btn-delete {
            background-color: #dc2626;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-delete:hover {
            background-color: #b91c1c;
            transform: scale(1.03);
        }
        .btn-confirm {
            background-color: var(--confirm-bg);
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-confirm:hover {
            background-color: var(--confirm-hover);
            transform: scale(1.03);
        }
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 12px;
            transition: width 0.3s ease;
            position: fixed;
            top: 80px;
            left: 16px;
            height: calc(100vh - 100px);
            overflow: hidden;
            z-index: 20;
        }
        .sidebar:hover, .sidebar-expanded {
            width: var(--sidebar-expanded);
            z-index: 30;
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: var(--text-color);
            transition: background-color 0.2s ease;
        }
        .sidebar-item:hover, .sidebar-item.active {
            background-color: #f97316;
            color: white;
        }
        .sidebar-item i {
            width: 24px;
            text-align: center;
            margin-right: 12px;
        }
        .sidebar-item span {
            display: none;
            white-space: nowrap;
        }
        .sidebar:hover .sidebar-item span, .sidebar-expanded .sidebar-item span {
            display: inline;
        }
        .main-content {
            transition: margin-left 0.3s ease;
            margin-left: calc(var(--sidebar-width) + 16px);
        }
        .sidebar:hover ~ .main-content, .sidebar-expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) + 16px);
        }
        .input-field {
            border: 1px solid var(--border-color);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            border-radius: 8px;
            padding: 0.5rem;
        }
        .input-field:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
            outline: none;
        }
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            background-color: var(--card-bg);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
        }
        th {
            background-color: #f97316;
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        tr:hover {
            background-color: #fef5e7;
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
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                left: 0;
            }
            .sidebar-expanded {
                width: var(--sidebar-expanded);
            }
            .main-content {
                margin-left: 16px;
            }
            .sidebar:hover {
                width: var(--sidebar-width);
            }
            .sidebar:hover ~ .main-content {
                margin-left: calc(var(--sidebar-width) + 16px);
            }
        }
    </style>
</head>
<body class="bg-gray-100">
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-orange-600 flex items-center" aria-label="Home">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-4">
            <span class="text-gray-700 dark:text-gray-300 hidden md:inline">Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
            <button class="md:hidden text-orange-600" id="sidebar-toggle" aria-label="Toggle Sidebar">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            <a href="../login.php?logout=1" class="text-white px-4 py-2 rounded-lg btn-admin" aria-label="Logout">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex min-h-screen pt-20">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <ul class="mt-4">
            <li class="sidebar-item"><a href="dashboard.php" class="flex items-center"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <li class="sidebar-item"><a href="add_member.php" class="flex items-center"><i class="fas fa-user-plus"></i><span>Add Member</span></a></li>
            <li class="sidebar-item"><a href="incidents.php?action=add" class="flex items-center"><i class="fas fa-file-alt"></i><span>Record Incident</span></a></li>
            <li class="sidebar-item active"><a href="payments.php" class="flex items-center"><i class="fas fa-money-bill"></i><span>Manage Payments</span></a></li>
            <li class="sidebar-item"><a href="loans.php?action=add" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Add Loan</span></a></li>
            <li class="sidebar-item"><a href="members.php" class="flex items-center"><i class="fas fa-users"></i><span>Manage Members</span></a></li>
            <li class="sidebar-item"><a href="loans.php" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Manage Loans</span></a></li>
            <li class="sidebar-item"><a href="incidents.php" class="flex items-center"><i class="fas fa-file-alt"></i><span>Manage Incidents</span></a></li>
        </ul>
    </aside>

    <!-- Dashboard Content -->
    <main class="flex-1 p-6 main-content" id="main-content">
        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-orange-600">Manage Payments</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Track and update payment records.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Add Payment Form -->
        <div class="card mb-6">
            <h2 class="text-lg font-semibold mb-3 text-orange-600">Add Payment</h2>
            <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label for="member_id" class="block text-sm font-medium mb-1">Member</label>
                    <select id="member_id" name="member_id" class="input-field w-full" required>
                        <option value="">Select Member</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="amount" class="block text-sm font-medium mb-1">Amount (LKR)</label>
                    <input type="number" id="amount" name="amount" step="0.01" class="input-field w-full" required>
                </div>
                <div>
                    <label for="date" class="block text-sm font-medium mb-1">Date</label>
                    <input type="date" id="date" name="date" class="input-field w-full" required>
                </div>
                <div>
                    <label for="payment_mode" class="block text-sm font-medium mb-1">Payment Mode</label>
                    <select id="payment_mode" name="payment_mode" class="input-field w-full" required>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div>
                    <label for="payment_type" class="block text-sm font-medium mb-1">Payment Type</label>
                    <select id="payment_type" name="payment_type" class="input-field w-full" required>
                        <option value="Society Issued">Society Issued</option>
                        <option value="Membership Fee">Membership Fee</option>
                        <option value="Loan Settlement">Loan Settlement</option>
                    </select>
                </div>
                <div>
                    <label for="receipt_number" class="block text-sm font-medium mb-1">Receipt Number</label>
                    <input type="text" id="receipt_number" name="receipt_number" class="input-field w-full">
                </div>
                <div class="lg:col-span-3">
                    <label for="remarks" class="block text-sm font-medium mb-1">Remarks</label>
                    <textarea id="remarks" name="remarks" class="input-field w-full" rows="2"></textarea>
                </div>
                <div class="lg:col-span-3 text-center">
                    <button type="submit" name="add" class="text-white px-6 py-2 rounded-lg font-semibold btn-admin">Add Payment</button>
                </div>
            </form>
        </div>

        <!-- Payments Issued from Society -->
        <div class="card mb-6">
            <h2 class="text-lg font-semibold mb-3 text-orange-600">Payments Issued from Society</h2>
            <div class="table-container">
                <table>
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
                                    <span class="confirmed-badge"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($p['confirmed_by']); ?></span>
                                <?php else: ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="confirm" class="btn-confirm text-white px-3 py-1"><i class="fas fa-check"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="flex space-x-2">
                                <?php if (!$p['is_confirmed']): ?>
                                    <a href="edit_payment.php?id=<?php echo $p['id']; ?>" class="text-white px-2 py-1 rounded-lg btn-admin"><i class="fas fa-edit"></i></a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="text-white px-2 py-1 btn-delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($society_payments)): ?>
                        <tr><td colspan="8" class="text-center text-gray-500 dark:text-gray-400 py-4">No society payments recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payments from Members for Membership Fees -->
        <div class="card mb-6">
            <h2 class="text-lg font-semibold mb-3 text-orange-600">Membership Fee Payments</h2>
            <div class="table-container">
                <table>
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
                                    <span class="confirmed-badge"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($p['confirmed_by']); ?></span>
                                <?php else: ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="confirm" class="btn-confirm text-white px-3 py-1"><i class="fas fa-check"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="flex space-x-2">
                                <?php if (!$p['is_confirmed']): ?>
                                    <a href="edit_payment.php?id=<?php echo $p['id']; ?>" class="text-white px-2 py-1 rounded-lg btn-admin"><i class="fas fa-edit"></i></a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="text-white px-2 py-1 btn-delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($membership_payments)): ?>
                        <tr><td colspan="8" class="text-center text-gray-500 dark:text-gray-400 py-4">No membership fee payments recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payments for Loan Settlements -->
        <div class="card mb-6">
            <h2 class="text-lg font-semibold mb-3 text-orange-600">Loan Settlement Payments</h2>
            <div class="table-container">
                <table>
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
                    <?php foreach ($loan_payments as $p): ?>
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
                                    <span class="confirmed-badge"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($p['confirmed_by']); ?></span>
                                <?php else: ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="confirm" class="btn-confirm text-white px-3 py-1"><i class="fas fa-check"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="flex space-x-2">
                                <?php if (!$p['is_confirmed']): ?>
                                    <a href="edit_payment.php?id=<?php echo $p['id']; ?>" class="text-white px-2 py-1 rounded-lg btn-admin"><i class="fas fa-edit"></i></a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="text-white px-2 py-1 btn-delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($loan_payments)): ?>
                        <tr><td colspan="8" class="text-center text-gray-500 dark:text-gray-400 py-4">No loan settlement payments recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-center"><a href="dashboard.php" class="text-orange-600 hover:underline">Back to Dashboard</a></p>
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
    const mainContent = document.getElementById('main-content');

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-expanded');
    });

    if (window.innerWidth <= 768) {
        sidebar.addEventListener('mouseenter', (e) => e.preventDefault());
    }
</script>
</body>
</html>