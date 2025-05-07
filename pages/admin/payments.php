<?php
define('APP_START', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    error_log("Unauthorized access attempt - Session role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'not set'));
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

$current_month = date('Y-m-01');
if (isset($_GET['auto_add_fees']) && isset($_GET['tab']) && $_GET['tab'] === 'membership') {
    try {
        $count = $payment->autoAddMembershipFees($current_month);
        $success = $count > 0 ? "Added membership fees for $count members." : "No new fees added.";
        $membership_payments = $payment->getPaymentsByType('Membership Fee');
    } catch (Exception $e) {
        $error = "Error adding membership fees.";
        error_log("Auto-add fees error: " . $e->getMessage());
    }
}

if (isset($_GET['auto_add_loan_settlements']) && isset($_GET['tab']) && $_GET['tab'] === 'loan') {
    try {
        $count = $payment->autoAddLoanSettlements($current_month);
        $success = $count > 0 ? "Added settlements for $count loans." : "No new settlements added.";
        $loan_payments = $payment->getPaymentsByType('Loan Settlement');
    } catch (Exception $e) {
        $error = "Error adding loan settlements.";
        error_log("Auto-add settlements error: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add'])) {
            $member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
            $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
            $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
            $payment_mode = filter_input(INPUT_POST, 'payment_mode', FILTER_SANITIZE_STRING);
            $payment_type = filter_input(INPUT_POST, 'payment_type', FILTER_SANITIZE_STRING);
            $receipt_number = filter_input(INPUT_POST, 'receipt_number', FILTER_SANITIZE_STRING) ?: null;
            $remarks = filter_input(INPUT_POST, 'monthly_contribution', FILTER_SANITIZE_STRING) ?: null;
            $loan_id = ($payment_type === 'Loan Settlement' && !empty($_POST['loan_id'])) ? filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT) : null;

            if (!$member_id || $amount <= 0 || !$date || !$payment_mode || !$payment_type) {
                $error = "Please fill all required fields correctly.";
            } elseif ($payment_type === 'Loan Settlement' && !$loan_id) {
                $error = "Please select a loan for settlement.";
            } else {
                if ($payment->addPayment($member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id)) {
                    $success = "Payment added successfully!";
                    $society_payments = $payment->getPaymentsByType('Society Issued');
                    $membership_payments = $payment->getPaymentsByType('Membership Fee');
                    $loan_payments = $payment->getPaymentsByType('Loan Settlement');
                } else {
                    $error = "Failed to add payment.";
                    error_log("Add payment failed: member_id=$member_id, type=$payment_type");
                }
            }
        } elseif (isset($_POST['update'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
            $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
            $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
            $payment_mode = filter_input(INPUT_POST, 'payment_mode', FILTER_SANITIZE_STRING);
            $payment_type = filter_input(INPUT_POST, 'payment_type', FILTER_SANITIZE_STRING);
            $receipt_number = filter_input(INPUT_POST, 'receipt_number', FILTER_SANITIZE_STRING) ?: null;
            $remarks = filter_input(INPUT_POST, 'monthly_contribution', FILTER_SANITIZE_STRING) ?: null;
            $loan_id = ($payment_type === 'Loan Settlement' && !empty($_POST['loan_id'])) ? filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT) : null;

            if (!$id || !$member_id || $amount <= 0 || !$date || !$payment_mode || !$payment_type) {
                $error = "Please fill all required fields correctly.";
            } elseif ($payment_type === 'Loan Settlement' && !$loan_id) {
                $error = "Please select a loan for settlement.";
            } else {
                if ($payment->updatePayment($id, $member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id)) {
                    $success = "Payment updated successfully!";
                    $society_payments = $payment->getPaymentsByType('Society Issued');
                    $membership_payments = $payment->getPaymentsByType('Membership Fee');
                    $loan_payments = $payment->getPaymentsByType('Loan Settlement');
                } else {
                    $error = "Failed to update payment or payment is confirmed.";
                    error_log("Update payment failed: id=$id");
                }
            }
        } elseif (isset($_POST['delete'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                $error = "Invalid payment ID.";
            } else {
                if ($payment->deletePayment($id)) {
                    $success = "Payment deleted successfully!";
                    $society_payments = $payment->getPaymentsByType('Society Issued');
                    $membership_payments = $payment->getPaymentsByType('Membership Fee');
                    $loan_payments = $payment->getPaymentsByType('Loan Settlement');
                } else {
                    $error = "Failed to delete payment or payment is confirmed.";
                    error_log("Delete payment failed: id=$id");
                }
            }
        } elseif (isset($_POST['confirm'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                $error = "Invalid payment ID.";
            } else {
                if ($payment->confirmPayment($id)) {
                    $success = "Payment confirmed!";
                    $society_payments = $payment->getPaymentsByType('Society Issued');
                    $membership_payments = $payment->getPaymentsByType('Membership Fee');
                    $loan_payments = $payment->getPaymentsByType('Loan Settlement');
                } else {
                    $error = "Failed to confirm payment or already confirmed.";
                    error_log("Confirm payment failed: id=$id");
                }
            }
        }
    } catch (Exception $e) {
        $error = "An unexpected error occurred.";
        error_log("POST error: " . $e->getMessage());
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
            --primary-orange: #F97316;
            --orange-dark: #C2410C;
            --orange-light: #FFF7ED;
            --gray-bg: #F9FAFB;
            --card-bg: #FFFFFF;
            --text-primary: #1F2937;
            --text-secondary: #6B7280;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
        }
        [data-theme="dark"] {
            --primary-orange: #FB923C;
            --orange-dark: #EA580C;
            --orange-light: #431407;
            --gray-bg: #111827;
            --card-bg: #1F2937;
            --text-primary: #F9FAFB;
            --text-secondary: #9CA3AF;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }
        body {
            background-color: var(--gray-bg);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--shadow);
            border-radius: var(--border-radius);
            padding: 1.5rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }
        .btn-primary {
            background-color: var(--primary-orange);
            color: white;
        }
        .btn-primary:hover {
            background-color: var(--orange-dark);
        }
        .btn-danger {
            background-color: #EF4444;
            color: white;
        }
        .btn-danger:hover {
            background-color: #DC2626;
        }
        .btn-success {
            background-color: #10B981;
            color: white;
        }
        .btn-success:hover {
            background-color: #059669;
        }
        .btn-icon {
            padding: 0.5rem;
            width: 2.5rem;
            height: 2.5rem;
            justify-content: center;
        }
        .btn-loading::after {
            content: '';
            width: 1rem;
            height: 1rem;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            position: absolute;
            right: 0.5rem;
        }
        .input-field {
            border: 1px solid var(--text-secondary);
            border-radius: var(--border-radius);
            padding: 0.75rem;
            width: 100%;
            background-color: var(--card-bg);
            transition: border-color 0.2s ease;
        }
        .input-field:focus {
            border-color: var(--primary-orange);
            outline: none;
        }
        .input-field:invalid:not(:placeholder-shown) {
            border-color: #EF4444;
        }
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: var(--card-bg);
        }
        .table th, .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--text-secondary);
            text-align: left;
        }
        .table thead th {
            background-color: var(--primary-orange);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .table tbody tr:nth-child(even) {
            background-color: var(--orange-light);
        }
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 9999px;
            color: var(--text-primary);
            font-weight: 500;
            background-color: var(--card-bg);
            transition: all 0.2s ease;
        }
        .tab-btn.active {
            background-color: var(--primary-orange);
            color: white;
        }
        .tab-btn:hover:not(.active) {
            background-color: var(--orange-light);
        }
        .alert {
            border-radius: var(--border-radius);
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slide-in 0.5s ease-out;
            margin-bottom: 1rem;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            animation: modal-slide-in 0.3s ease-out;
        }
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.25rem;
            cursor: pointer;
            color: var(--text-secondary);
        }
        .modal-close:hover {
            color: var(--primary-orange);
        }
        .tooltip {
            position: relative;
        }
        .tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #1F2937;
            color: white;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
        }
        .tooltip:hover::after {
            opacity: 1;
            visibility: visible;
            bottom: 120%;
        }
        @media (max-width: 640px) {
            .card {
                padding: 1rem;
            }
            .table th, .table td {
                padding: 0.5rem;
                font-size: 0.875rem;
            }
            .btn {
                padding: 0.5rem 1rem;
            }
            .tab-btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
        }
        @keyframes slide-in {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes modal-slide-in {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidepanel.php'; ?>

<main class="p-6 sm:p-8 max-w-7xl mx-auto">
    <h1 class="text-3xl font-bold text-[var(--primary-orange)] mb-6">Manage Payments</h1>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2 mb-6">
        <button class="tab-btn <?php echo !isset($_GET['tab']) || $_GET['tab'] === 'add' ? 'active' : ''; ?>" onclick="showTab('add')">Add Payment</button>
        <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'society' ? 'active' : ''; ?>" onclick="showTab('society')">Society Issued</button>
        <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'membership' ? 'active' : ''; ?>" onclick="showTab('membership')">Membership Fees</button>
        <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'loan' ? 'active' : ''; ?>" onclick="showTab('loan')">Loan Settlements</button>
    </div>

    <?php if ($error): ?>
        <div class="alert bg-red-50 text-red-600">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert bg-green-50 text-green-600">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
    <?php endif; ?>

    <!-- Add Payment -->
    <div id="tab-add" class="card <?php echo isset($_GET['tab']) && $_GET['tab'] !== 'add' ? 'hidden' : ''; ?>">
        <h2 class="text-xl font-semibold mb-4">Add Payment</h2>
        <form method="POST" class="space-y-6" id="add-payment-form">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Member <span class="text-red-500">*</span></label>
                    <select name="member_id" class="input-field" required aria-required="true" onchange="updateLoans()">
                        <option value="" disabled selected>Select Member</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Amount (LKR) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01" class="input-field" required aria-required="true" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="date" class="input-field" required aria-required="true" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Payment Mode <span class="text-red-500">*</span></label>
                    <select name="payment_mode" class="input-field" required aria-required="true">
                        <option value="" disabled selected>Select Mode</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Payment Type <span class="text-red-500">*</span></label>
                    <select name="payment_type" class="input-field" required aria-required="true" onchange="toggleLoanSection()">
                        <option value="" disabled selected>Select Type</option>
                        <option value="Society Issued">Society Issued</option>
                        <option value="Membership Fee">Membership Fee</option>
                        <option value="Loan Settlement">Loan Settlement</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Receipt Number</label>
                    <input type="text" name="receipt_number" class="input-field" placeholder="e.g., R12345">
                </div>
                <div id="loan-section" class="sm:col-span-2 hidden">
                    <label class="block text-sm font-medium mb-1">Select Loan <span class="text-red-500">*</span></label>
                    <select name="loan_id" class="input-field" aria-required="true">
                        <option value="" disabled selected>Select a Loan</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Remarks</label>
                    <textarea name="monthly_contribution" class="input-field" rows="3" placeholder="Additional details..."></textarea>
                </div>
            </div>
            <div class="flex gap-4">
                <button type="submit" name="add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Payment
                </button>
                <button type="reset" class="btn btn-danger" onclick="toggleLoanSection()">Reset</button>
            </div>
        </form>
    </div>

    <!-- Society Issued -->
    <div id="tab-society" class="card <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'society' ? 'hidden' : ''; ?>">
        <h2 class="text-xl font-semibold mb-4">Society Issued Payments</h2>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Mode</th>
                        <th>Receipt</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Confirmed By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($society_payments as $p): ?>
                        <?php $m = $member->getMemberById($p['member_id']); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['member_id'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($p['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="px-2 py-1 rounded-full text-sm <?php echo $p['is_confirmed'] ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                                    <?php echo $p['is_confirmed'] ? 'Confirmed' : 'Pending'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($p['confirmed_by'] ?? 'N/A'); ?></td>
                            <td class="flex gap-2">
                                <?php if (!$p['is_confirmed']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="confirm" class="btn btn-success btn-icon tooltip" data-tooltip="Confirm Payment" onclick="return confirm('Confirm this payment?');">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-primary btn-icon tooltip" data-tooltip="Edit Payment" onclick="showEditModal(<?php echo json_encode($p, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-icon tooltip" data-tooltip="Delete Payment" onclick="return confirm('Delete this payment?');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($society_payments)): ?>
                        <tr><td colspan="9" class="text-center py-4">No payments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Membership Fees -->
    <div id="tab-membership" class="card <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'membership' ? 'hidden' : ''; ?>">
        <div class="flex justify-between mb-4">
            <h2 class="text-xl font-semibold">Membership Fees</h2>
            <a href="?tab=membership&auto_add_fees=1" class="btn btn-primary"><i class="fas fa-plus"></i> Auto-Add Fees</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Mode</th>
                        <th>Receipt</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Confirmed By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($membership_payments as $p): ?>
                        <?php $m = $member->getMemberById($p['member_id']); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['member_id'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($p['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="px-2 py-1 rounded-full text-sm <?php echo $p['is_confirmed'] ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                                    <?php echo $p['is_confirmed'] ? 'Confirmed' : 'Pending'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($p['confirmed_by'] ?? 'N/A'); ?></td>
                            <td class="flex gap-2">
                                <?php if (!$p['is_confirmed']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="confirm" class="btn btn-success btn-icon tooltip" data-tooltip="Confirm Payment" onclick="return confirm('Confirm this payment?');">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-primary btn-icon tooltip" data-tooltip="Edit Payment" onclick="showEditModal(<?php echo json_encode($p, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-icon tooltip" data-tooltip="Delete Payment" onclick="return confirm('Delete this payment?');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($membership_payments)): ?>
                        <tr><td colspan="9" class="text-center py-4">No payments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Loan Settlements -->
    <div id="tab-loan" class="card <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'loan' ? 'hidden' : ''; ?>">
        <div class="flex justify-between mb-4">
            <h2 class="text-xl font-semibold">Loan Settlements</h2>
            <a href="?tab=loan&auto_add_loan_settlements=1" class="btn btn-primary"><i class="fas fa-plus"></i> Auto-Add Settlements</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Loan ID</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Mode</th>
                        <th>Receipt</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Confirmed By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loan_payments as $p): ?>
                        <?php $m = $member->getMemberById($p['member_id']); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['member_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['loan_id'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($p['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="px-2 py-1 rounded-full text-sm <?php echo $p['is_confirmed'] ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                                    <?php echo $p['is_confirmed'] ? 'Confirmed' : 'Pending'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($p['confirmed_by'] ?? 'N/A'); ?></td>
                            <td class="flex gap-2">
                                <?php if (!$p['is_confirmed']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="confirm" class="btn btn-success btn-icon tooltip" data-tooltip="Confirm Payment" onclick="return confirm('Confirm this payment?');">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-primary btn-icon tooltip" data-tooltip="Edit Payment" onclick="showEditModal(<?php echo json_encode($p, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-icon tooltip" data-tooltip="Delete Payment" onclick="return confirm('Delete this payment?');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($loan_payments)): ?>
                        <tr><td colspan="10" class="text-center py-4">No payments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeEditModal()">×</span>
            <h2 class="text-xl font-semibold mb-4">Edit Payment</h2>
            <form method="POST" class="space-y-6" id="edit-payment-form">
                <input type="hidden" name="id" id="edit-id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Member <span class="text-red-500">*</span></label>
                        <select name="member_id" id="edit-member_id" class="input-field" required aria-required="true" onchange="updateEditLoans()">
                            <option value="" disabled>Select Member</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Amount (LKR) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" id="edit-amount" step="0.01" min="0.01" class="input-field" required aria-required="true">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="date" id="edit-date" class="input-field" required aria-required="true">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Payment Mode <span class="text-red-500">*</span></label>
                        <select name="payment_mode" id="edit-payment_mode" class="input-field" required aria-required="true">
                            <option value="" disabled>Select Mode</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Payment Type <span class="text-red-500">*</span></label>
                        <select name="payment_type" id="edit-payment_type" class="input-field" required aria-required="true" onchange="toggleEditLoanSection()">
                            <option value="" disabled>Select Type</option>
                            <option value="Society Issued">Society Issued</option>
                            <option value="Membership Fee">Membership Fee</option>
                            <option value="Loan Settlement">Loan Settlement</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Receipt Number</label>
                        <input type="text" name="receipt_number" id="edit-receipt_number" class="input-field" placeholder="e.g., R12345">
                    </div>
                    <div id="edit-loan-section" class="sm:col-span-2 hidden">
                        <label class="block text-sm font-medium mb-1">Select Loan <span class="text-red-500">*</span></label>
                        <select name="loan_id" id="edit-loan_id" class="input-field" aria-required="true">
                            <option value="" disabled>Select a Loan</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium mb-1">Remarks</label>
                        <textarea name="monthly_contribution" id="edit-remarks" class="input-field" rows="3"></textarea>
                    </div>
                </div>
                <div class="flex gap-4">
                    <button type="submit" name="update" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update
                    </button>
                    <button type="button" class="btn btn-danger" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>

<script>
    function showTab(tab) {
        document.querySelectorAll('.card').forEach(c => c.classList.add('hidden'));
        document.getElementById(`tab-${tab}`).classList.remove('hidden');
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelector(`button[onclick="showTab('${tab}')"]`).classList.add('active');
        window.history.replaceState({}, '', `?tab=${tab}`);
    }

    function toggleLoanSection() {
        const type = document.querySelector('#add-payment-form [name="payment_type"]').value;
        const section = document.getElementById('loan-section');
        const select = section.querySelector('select');
        section.classList.toggle('hidden', type !== 'Loan Settlement');
        select.required = type === 'Loan Settlement';
        if (type === 'Loan Settlement') updateLoans();
        else select.innerHTML = '<option value="" disabled selected>Select a Loan</option>';
    }

    async function updateLoans() {
        const memberId = document.querySelector('#add-payment-form [name="member_id"]').value;
        const dropdown = document.querySelector('#add-payment-form [name="loan_id"]');
        dropdown.innerHTML = '<option value="" disabled selected>Loading...</option>';

        if (!memberId || isNaN(memberId)) {
            dropdown.innerHTML = '<option value="" disabled selected>No member selected</option>';
            return;
        }

        try {
            const response = await fetch(`/maranadara-society/get_loans.php?member_id=${encodeURIComponent(memberId)}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            const data = await response.json();
            dropdown.innerHTML = '<option value="" disabled selected>Select a Loan</option>';

            if (data.status !== 'success') {
                throw new Error(data.message || 'Invalid response format');
            }

            if (!data.data || data.data.length === 0) {
                dropdown.innerHTML += '<option value="" disabled>No active loans available</option>';
            } else {
                data.data.forEach(loan => {
                    dropdown.innerHTML += `<option value="${loan.id}">Loan #${loan.id} - LKR ${Number(loan.amount).toFixed(2)} (Monthly: ${Number(loan.monthly_payment).toFixed(2)})</option>`;
                });
            }
        } catch (error) {
            console.error('Loan fetch error:', error.message);
            dropdown.innerHTML = '<option value="" disabled>No active loans available</option>';
        }
    }

    function showEditModal(payment) {
        document.getElementById('edit-id').value = payment.id;
        document.getElementById('edit-member_id').value = payment.member_id;
        document.getElementById('edit-amount').value = payment.amount;
        document.getElementById('edit-date').value = payment.date;
        document.getElementById('edit-payment_mode').value = payment.payment_mode;
        document.getElementById('edit-payment_type').value = payment.payment_type;
        document.getElementById('edit-receipt_number').value = payment.receipt_number || '';
        document.getElementById('edit-remarks').value = payment.remarks || '';
        const loanSection = document.getElementById('edit-loan-section');
        const select = loanSection.querySelector('select');
        loanSection.classList.toggle('hidden', payment.payment_type !== 'Loan Settlement');
        select.required = payment.payment_type === 'Loan Settlement';
        if (payment.payment_type === 'Loan Settlement') {
            updateEditLoans(payment.loan_id);
        }
        document.getElementById('edit-modal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('edit-modal').style.display = 'none';
    }

    function toggleEditLoanSection() {
        const type = document.getElementById('edit-payment_type').value;
        const section = document.getElementById('edit-loan-section');
        const select = section.querySelector('select');
        section.classList.toggle('hidden', type !== 'Loan Settlement');
        select.required = type === 'Loan Settlement';
        if (type === 'Loan Settlement') updateEditLoans();
        else select.innerHTML = '<option value="" disabled selected>Select a Loan</option>';
    }

    async function updateEditLoans(selectedId = null) {
        const memberId = document.getElementById('edit-member_id').value;
        const dropdown = document.getElementById('edit-loan_id');
        dropdown.innerHTML = '<option value="" disabled selected>Loading...</option>';

        if (!memberId || isNaN(memberId)) {
            dropdown.innerHTML = '<option value="" disabled selected>No member selected</option>';
            return;
        }

        try {
            const response = await fetch(`/maranadara-society/get_loans.php?member_id=${encodeURIComponent(memberId)}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            const data = await response.json();
            dropdown.innerHTML = '<option value="" disabled selected>Select a Loan</option>';

            if (data.status !== 'success') {
                throw new Error(data.message || 'Invalid response format');
            }

            if (!data.data || data.data.length === 0) {
                dropdown.innerHTML += '<option value="" disabled>No active loans available</option>';
            } else {
                data.data.forEach(loan => {
                    const selected = loan.id == selectedId ? ' selected' : '';
                    dropdown.innerHTML += `<option value="${loan.id}"${selected}>Loan #${loan.id} - LKR ${Number(loan.amount).toFixed(2)} (Monthly: ${Number(loan.monthly_payment).toFixed(2)})</option>`;
                });
            }
        } catch (error) {
            console.error('Loan fetch error:', error.message);
            dropdown.innerHTML = '<option value="" disabled>No active loans available</option>';
        }
    }

    document.getElementById('add-payment-form')?.addEventListener('submit', (e) => {
        const button = e.target.querySelector('button[name="add"]');
        button.disabled = true;
        button.classList.add('btn-loading');
        setTimeout(() => {
            button.disabled = false;
            button.classList.remove('btn-loading');
        }, 1000);
    });

    document.getElementById('edit-payment-form')?.addEventListener('submit', (e) => {
        const button = e.target.querySelector('button[name="update"]');
        button.disabled = true;
        button.classList.add('btn-loading');
        setTimeout(() => {
            button.disabled = false;
            button.classList.remove('btn-loading');
        }, 1000);
    });

    // Theme toggle (if needed)
    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        document.body.dataset.theme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
    });
</script>
</body>
</html>