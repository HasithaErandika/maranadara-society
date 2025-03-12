<?php
define('APP_START', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/header.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    error_log("Redirecting to login.php - Session role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'not set'));
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
    $count = $payment->autoAddMembershipFees($current_month);
    if ($count > 0) {
        $success = "Automatically added membership fees for $count active members.";
        $membership_payments = $payment->getPaymentsByType('Membership Fee');
    } else {
        $success = "No new membership fees added (already exist or no active members).";
    }
}

if (isset($_GET['auto_add_loan_settlements']) && isset($_GET['tab']) && $_GET['tab'] === 'loan') {
    $count = $payment->autoAddLoanSettlements($current_month);
    if ($count > 0) {
        $success = "Automatically added loan settlement payments for $count pending loans.";
        $loan_payments = $payment->getPaymentsByType('Loan Settlement');
    } else {
        $success = "No new loan settlement payments added (already exist or no pending loans).";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $member_id = $_POST['member_id'];
        $amount = floatval($_POST['amount']);
        $date = $_POST['date'];
        $payment_mode = $_POST['payment_mode'];
        $payment_type = $_POST['payment_type'];
        $receipt_number = $_POST['receipt_number'] ?: null;
        $remarks = $_POST['monthly_contribution'] ?: null;
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
                $error = "Error adding payment. Check server logs for details.";
            }
        }
    } elseif (isset($_POST['update'])) {
        $id = $_POST['id'];
        $member_id = $_POST['member_id'];
        $amount = floatval($_POST['amount']);
        $date = $_POST['date'];
        $payment_mode = $_POST['payment_mode'];
        $payment_type = $_POST['payment_type'];
        $receipt_number = $_POST['receipt_number'] ?: null;
        $remarks = $_POST['monthly_contribution'] ?: null;
        $loan_id = ($payment_type === 'Loan Settlement' && isset($_POST['loan_id']) && !empty($_POST['loan_id'])) ? intval($_POST['loan_id']) : null;

        if ($payment->updatePayment($id, $member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id)) {
            $success = "Payment updated successfully!";
            $society_payments = $payment->getPaymentsByType('Society Issued');
            $membership_payments = $payment->getPaymentsByType('Membership Fee');
            $loan_payments = $payment->getPaymentsByType('Loan Settlement');
        } else {
            $error = "Error updating payment or payment is already confirmed.";
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
            $success = "Payment confirmed successfully by " . htmlspecialchars($_SESSION['user']) . "!";
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
            --primary-orange: #F97316;
            --orange-dark: #C2410C;
            --orange-light: #FED7AA;
            --gray-bg: #F9FAFB;
            --card-bg: #FFFFFF;
            --text-primary: #111827;
            --text-secondary: #6B7280;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --sidebar-expanded: 240px;
            --border-radius: 12px;
        }
        [data-theme="dark"] {
            --primary-orange: #FB923C;
            --orange-dark: #EA580C;
            --orange-light: #FDBA74;
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
            overflow-x: hidden;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--shadow);
            border-radius: var(--border-radius);
            padding: 2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary {
            background-color: var(--primary-orange);
            color: white;
        }
        .btn-primary:hover {
            background-color: var(--orange-dark);
            box-shadow: 0 6px 12px rgba(249, 115, 22, 0.3);
            transform: translateY(-2px);
        }
        .btn-danger {
            background-color: #DC2626;
            color: white;
        }
        .btn-danger:hover {
            background-color: #B91C1C;
            box-shadow: 0 6px 12px rgba(220, 38, 38, 0.3);
            transform: translateY(-2px);
        }
        .btn-success {
            background-color: #10B981;
            color: white;
        }
        .btn-success:hover {
            background-color: #059669;
            box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3);
            transform: translateY(-2px);
        }
        .btn-icon {
            padding: 0.5rem;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .input-field {
            border: 1px solid var(--text-secondary);
            border-radius: 8px;
            padding: 0.75rem;
            width: 100%;
            transition: all 0.3s ease;
            background-color: var(--card-bg);
        }
        .input-field:focus {
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px var(--orange-light);
            outline: none;
        }
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        .table th, .table td {
            padding: 1.25rem;
            border-bottom: 1px solid var(--text-secondary);
            text-align: left;
        }
        .table thead th {
            background-color: var(--primary-orange);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.05em;
        }
        .table tbody tr {
            transition: all 0.3s ease;
        }
        .table tbody tr:hover {
            background-color: var(--orange-light);
            transform: scale(1.005);
        }
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-weight: 500;
            background-color: var(--card-bg);
            box-shadow: var(--shadow);
        }
        .tab-btn.active {
            background-color: var(--primary-orange);
            color: white;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        }
        .tab-btn:hover:not(.active) {
            background-color: var(--orange-light);
            transform: translateY(-2px);
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        .status-confirmed {
            background-color: #D1FAE5;
            color: #10B981;
        }
        .status-pending {
            background-color: #FEE2E2;
            color: #EF4444;
        }
        .main-content {
            margin-left: calc(var(--sidebar-expanded) + 32px);
            transition: margin-left 0.3s ease;
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
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 20;
        }
        .tooltip:hover::after {
            opacity: 1;
            visibility: visible;
            bottom: 130%;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .alert {
            border-radius: var(--border-radius);
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow);
            animation: slide-in 0.5s ease-out;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow);
            position: relative;
            animation: modal-slide-in 0.3s ease-out;
        }
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .modal-close:hover {
            color: var(--primary-orange);
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 16px;
            }
            .tab-btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
            .table th, .table td {
                padding: 0.75rem;
                font-size: 0.85rem;
            }
            .btn {
                padding: 0.5rem 1rem;
            }
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }
        }
        @keyframes slide-in {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes modal-slide-in {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidepanel.php'; ?>

<main class="flex-1 p-8 main-content pt-20">
    <div class="max-w-7xl mx-auto">
        <div class="header-section">
            <h1 class="text-4xl font-bold text-[var(--primary-orange)] tracking-tight">Manage Payments</h1>
        </div>

        <!-- Tabs -->
        <div class="flex flex-wrap gap-4 mb-8 justify-center">
            <button class="tab-btn <?php echo !isset($_GET['tab']) || $_GET['tab'] === 'add' ? 'active' : ''; ?>" onclick="showTab('add')">Add Payment</button>
            <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'society' ? 'active' : ''; ?>" onclick="showTab('society')">Society Issued</button>
            <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'membership' ? 'active' : ''; ?>" onclick="showTab('membership')">Membership Fees</button>
            <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'loan' ? 'active' : ''; ?>" onclick="showTab('loan')">Loan Settlements</button>
        </div>

        <?php if ($error): ?>
            <div class="alert bg-red-50 text-red-600 mb-6">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert bg-green-50 text-green-600 mb-6">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <!-- Add Payment Tab -->
        <div id="tab-add" class="card <?php echo isset($_GET['tab']) && $_GET['tab'] !== 'add' ? 'hidden' : ''; ?>">
            <h2 class="text-2xl font-semibold mb-6 text-[var(--primary-orange)]">Add New Payment</h2>
            <form method="POST" class="space-y-8" id="add-payment-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Member <span class="text-red-500">*</span></label>
                        <select name="member_id" class="input-field" required onchange="updateLoans()">
                            <option value="" disabled selected>Select Member</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Amount (LKR) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" step="0.01" class="input-field" required min="0.01" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="date" class="input-field" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Payment Mode <span class="text-red-500">*</span></label>
                        <select name="payment_mode" class="input-field" required>
                            <option value="" disabled selected>Select Mode</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Payment Type <span class="text-red-500">*</span></label>
                        <select name="payment_type" class="input-field" required onchange="toggleLoanSection()">
                            <option value="" disabled selected>Select Type</option>
                            <option value="Society Issued">Society Issued</option>
                            <option value="Membership Fee">Membership Fee</option>
                            <option value="Loan Settlement">Loan Settlement</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Receipt Number (Optional)</label>
                        <input type="text" name="receipt_number" class="input-field" placeholder="e.g., R12345">
                    </div>
                    <div id="loan-section" class="md:col-span-2 hidden">
                        <label class="block text-sm font-medium mb-2">Select Loan <span class="text-red-500">*</span></label>
                        <select name="loan_id" class="input-field" required>
                            <option value="" disabled selected>Select a Loan</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">Remarks (Optional)</label>
                        <textarea name="monthly_contribution" class="input-field" rows="4" placeholder="Additional payment details..."></textarea>
                    </div>
                </div>
                <div class="flex justify-center gap-6">
                    <button type="submit" name="add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Payment
                    </button>
                    <button type="reset" class="btn btn-danger" onclick="toggleLoanSection()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- Society Issued Payments Tab -->
        <div id="tab-society" class="card <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'society' ? 'hidden' : ''; ?>">
            <h2 class="text-2xl font-semibold mb-6 text-[var(--primary-orange)]">Society Issued Payments</h2>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Amount (LKR)</th>
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
                            <td><?php echo htmlspecialchars($m['member_id']); ?></td>
                            <td><?php echo number_format($p['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($p['is_confirmed']): ?>
                                    <span class="status-badge status-confirmed"><i class="fas fa-check"></i> Confirmed</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($p['confirmed_by'] ?? 'N/A'); ?></td>
                            <td class="flex gap-3">
                                <?php if (!$p['is_confirmed']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="confirm" class="btn btn-success btn-icon tooltip" data-tooltip="Confirm Payment" onclick="return confirm('Confirm this payment?');">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-primary btn-icon tooltip" data-tooltip="Edit Payment" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-icon tooltip" data-tooltip="Delete Payment" onclick="return confirm('Are you sure you want to delete this payment?');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($society_payments)): ?>
                        <tr><td colspan="9" class="py-6 text-center text-[var(--text-secondary)]">No society payments recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Membership Fee Payments Tab -->
        <div id="tab-membership" class="card <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'membership' ? 'hidden' : ''; ?>">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-[var(--primary-orange)]">Membership Fee Payments</h2>
                <a href="?tab=membership&auto_add_fees=1" class="btn btn-primary tooltip" data-tooltip="Add fees for all active members">
                    <i class="fas fa-plus"></i> Auto-Add Fees
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Amount (LKR)</th>
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
                            <td><?php echo htmlspecialchars($m['member_id']); ?></td>
                            <td><?php echo number_format($p['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($p['is_confirmed']): ?>
                                    <span class="status-badge status-confirmed"><i class="fas fa-check"></i> Confirmed</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($p['confirmed_by'] ?? 'N/A'); ?></td>
                            <td class="flex gap-3">
                                <?php if (!$p['is_confirmed']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="confirm" class="btn btn-success btn-icon tooltip" data-tooltip="Confirm Payment" onclick="return confirm('Confirm this payment?');">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-primary btn-icon tooltip" data-tooltip="Edit Payment" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-icon tooltip" data-tooltip="Delete Payment" onclick="return confirm('Are you sure you want to delete this payment?');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($membership_payments)): ?>
                        <tr><td colspan="9" class="py-6 text-center text-[var(--text-secondary)]">No membership fee payments recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Loan Settlement Payments Tab -->
        <div id="tab-loan" class="card <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'loan' ? 'hidden' : ''; ?>">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-[var(--primary-orange)]">Loan Settlement Payments</h2>
                <a href="?tab=loan&auto_add_loan_settlements=1" class="btn btn-primary tooltip" data-tooltip="Add monthly settlements for pending loans">
                    <i class="fas fa-plus"></i> Auto-Add Settlements
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
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
                        <th>Confirmed By</th>
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
                                    <span class="status-badge status-confirmed"><i class="fas fa-check"></i> Confirmed</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($p['confirmed_by'] ?? 'N/A'); ?></td>
                            <td class="flex gap-3">
                                <?php if (!$p['is_confirmed']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="confirm" class="btn btn-success btn-icon tooltip" data-tooltip="Confirm Payment" onclick="return confirm('Confirm this payment?');">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-primary btn-icon tooltip" data-tooltip="Edit Payment" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-icon tooltip" data-tooltip="Delete Payment" onclick="return confirm('Are you sure you want to delete this payment?');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($loan_payments)): ?>
                        <tr><td colspan="10" class="py-6 text-center text-[var(--text-secondary)]">No loan settlement payments recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Payment Modal -->
        <div id="edit-modal" class="modal">
            <div class="modal-content">
                <span class="modal-close" onclick="closeEditModal()">×</span>
                <h2 class="text-2xl font-semibold mb-6 text-[var(--primary-orange)]">Edit Payment</h2>
                <form method="POST" class="space-y-8" id="edit-payment-form">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium mb-2">Member <span class="text-red-500">*</span></label>
                            <select name="member_id" id="edit-member_id" class="input-field" required onchange="updateEditLoans()">
                                <option value="" disabled>Select Member</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Amount (LKR) <span class="text-red-500">*</span></label>
                            <input type="number" name="amount" id="edit-amount" step="0.01" class="input-field" required min="0.01" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Date <span class="text-red-500">*</span></label>
                            <input type="date" name="date" id="edit-date" class="input-field" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Payment Mode <span class="text-red-500">*</span></label>
                            <select name="payment_mode" id="edit-payment_mode" class="input-field" required>
                                <option value="" disabled>Select Mode</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Payment Type <span class="text-red-500">*</span></label>
                            <select name="payment_type" id="edit-payment_type" class="input-field" required onchange="toggleEditLoanSection()">
                                <option value="" disabled>Select Type</option>
                                <option value="Society Issued">Society Issued</option>
                                <option value="Membership Fee">Membership Fee</option>
                                <option value="Loan Settlement">Loan Settlement</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Receipt Number (Optional)</label>
                            <input type="text" name="receipt_number" id стратег

                            "edit-receipt_number" class="input-field" placeholder="e.g., R12345">
                        </div>
                        <div id="edit-loan-section" class="md:col-span-2 hidden">
                            <label class="block text-sm font-medium mb-2">Select Loan <span class="text-red-500">*</span></label>
                            <select name="loan_id" id="edit-loan_id" class="input-field">
                                <option value="" disabled>Select a Loan</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">Remarks (Optional)</label>
                            <textarea name="monthly_contribution" id="edit-remarks" class="input-field" rows="4" placeholder="Additional payment details..."></textarea>
                        </div>
                    </div>
                    <div class="flex justify-center gap-6">
                        <button type="submit" name="update" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Payment
                        </button>
                        <button type="button" class="btn btn-danger" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <p class="text-center mt-8"><a href="dashboard.php" class="text-[var(--primary-orange)] hover:underline font-semibold">Back to Dashboard</a></p>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>

<script>
    const themeToggle = document.getElementById('theme-toggle');
    const addForm = document.getElementById('add-payment-form');
    const editForm = document.getElementById('edit-payment-form');
    const editModal = document.getElementById('edit-modal');

    themeToggle?.addEventListener('click', () => {
        document.body.dataset.theme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
        themeToggle.querySelector('i').classList.toggle('fa-moon');
        themeToggle.querySelector('i').classList.toggle('fa-sun');
    });

    function showTab(tab) {
        document.querySelectorAll('.card').forEach(card => card.classList.add('hidden'));
        document.getElementById(`tab-${tab}`).classList.remove('hidden');
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`button[onclick="showTab('${tab}')"]`).classList.add('active');
        window.history.pushState({}, '', `?tab=${tab}`);
    }

    function toggleLoanSection() {
        const paymentType = document.querySelector('#add-payment-form [name="payment_type"]').value;
        const loanSection = document.getElementById('loan-section');
        loanSection.classList.toggle('hidden', paymentType !== 'Loan Settlement');
        if (paymentType === 'Loan Settlement') updateLoans();
    }

    function updateLoans() {
        const memberId = document.querySelector('#add-payment-form [name="member_id"]').value;
        const loanDropdown = document.querySelector('#add-payment-form [name="loan_id"]');
        loanDropdown.innerHTML = '<option value="" disabled selected>Loading loans...</option>';

        if (memberId) {
            fetch(`get_loans.php?member_id=${memberId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(loans => {
                    loanDropdown.innerHTML = '<option value="" disabled selected>Select a Loan</option>';
                    if (loans.length > 0) {
                        loans.forEach(loan => {
                            loanDropdown.innerHTML += `<option value="${loan.id}">Loan #${loan.id} - LKR ${Number(loan.amount).toFixed(2)}</option>`;
                        });
                    } else {
                        loanDropdown.innerHTML += '<option value="">No confirmed loans available</option>';
                    }
                })
                .catch(error => {
                    loanDropdown.innerHTML = '<option value="">Error loading loans</option>';
                    console.error('Error fetching loans:', error);
                });
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
        const loanDropdown = document.getElementById('edit-loan_id');
        loanSection.classList.toggle('hidden', payment.payment_type !== 'Loan Settlement');
        if (payment.payment_type === 'Loan Settlement') {
            updateEditLoans(payment.loan_id);
        }
        editModal.style.display = 'flex';
    }

    function closeEditModal() {
        editModal.style.display = 'none';
    }

    function toggleEditLoanSection() {
        const paymentType = document.getElementById('edit-payment_type').value;
        const loanSection = document.getElementById('edit-loan-section');
        loanSection.classList.toggle('hidden', paymentType !== 'Loan Settlement');
        if (paymentType === 'Loan Settlement') updateEditLoans();
    }

    function updateEditLoans(selectedLoanId = null) {
        const memberId = document.getElementById('edit-member_id').value;
        const loanDropdown = document.getElementById('edit-loan_id');
        loanDropdown.innerHTML = '<option value="" disabled selected>Loading loans...</option>';

        if (memberId) {
            fetch(`get_loans.php?member_id=${memberId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(loans => {
                    loanDropdown.innerHTML = '<option value="" disabled selected>Select a Loan</option>';
                    if (loans.length > 0) {
                        loans.forEach(loan => {
                            const selected = loan.id == selectedLoanId ? ' selected' : '';
                            loanDropdown.innerHTML += `<option value="${loan.id}"${selected}>Loan #${loan.id} - LKR ${Number(loan.amount).toFixed(2)}</option>`;
                        });
                    } else {
                        loanDropdown.innerHTML += '<option value="">No confirmed loans available</option>';
                    }
                })
                .catch(error => {
                    loanDropdown.innerHTML = '<option value="">Error loading loans</option>';
                    console.error('Error fetching loans:', error);
                });
        }
    }

    addForm?.addEventListener('input', (e) => {
        const target = e.target;
        if (target.name === 'amount' && target.value <= 0) {
            target.setCustomValidity('Amount must be greater than 0');
        } else {
            target.setCustomValidity('');
        }
    });

    editForm?.addEventListener('input', (e) => {
        const target = e.target;
        if (target.name === 'amount' && target.value <= 0) {
            target.setCustomValidity('Amount must be greater than 0');
        } else {
            target.setCustomValidity('');
        }
    });

    toggleLoanSection();
</script>

<!-- get_loans.php inline script for simplicity -->
<script type="text/php">
<?php
    if (isset($_GET['member_id'])) {
        header('Content-Type: application/json');
        $member_id = intval($_GET['member_id']);
        $loans = $payment->getConfirmedLoansByMemberId($member_id);
        echo json_encode($loans);
        exit;
    }
    ?>
</script>
</body>
</html>