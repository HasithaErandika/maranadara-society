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
    } elseif (isset($_POST['update'])) {
        $id = $_POST['id'];
        $conn = (new Database())->getConnection();
        $stmt = $conn->prepare("SELECT is_confirmed FROM payments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['is_confirmed']) {
            $error = "Cannot update a confirmed payment.";
        } else {
            $amount = $_POST['amount'];
            $date = $_POST['date'];
            $payment_mode = $_POST['payment_mode'];
            $payment_type = $_POST['payment_type'];
            $receipt_number = $_POST['receipt_number'] ?: null;
            $remarks = $_POST['remarks'] ?: null;

            $stmt = $conn->prepare("UPDATE payments SET amount = ?, date = ?, payment_mode = ?, payment_type = ?, receipt_number = ?, remarks = ? WHERE id = ?");
            $stmt->bind_param("dsssssi", $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $id);
            if ($stmt->execute()) {
                $success = "Payment updated successfully!";
                $society_payments = $payment->getPaymentsByType('Society Issued');
                $membership_payments = $payment->getPaymentsByType('Membership Fee');
                $loan_payments = $payment->getPaymentsByType('Loan Settlement');
            } else {
                $error = "Error updating payment: " . $conn->error;
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
        .confirmed-badge {
            background-color: var(--confirm-bg);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
        }
        .unconfirmed-badge {
            background-color: #6b7280;
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
        <h1 class="text-3xl font-extrabold mb-6 text-orange-600">Manage Payments</h1>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Add Payment Form -->
        <form method="POST" class="space-y-6 mb-6">
            <h2 class="text-xl font-semibold text-orange-600">Add Payment</h2>
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
                    <label for="date" class="block font-medium mb-1 text-gray-700">Date</label>
                    <input type="date" id="date" name="date" class="input-field w-full px-4 py-2" required>
                </div>
                <div>
                    <label for="payment_mode" class="block font-medium mb-1 text-gray-700">Payment Mode</label>
                    <select id="payment_mode" name="payment_mode" class="input-field w-full px-4 py-2" required>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div>
                    <label for="payment_type" class="block font-medium mb-1 text-gray-700">Payment Type</label>
                    <select id="payment_type" name="payment_type" class="input-field w-full px-4 py-2" required>
                        <option value="Society Issued">Society Issued</option>
                        <option value="Membership Fee">Membership Fee</option>
                        <option value="Loan Settlement">Loan Settlement</option>
                    </select>
                </div>
                <div>
                    <label for="receipt_number" class="block font-medium mb-1 text-gray-700">Receipt Number (Optional)</label>
                    <input type="text" id="receipt_number" name="receipt_number" class="input-field w-full px-4 py-2">
                </div>
                <div class="md:col-span-2">
                    <label for="remarks" class="block font-medium mb-1 text-gray-700">Remarks (Optional)</label>
                    <textarea id="remarks" name="remarks" class="input-field w-full px-4 py-2" rows="3"></textarea>
                </div>
            </div>
            <div class="text-center">
                <button type="submit" name="add" class="text-white px-6 py-3 rounded-lg font-semibold btn-admin">Add Payment</button>
            </div>
        </form>

        <!-- Payments Issued from Society -->
        <h2 class="text-xl font-semibold mb-4 text-orange-600">Payments Issued from Society</h2>
        <div class="overflow-x-auto mb-6">
            <table class="w-full table-hover">
                <thead>
                <tr class="border-b dark:border-gray-600">
                    <th class="py-2 px-4 text-left">Member ID</th>
                    <th class="py-2 px-4 text-left">Amount (LKR)</th>
                    <th class="py-2 px-4 text-left">Date</th>
                    <th class="py-2 px-4 text-left">Mode</th>
                    <th class="py-2 px-4 text-left">Receipt</th>
                    <th class="py-2 px-4 text-left">Remarks</th>
                    <th class="py-2 px-4 text-left">Status</th>
                    <th class="py-2 px-4 text-left">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($society_payments as $p): ?>
                    <?php $m = $member->getMemberById($p['member_id']); ?>
                    <tr class="border-b dark:border-gray-600">
                        <td class="py-2 px-4"><?php echo htmlspecialchars($m['member_id']); ?></td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo number_format($p['amount'], 2); ?>
                            <?php else: ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="payment_type" value="Society Issued">
                                <input type="number" name="amount" value="<?php echo htmlspecialchars($p['amount']); ?>" step="0.01" class="input-field w-full px-2 py-1">
                                <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo htmlspecialchars($p['date']); ?>
                            <?php else: ?>
                                <input type="date" name="date" value="<?php echo htmlspecialchars($p['date']); ?>" class="input-field w-full px-2 py-1">
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo htmlspecialchars($p['payment_mode']); ?>
                            <?php else: ?>
                                <select name="payment_mode" class="input-field w-full px-2 py-1">
                                    <option value="Cash" <?php echo $p['payment_mode'] == 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="Bank Transfer" <?php echo $p['payment_mode'] == 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                    <option value="Cheque" <?php echo $p['payment_mode'] == 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                                </select>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?>
                            <?php else: ?>
                                <input type="text" name="receipt_number" value="<?php echo htmlspecialchars($p['receipt_number'] ?? ''); ?>" class="input-field w-full px-2 py-1">
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?>
                            <?php else: ?>
                                <textarea name="remarks" class="input-field w-full px-2 py-1" rows="2"><?php echo htmlspecialchars($p['remarks'] ?? ''); ?></textarea>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <span class="confirmed-badge"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($p['confirmed_by']); ?></span>
                            <?php else: ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" name="confirm" class="btn-confirm text-white px-3 py-1 rounded-lg"><i class="fas fa-check"></i> Confirm</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4 flex space-x-2">
                            <?php if (!$p['is_confirmed']): ?>
                                <button type="submit" name="update" class="text-white px-2 py-1 rounded-lg btn-admin"><i class="fas fa-save"></i></button>
                                <button type="submit" name="delete" class="text-white px-2 py-1 rounded-lg btn-delete"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($society_payments)): ?>
                    <tr><td colspan="8" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No society payments recorded.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Payments from Members for Membership Fees -->
        <h2 class="text-xl font-semibold mb-4 text-orange-600">Payments from Members for Membership Fees</h2>
        <div class="overflow-x-auto mb-6">
            <table class="w-full table-hover">
                <thead>
                <tr class="border-b dark:border-gray-600">
                    <th class="py-2 px-4 text-left">Member ID</th>
                    <th class="py-2 px-4 text-left">Amount (LKR)</th>
                    <th class="py-2 px-4 text-left">Date</th>
                    <th class="py-2 px-4 text-left">Mode</th>
                    <th class="py-2 px-4 text-left">Receipt</th>
                    <th class="py-2 px-4 text-left">Remarks</th>
                    <th class="py-2 px-4 text-left">Status</th>
                    <th class="py-2 px-4 text-left">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($membership_payments as $p): ?>
                    <?php $m = $member->getMemberById($p['member_id']); ?>
                    <tr class="border-b dark:border-gray-600">
                        <td class="py-2 px-4"><?php echo htmlspecialchars($m['member_id']); ?></td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo number_format($p['amount'], 2); ?>
                            <?php else: ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="payment_type" value="Membership Fee">
                                <input type="number" name="amount" value="<?php echo htmlspecialchars($p['amount']); ?>" step="0.01" class="input-field w-full px-2 py-1">
                                <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo htmlspecialchars($p['date']); ?>
                            <?php else: ?>
                                <input type="date" name="date" value="<?php echo htmlspecialchars($p['date']); ?>" class="input-field w-full px-2 py-1">
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo htmlspecialchars($p['payment_mode']); ?>
                            <?php else: ?>
                                <select name="payment_mode" class="input-field w-full px-2 py-1">
                                    <option value="Cash" <?php echo $p['payment_mode'] == 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="Bank Transfer" <?php echo $p['payment_mode'] == 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                    <option value="Cheque" <?php echo $p['payment_mode'] == 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                                </select>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?>
                            <?php else: ?>
                                <input type="text" name="receipt_number" value="<?php echo htmlspecialchars($p['receipt_number'] ?? ''); ?>" class="input-field w-full px-2 py-1">
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?>
                            <?php else: ?>
                                <textarea name="remarks" class="input-field w-full px-2 py-1" rows="2"><?php echo htmlspecialchars($p['remarks'] ?? ''); ?></textarea>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <span class="confirmed-badge"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($p['confirmed_by']); ?></span>
                            <?php else: ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" name="confirm" class="btn-confirm text-white px-3 py-1 rounded-lg"><i class="fas fa-check"></i> Confirm</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4 flex space-x-2">
                            <?php if (!$p['is_confirmed']): ?>
                                <button type="submit" name="update" class="text-white px-2 py-1 rounded-lg btn-admin"><i class="fas fa-save"></i></button>
                                <button type="submit" name="delete" class="text-white px-2 py-1 rounded-lg btn-delete"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($membership_payments)): ?>
                    <tr><td colspan="8" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No membership fee payments recorded.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Payments for Loan Settlements -->
        <h2 class="text-xl font-semibold mb-4 text-orange-600">Payments for Loan Settlements</h2>
        <div class="overflow-x-auto mb-6">
            <table class="w-full table-hover">
                <thead>
                <tr class="border-b dark:border-gray-600">
                    <th class="py-2 px-4 text-left">Member ID</th>
                    <th class="py-2 px-4 text-left">Amount (LKR)</th>
                    <th class="py-2 px-4 text-left">Date</th>
                    <th class="py-2 px-4 text-left">Mode</th>
                    <th class="py-2 px-4 text-left">Receipt</th>
                    <th class="py-2 px-4 text-left">Remarks</th>
                    <th class="py-2 px-4 text-left">Status</th>
                    <th class="py-2 px-4 text-left">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($loan_payments as $p): ?>
                    <?php $m = $member->getMemberById($p['member_id']); ?>
                    <tr class="border-b dark:border-gray-600">
                        <td class="py-2 px-4"><?php echo htmlspecialchars($m['member_id']); ?></td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo number_format($p['amount'], 2); ?>
                            <?php else: ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="payment_type" value="Loan Settlement">
                                <input type="number" name="amount" value="<?php echo htmlspecialchars($p['amount']); ?>" step="0.01" class="input-field w-full px-2 py-1">
                                <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo htmlspecialchars($p['date']); ?>
                            <?php else: ?>
                                <input type="date" name="date" value="<?php echo htmlspecialchars($p['date']); ?>" class="input-field w-full px-2 py-1">
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo htmlspecialchars($p['payment_mode']); ?>
                            <?php else: ?>
                                <select name="payment_mode" class="input-field w-full px-2 py-1">
                                    <option value="Cash" <?php echo $p['payment_mode'] == 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="Bank Transfer" <?php echo $p['payment_mode'] == 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                    <option value="Cheque" <?php echo $p['payment_mode'] == 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                                </select>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?>
                            <?php else: ?>
                                <input type="text" name="receipt_number" value="<?php echo htmlspecialchars($p['receipt_number'] ?? ''); ?>" class="input-field w-full px-2 py-1">
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?>
                            <?php else: ?>
                                <textarea name="remarks" class="input-field w-full px-2 py-1" rows="2"><?php echo htmlspecialchars($p['remarks'] ?? ''); ?></textarea>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <?php if ($p['is_confirmed']): ?>
                                <span class="confirmed-badge"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($p['confirmed_by']); ?></span>
                            <?php else: ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" name="confirm" class="btn-confirm text-white px-3 py-1 rounded-lg"><i class="fas fa-check"></i> Confirm</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4 flex space-x-2">
                            <?php if (!$p['is_confirmed']): ?>
                                <button type="submit" name="update" class="text-white px-2 py-1 rounded-lg btn-admin"><i class="fas fa-save"></i></button>
                                <button type="submit" name="delete" class="text-white px-2 py-1 rounded-lg btn-delete"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($loan_payments)): ?>
                    <tr><td colspan="8" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No loan settlement payments recorded.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="text-center mt-4"><a href="dashboard.php" class="text-orange-600 hover:underline">Back to Dashboard</a></p>
    </div>
</div>
</body>
</html>