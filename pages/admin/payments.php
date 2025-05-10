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
                    error_log("Add payment failed: member_id=$member_id");
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --primary-orange: #F97316;
            --orange-dark: #C2410C;
            --orange-light: #FFF7ED;
            --gray-bg: #F9FAFB;
            --card-bg: #FFFFFF;
            --text-primary: #1F2937;
            --text-secondary: #6B7280;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --success-green: #2ECC71;
            --error-red: #E74C3C;
            --cancel-gray: #7F8C8D;
        }

        body {
            background-color: var(--gray-bg);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            padding-left: 100px;
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
            box-shadow: 0 15px 25px -5px rgba(0, 0, 0, 0.2);
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
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .input-field:focus {
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
            outline: none;
        }

        .input-field:invalid:not(:placeholder-shown) {
            border-color: #EF4444;
        }

        .input-error {
            color: #EF4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
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

        .table tbody tr:hover {
            background-color: rgba(249, 115, 22, 0.1);
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
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
            animation: modal-slide-in 0.3s ease-out;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
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
            z-index: 10;
        }

        .tooltip:hover::after {
            opacity: 1;
            visibility: visible;
            bottom: 120%;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            background-color: var(--card-bg);
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        .pagination-btn:hover {
            background-color: var(--orange-light);
        }

        .pagination-btn.active {
            background-color: var(--primary-orange);
            color: white;
        }

        .pagination-btn:disabled {
            background-color: var(--text-secondary);
            cursor: not-allowed;
        }

        .search-container {
            position: relative;
            max-width: 300px;
        }

        .search-container i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .search-input {
            padding-left: 2.5rem;
        }

        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
        }

        .popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            text-align: center;
            z-index: 2100;
            width: 90%;
            max-width: 400px;
            animation: modal-slide-in 0.3s ease-out;
        }

        .popup.show {
            display: block;
        }

        .popup-overlay.show {
            display: block;
        }

        .popup i {
            font-size: 3rem;
            margin-bottom: 1.25rem;
        }

        .popup h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.625rem;
        }

        .popup p {
            color: var(--cancel-gray);
            margin-top: 0.625rem;
            font-size: 1rem;
        }

        .popup .countdown {
            margin-top: 1.25rem;
            font-size: 0.8rem;
            color: var(--cancel-gray);
        }

        .popup .countdown span {
            font-weight: 600;
        }

        #success-popup i {
            color: var(--success-green);
        }

        #error-popup i {
            color: var(--error-red);
        }

        #cancel-popup i {
            color: var(--cancel-gray);
        }

        @media (max-width: 640px) {
            .card {
                padding: 1.5rem;
            }

            .table th, .table td {
                padding: 0.75rem;
                font-size: 0.875rem;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .tab-btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .modal-content {
                padding: 1.5rem;
                width: 95%;
            }

            .popup {
                padding: 1.5rem;
                width: 95%;
            }
        }

        @keyframes modal-slide-in {
            from { transform: translate(-50%, -60%); opacity: 0; }
            to { transform: translate(-50%, -50%); opacity: 1; }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidepanel.php'; ?>

<main class="p-6 sm:p-8 max-w-7xl mx-auto pl-[200px] animate__animated animate__fadeIn">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-[var(--primary-orange)]">Manage Payments</h1>
    </div>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2 mb-6 bg-[var(--card-bg)] p-4 rounded-[var(--border-radius)] shadow-sm">
        <button class="tab-btn <?php echo !isset($_GET['tab']) || $_GET['tab'] === 'add' ? 'active' : ''; ?>" onclick="showTab('add')" aria-label="Add Payment">Add Payment</button>
        <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'society' ? 'active' : ''; ?>" onclick="showTab('society')" aria-label="Society Issued Payments">Society Issued</button>
        <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'membership' ? 'active' : ''; ?>" onclick="showTab('membership')" aria-label="Membership Fees">Membership Fees</button>
        <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'loan' ? 'active' : ''; ?>" onclick="showTab('loan')" aria-label="Loan Settlements">Loan Settlements</button>
    </div>

    <!-- Add Payment -->
    <div id="tab-add" class="card <?php echo isset($_GET['tab']) && $_GET['tab'] !== 'add' ? 'hidden' : ''; ?>">
        <h2 class="text-xl font-semibold mb-4">Add Payment</h2>
        <form method="POST" class="space-y-6" id="add-payment-form" novalidate>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-1" for="member_id">Member <span class="text-red-500">*</span></label>
                    <select name="member_id" id="member_id" class="input-field" required aria-required="true" onchange="updateLoans()">
                        <option value="" disabled selected>Select Member</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="input-error hidden" id="member_id-error">Please select a member.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="amount">Amount (LKR) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" id="amount" step="0.01" min="0.01" class="input-field" required aria-required="true" placeholder="0.00">
                    <p class="input-error hidden" id="amount-error">Amount must be greater than 0.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="date">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="date" id="date" class="input-field" required aria-required="true" value="<?php echo date('Y-m-d'); ?>">
                    <p class="input-error hidden" id="date-error">Please select a valid date.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="payment_mode">Payment Mode <span class="text-red-500">*</span></label>
                    <select name="payment_mode" id="payment_mode" class="input-field" required aria-required="true">
                        <option value="" disabled selected>Select Mode</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                    <p class="input-error hidden" id="payment_mode-error">Please select a payment mode.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="payment_type">Payment Type <span class="text-red-500">*</span></label>
                    <select name="payment_type" id="payment_type" class="input-field" required aria-required="true" onchange="toggleLoanSection()">
                        <option value="" disabled selected>Select Type</option>
                        <option value="Society Issued">Society Issued</option>
                        <option value="Membership Fee">Membership Fee</option>
                        <option value="Loan Settlement">Loan Settlement</option>
                    </select>
                    <p class="input-error hidden" id="payment_type-error">Please select a payment type.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="receipt_number">Receipt Number</label>
                    <input type="text" name="receipt_number" id="receipt_number" class="input-field" placeholder="e.g., R12345">
                </div>
                <div id="loan-section" class="sm:col-span-2 hidden">
                    <label class="block text-sm font-medium mb-1" for="loan_id">Select Loan <span class="text-red-500">*</span></label>
                    <select name="loan_id" id="loan_id" class="input-field" aria-required="true">
                        <option value="" disabled selected>Select a Loan</option>
                    </select>
                    <p class="input-error hidden" id="loan_id-error">Please select a loan.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1" for="monthly_contribution">Remarks</label>
                    <textarea name="monthly_contribution" id="monthly_contribution" class="input-field" rows="4" placeholder="Additional details..."></textarea>
                </div>
            </div>
            <div class="flex gap-4 sticky bottom-0 bg-[var(--card-bg)] py-4 -mx-8 px-8">
                <button type="submit" name="add" class="btn btn-primary flex-1">
                    <i class="fas fa-plus"></i> Add Payment
                </button>
                <button type="reset" class="btn btn-danger" onclick="resetForm('add-payment-form')">Reset</button>
            </div>
        </form>
    </div>

    <!-- Society Issued -->
    <div id="tab-society" class="card <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'society' ? 'hidden' : ''; ?>">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Society Issued Payments</h2>
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="society-search" class="input-field search-input" placeholder="Search payments...">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table" id="society-table">
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
                                        <button type="submit" name="confirm" class="btn btn-success btn-icon tooltip" data-tooltip="Confirm Payment" aria-label="Confirm Payment" onclick="return confirm('Confirm this payment?');">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-primary btn-icon tooltip" data-tooltip="Edit Payment" aria-label="Edit Payment" onclick="showEditModal(<?php echo json_encode($p, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-icon tooltip" data-tooltip="Delete Payment" aria-label="Delete Payment" onclick="return confirm('Delete this payment?');">
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
        <div class="pagination" id="society-pagination"></div>
    </div>

    <!-- Membership Fees -->
    <div id="tab-membership" class="card <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'membership' ? 'hidden' : ''; ?>">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Membership Fees</h2>
            <div class="flex gap-2">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="membership-search" class="input-field search-input" placeholder="Search payments...">
                </div>
                <a href="?tab=membership&auto_add_fees=1" class="btn btn-primary" aria-label="Auto-Add Membership Fees">
                    <i class="fas fa-plus"></i> Auto-Add Fees
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table" id="membership-table">
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
                                        <button type="submit" name="confirm" class="btn btn-success btn-icon tooltip" data-tooltip="Confirm Payment" aria-label="Confirm Payment" onclick="return confirm('Confirm this payment?');">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-primary btn-icon tooltip" data-tooltip="Edit Payment" aria-label="Edit Payment" onclick="showEditModal(<?php echo json_encode($p, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-icon tooltip" data-tooltip="Delete Payment" aria-label="Delete Payment" onclick="return confirm('Delete this payment?');">
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
        <div class="pagination" id="membership-pagination"></div>
    </div>

    <!-- Loan Settlements -->
    <div id="tab-loan" class="card <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'loan' ? 'hidden' : ''; ?>">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Loan Settlements</h2>
            <div class="flex gap-2">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="loan-search" class="input-field search-input" placeholder="Search payments...">
                </div>
                <a href="?tab=loan&auto_add_loan_settlements=1" class="btn btn-primary" aria-label="Auto-Add Loan Settlements">
                    <i class="fas fa-plus"></i> Auto-Add Settlements
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table" id="loan-table">
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
                                        <button type="submit" name="confirm" class="btn btn-success btn-icon tooltip" data-tooltip="Confirm Payment" aria-label="Confirm Payment" onclick="return confirm('Confirm this payment?');">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-primary btn-icon tooltip" data-tooltip="Edit Payment" aria-label="Edit Payment" onclick="showEditModal(<?php echo json_encode($p, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-icon tooltip" data-tooltip="Delete Payment" aria-label="Delete Payment" onclick="return confirm('Delete this payment?');">
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
        <div class="pagination" id="loan-pagination"></div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeEditModal()" aria-label="Close modal">×</span>
            <h2 class="text-xl font-semibold mb-4">Edit Payment</h2>
            <form method="POST" class="space-y-6" id="edit-payment-form" novalidate>
                <input type="hidden" name="id" id="edit-id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-1" for="edit-member_id">Member <span class="text-red-500">*</span></label>
                        <select name="member_id" id="edit-member_id" class="input-field" required aria-required="true" onchange="updateEditLoans()">
                            <option value="" disabled>Select Member</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="input-error hidden" id="edit-member_id-error">Please select a member.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" for="edit-amount">Amount (LKR) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" id="edit-amount" step="0.01" min="0.01" class="input-field" required aria-required="true">
                        <p class="input-error hidden" id="edit-amount-error">Amount must be greater than 0.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" for="edit-date">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="date" id="edit-date" class="input-field" required aria-required="true">
                        <p class="input-error hidden" id="edit-date-error">Please select a valid date.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" for="edit-payment_mode">Payment Mode <span class="text-red-500">*</span></label>
                        <select name="payment_mode" id="edit-payment_mode" class="input-field" required aria-required="true">
                            <option value="" disabled>Select Mode</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                        <p class="input-error hidden" id="edit-payment_mode-error">Please select a payment mode.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" for="edit-payment_type">Payment Type <span class="text-red-500">*</span></label>
                        <select name="payment_type" id="edit-payment_type" class="input-field" required aria-required="true" onchange="toggleEditLoanSection()">
                            <option value="" disabled>Select Type</option>
                            <option value="Society Issued">Society Issued</option>
                            <option value="Membership Fee">Membership Fee</option>
                            <option value="Loan Settlement">Loan Settlement</option>
                        </select>
                        <p class="input-error hidden" id="edit-payment_type-error">Please select a payment type.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" for="edit-receipt_number">Receipt Number</label>
                        <input type="text" name="receipt_number" id="edit-receipt_number" class="input-field" placeholder="e.g., R12345">
                    </div>
                    <div id="edit-loan-section" class="sm:col-span-2 hidden">
                        <label class="block text-sm font-medium mb-1" for="edit-loan_id">Select Loan <span class="text-red-500">*</span></label>
                        <select name="loan_id" id="edit-loan_id" class="input-field" aria-required="true">
                            <option value="" disabled>Select a Loan</option>
                        </select>
                        <p class="input-error hidden" id="edit-loan_id-error">Please select a loan.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium mb-1" for="edit-remarks">Remarks</label>
                        <textarea name="monthly_contribution" id="edit-remarks" class="input-field" rows="4"></textarea>
                    </div>
                </div>
                <div class="flex gap-4 sticky bottom-0 bg-[var(--card-bg)] py-4 -mx-8 px-8">
                    <button type="submit" name="update" class="btn btn-primary flex-1">
                        <i class="fas fa-save"></i> Update
                    </button>
                    <button type="button" class="btn btn-danger" onclick="showCancelPopup()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Popups -->
    <div class="popup-overlay" id="popup-overlay"></div>

    <div class="popup" id="success-popup">
        <div>
            <div><i class="ri-checkbox-circle-fill"></i></div>
            <h3>Success!</h3>
            <p id="success-message"></p>
            <div class="countdown">
                Closing in <span id="success-countdown">3</span> seconds...
            </div>
        </div>
    </div>

    <div class="popup" id="error-popup">
        <div>
            <div><i class="ri-error-warning-fill"></i></div>
            <h3>Error</h3>
            <p id="error-message"></p>
            <div class="countdown">
                Closing in <span id="error-countdown">3</span> seconds...
            </div>
        </div>
    </div>

    <div class="popup" id="cancel-popup">
        <div>
            <div><i class="ri-close-circle-fill"></i></div>
            <h3>Cancelled</h3>
            <p>The operation has been cancelled.</p>
            <div class="countdown">
                Closing in <span id="cancel-countdown">3</span> seconds...
            </div>
        </div>
    </div>
</main>

<script>
    // Show Popup
    function showPopup(type, message = '') {
        const overlay = document.getElementById('popup-overlay');
        const popup = document.getElementById(`${type}-popup`);
        const messageElement = document.getElementById(`${type}-message`);
        const countdownElement = document.getElementById(`${type}-countdown`);

        if (messageElement && message) {
            messageElement.textContent = message;
        }
        overlay.classList.add('show');
        popup.classList.add('show');

        let countdown = 3;
        countdownElement.textContent = countdown;
        const interval = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            if (countdown <= 0) {
                clearInterval(interval);
                overlay.classList.remove('show');
                popup.classList.remove('show');
                if (type === 'cancel') {
                    closeEditModal();
                }
            }
        }, 1000);
    }

    // Show Cancel Popup
    function showCancelPopup() {
        showPopup('cancel');
    }

    // PHP-driven Popups
    <?php if ($error): ?>
        document.addEventListener('DOMContentLoaded', () => {
            showPopup('error', "<?php echo htmlspecialchars($error); ?>");
        });
    <?php endif; ?>
    <?php if ($success): ?>
        document.addEventListener('DOMContentLoaded', () => {
            showPopup('success', "<?php echo htmlspecialchars($success); ?>");
        });
    <?php endif; ?>

    // Tab Switching
    function showTab(tab) {
        document.querySelectorAll('.card').forEach(c => {
            c.classList.add('hidden');
            c.classList.remove('animate__animated', 'animate__fadeIn');
        });
        const activeTab = document.getElementById(`tab-${tab}`);
        activeTab.classList.remove('hidden');
        activeTab.classList.add('animate__animated', 'animate__fadeIn');
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelector(`button[onclick="showTab('${tab}')"]`).classList.add('active');
        window.history.replaceState({}, '', `?tab=${tab}`);
    }

    // Form Reset
    function resetForm(formId) {
        const form = document.getElementById(formId);
        form.reset();
        form.querySelectorAll('.input-error').forEach(e => e.classList.add('hidden'));
        toggleLoanSection();
    }

    // Loan Section Toggle
    function toggleLoanSection() {
        const type = document.querySelector('#add-payment-form [name="payment_type"]').value;
        const section = document.getElementById('loan-section');
        const select = section.querySelector('select');
        section.classList.toggle('hidden', type !== 'Loan Settlement');
        select.required = type === 'Loan Settlement';
        if (type === 'Loan Settlement') updateLoans();
        else {
            select.innerHTML = '<option value="" disabled selected>Select a Loan</option>';
            document.getElementById('loan_id-error').classList.add('hidden');
        }
    }

    // Fetch Loans
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

    // Edit Modal
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
        document.getElementById('edit-member_id').focus();
    }

    function closeEditModal() {
        document.getElementById('edit-modal').style.display = 'none';
        document.getElementById('edit-payment-form').querySelectorAll('.input-error').forEach(e => e.classList.add('hidden'));
    }

    function toggleEditLoanSection() {
        const type = document.getElementById('edit-payment_type').value;
        const section = document.getElementById('edit-loan-section');
        const select = section.querySelector('select');
        section.classList.toggle('hidden', type !== 'Loan Settlement');
        select.required = type === 'Loan Settlement';
        if (type === 'Loan Settlement') updateEditLoans();
        else {
            select.innerHTML = '<option value="" disabled selected>Select a Loan</option>';
            document.getElementById('edit-loan_id-error').classList.add('hidden');
        }
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

    // Form Validation
    function validateForm(formId) {
        const form = document.getElementById(formId);
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            const error = document.getElementById(`${field.id}-error`);
            if (!field.value || (field.type === 'number' && field.value <= 0)) {
                error.classList.remove('hidden');
                isValid = false;
            } else {
                error.classList.add('hidden');
            }
        });
        if (formId === 'add-payment-form' || formId === 'edit-payment-form') {
            const paymentType = form.querySelector('[name="payment_type"]').value;
            if (paymentType === 'Loan Settlement') {
                const loanId = form.querySelector('[name="loan_id"]').value;
                const loanError = document.getElementById(formId === 'add-payment-form' ? 'loan_id-error' : 'edit-loan_id-error');
                if (!loanId) {
                    loanError.classList.remove('hidden');
                    isValid = false;
                } else {
                    loanError.classList.add('hidden');
                }
            }
        }
        return isValid;
    }

    // Form Submission
    document.getElementById('add-payment-form')?.addEventListener('submit', (e) => {
        if (!validateForm('add-payment-form')) {
            e.preventDefault();
            return;
        }
        const button = e.target.querySelector('button[name="add"]');
        button.disabled = true;
        button.classList.add('btn-loading');
        setTimeout(() => {
            button.disabled = false;
            button.classList.remove('btn-loading');
        }, 1000);
    });

    document.getElementById('edit-payment-form')?.addEventListener('submit', (e) => {
        if (!validateForm('edit-payment-form')) {
            e.preventDefault();
            return;
        }
        const button = e.target.querySelector('button[name="update"]');
        button.disabled = true;
        button.classList.add('btn-loading');
        setTimeout(() => {
            button.disabled = false;
            button.classList.remove('btn-loading');
        }, 1000);
    });

    // Table Pagination and Search
    function setupTable(tableId, paginationId, rowsPerPage = 10) {
        const table = document.getElementById(tableId);
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const pagination = document.getElementById(paginationId);
        let currentPage = 1;
        let filteredRows = rows;

        function renderPage(page) {
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            tbody.innerHTML = '';
            filteredRows.slice(start, end).forEach(row => tbody.appendChild(row));

            const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
            pagination.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.className = `pagination-btn ${i === page ? 'active' : ''}`;
                btn.textContent = i;
                btn.disabled = i === page;
                btn.addEventListener('click', () => {
                    currentPage = i;
                    renderPage(i);
                });
                pagination.appendChild(btn);
            }
        }

        const searchInput = document.getElementById(tableId.replace('-table', '-search'));
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase();
            filteredRows = rows.filter(row => {
                return Array.from(row.cells).some(cell => cell.textContent.toLowerCase().includes(query));
            });
            currentPage = 1;
            renderPage(1);
        });

        renderPage(1);
    }

    // Initialize Tables
    setupTable('society-table', 'society-pagination');
    setupTable('membership-table', 'membership-pagination');
    setupTable('loan-table', 'loan-pagination');

    // Keyboard Navigation for Modal
    document.getElementById('edit-modal').addEventListener('keydown', (e) => {
        if (e.key === 'Escape') showCancelPopup();
    });
</script>
</body>
</html>