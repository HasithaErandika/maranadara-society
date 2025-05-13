<?php
define('APP_START', true);

// Start output buffering
ob_start();

// Disable displaying errors
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start();

// Log session state
error_log("payments.php: Session: " . print_r($_SESSION, true));

try {
    // Redirect if not admin or credentials missing
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' ||
        !isset($_SESSION['db_username']) || !isset($_SESSION['db_password'])) {
        error_log("payments.php: Missing session variables, redirecting to admin-login.php");
        header("Location: /admin-login.php");
        exit;
    }

    require_once __DIR__ . '/../../classes/Member.php';
    require_once __DIR__ . '/../../classes/Payment.php';
    require_once __DIR__ . '/../../classes/Loan.php';
    require_once __DIR__ . '/../../classes/Database.php';

    $member = new Member();
    $payment = new Payment();
    $loan = new Loan();
    $db = new Database();
    $error = $success = '';

    // Handle AJAX request for fetching loans
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && isset($_GET['action']) && $_GET['action'] === 'get_loans') {
        ob_end_clean();
        header('Content-Type: application/json');
        
        $member_id = filter_input(INPUT_GET, 'member_id', FILTER_VALIDATE_INT);
        if (!$member_id || $member_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid member ID']);
            exit;
        }

        try {
            $loans = $loan->getConfirmedPendingLoansByMemberId($member_id);
            echo json_encode(['status' => 'success', 'data' => $loans]);
        } catch (Exception $e) {
            error_log("payments.php: get_loans error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Failed to fetch loans: ' . $e->getMessage()]);
        }
        exit;
    }

    $members = $member->getAllMembers();
    $society_payments = $payment->getPaymentsByType('Society Issued');
    $membership_payments = $payment->getPaymentsByType('Membership Fee');
    $loan_payments = $payment->getPaymentsByType('Loan Settlement');

    $current_month = date('Y-m-01');
    if (isset($_GET['auto_add_fees']) && isset($_GET['tab']) && $_GET['tab'] === 'membership') {
        try {
            $conn = $db->getConnection();
            $conn->begin_transaction();
            $count = $payment->autoAddMembershipFees($current_month);
            $conn->commit();
            $success = $count > 0 ? "Added membership fees for $count members." : "No new fees added.";
            $membership_payments = $payment->getPaymentsByType('Membership Fee');
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error adding membership fees: " . $e->getMessage();
            error_log("Auto-add fees error: " . $e->getMessage());
        } finally {
            $db->closeConnection();
        }
    }

    if (isset($_GET['auto_add_loan_settlements']) && isset($_GET['tab']) && $_GET['tab'] === 'loan') {
        try {
            $conn = $db->getConnection();
            $conn->begin_transaction();
            $count = $payment->autoAddLoanSettlements($current_month);
            $conn->commit();
            $success = $count > 0 ? "Added settlements for $count loans." : "No new settlements added.";
            $loan_payments = $payment->getPaymentsByType('Loan Settlement');
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error adding loan settlements: " . $e->getMessage();
            error_log("Auto-add settlements error: " . $e->getMessage());
        } finally {
            $db->closeConnection();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("payments.php: POST received: " . print_r($_POST, true));
        $conn = $db->getConnection();
        $conn->begin_transaction();

        try {
            if (isset($_POST['add'])) {
                $member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
                $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
                $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
                $payment_mode = filter_input(INPUT_POST, 'payment_mode', FILTER_SANITIZE_STRING);
                $payment_type = filter_input(INPUT_POST, 'payment_type', FILTER_SANITIZE_STRING);
                $receipt_number = filter_input(INPUT_POST, 'receipt_number', FILTER_SANITIZE_STRING) ?: null;
                $remarks = filter_input(INPUT_POST, 'monthly_contribution', FILTER_SANITIZE_STRING) ?: null;
                $loan_id = ($payment_type === 'Loan Settlement' && !empty($_POST['loan_id']) && $_POST['loan_id'] > 0) ? filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT) : null;

                // Detailed validation
                if (!$member_id || $member_id <= 0) {
                    throw new Exception("Invalid member selection.");
                }
                if (!$amount || $amount <= 0) {
                    throw new Exception("Amount must be greater than 0.");
                }
                if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    throw new Exception("Invalid date format.");
                }
                if (!$payment_mode || !in_array($payment_mode, ['Cash', 'Bank Transfer', 'Cheque'])) {
                    throw new Exception("Invalid payment mode.");
                }
                if (!$payment_type || !in_array($payment_type, ['Society Issued', 'Membership Fee', 'Loan Settlement'])) {
                    throw new Exception("Invalid payment type.");
                }

                // Verify member exists
                $member_data = $member->getMemberById($member_id);
                if (!$member_data) {
                    throw new Exception("Selected member does not exist.");
                }

                // Verify loan for Loan Settlement
                if ($payment_type === 'Loan Settlement') {
                    if (!$loan_id || $loan_id <= 0) {
                        throw new Exception("Please select a valid loan for settlement.");
                    }
                    $loan_data = $loan->getLoanById($loan_id);
                    if (!$loan_data) {
                        throw new Exception("Selected loan does not exist.");
                    }
                    if ($loan_data['member_id'] !== $member_id) {
                        throw new Exception("Selected loan does not belong to this member.");
                    }
                    if ($loan_data['status'] !== 'Pending' || !$loan_data['is_confirmed']) {
                        throw new Exception("Selected loan is not eligible for settlement.");
                    }
                } else {
                    $loan_id = null; // Ensure loan_id is NULL for non-loan payments
                }

                // Add payment
                if (!$payment->addPayment($member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id)) {
                    throw new Exception("Failed to add payment to database.");
                }

                $conn->commit();
                $success = "Payment added successfully!";
                $society_payments = $payment->getPaymentsByType('Society Issued');
                $membership_payments = $payment->getPaymentsByType('Membership Fee');
                $loan_payments = $payment->getPaymentsByType('Loan Settlement');
            } elseif (isset($_POST['update'])) {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
                $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
                $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
                $payment_mode = filter_input(INPUT_POST, 'payment_mode', FILTER_SANITIZE_STRING);
                $payment_type = filter_input(INPUT_POST, 'payment_type', FILTER_SANITIZE_STRING);
                $receipt_number = filter_input(INPUT_POST, 'receipt_number', FILTER_SANITIZE_STRING) ?: null;
                $remarks = filter_input(INPUT_POST, 'monthly_contribution', FILTER_SANITIZE_STRING) ?: null;
                $loan_id = ($payment_type === 'Loan Settlement' && !empty($_POST['loan_id']) && $_POST['loan_id'] > 0) ? filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT) : null;

                if (!$id || !$member_id || $amount <= 0 || !$date || !$payment_mode || !$payment_type) {
                    throw new Exception("Please fill all required fields correctly.");
                }

                // Verify member exists
                $member_data = $member->getMemberById($member_id);
                if (!$member_data) {
                    throw new Exception("Selected member does not exist.");
                }

                // Verify loan for Loan Settlement
                if ($payment_type === 'Loan Settlement') {
                    if (!$loan_id || $loan_id <= 0) {
                        throw new Exception("Please select a valid loan for settlement.");
                    }
                    $loan_data = $loan->getLoanById($loan_id);
                    if (!$loan_data) {
                        throw new Exception("Selected loan does not exist.");
                    }
                    if ($loan_data['member_id'] !== $member_id) {
                        throw new Exception("Selected loan does not belong to this member.");
                    }
                    if ($loan_data['status'] !== 'Pending' || !$loan_data['is_confirmed']) {
                        throw new Exception("Selected loan is not eligible for settlement.");
                    }
                } else {
                    $loan_id = null; // Ensure loan_id is NULL for non-loan payments
                }

                if (!$payment->updatePayment($id, $member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id)) {
                    throw new Exception("Failed to update payment or payment is confirmed.");
                }
                $conn->commit();
                $success = "Payment updated successfully!";
                $society_payments = $payment->getPaymentsByType('Society Issued');
                $membership_payments = $payment->getPaymentsByType('Membership Fee');
                $loan_payments = $payment->getPaymentsByType('Loan Settlement');
            } elseif (isset($_POST['delete'])) {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new Exception("Invalid payment ID.");
                }
                if (!$payment->deletePayment($id)) {
                    throw new Exception("Failed to delete payment or payment is confirmed.");
                }
                $conn->commit();
                $success = "Payment deleted successfully!";
                $society_payments = $payment->getPaymentsByType('Society Issued');
                $membership_payments = $payment->getPaymentsByType('Membership Fee');
                $loan_payments = $payment->getPaymentsByType('Loan Settlement');
            } elseif (isset($_POST['confirm'])) {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new Exception("Invalid payment ID.");
                }
                if (!$payment->confirmPayment($id)) {
                    throw new Exception("Failed to confirm payment or already confirmed.");
                }
                $conn->commit();
                $success = "Payment confirmed!";
                $society_payments = $payment->getPaymentsByType('Society Issued');
                $membership_payments = $payment->getPaymentsByType('Membership Fee');
                $loan_payments = $payment->getPaymentsByType('Loan Settlement');
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
            error_log("payments.php: POST error: $error");
        } finally {
            $db->closeConnection();
        }
    }

    // Handle AJAX requests for POST actions
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_end_clean();
        header('Content-Type: application/json');
        if ($success) {
            echo json_encode(['success' => true, 'message' => $success]);
        } else {
            echo json_encode(['success' => false, 'message' => $error ?: 'Unknown server error.']);
        }
        exit;
    }

    // Escape messages for JavaScript
    $js_success = json_encode($success);
    $js_error = json_encode($error);
    error_log("payments.php: JS Success: $js_success");
    error_log("payments.php: JS Error: $js_error");

} catch (Exception $e) {
    $error = "Initialization error: " . $e->getMessage();
    error_log("payments.php: Error: $error");
    $js_success = json_encode('');
    $js_error = json_encode($error);
}

// Clear output buffer for non-AJAX
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Maranadhara Samithi</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/payments.css">
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<div class="flex min-h-screen">
    <?php include __DIR__ . '/../../includes/sidepanel.php'; ?>
    <main class="main">
        <div class="container">
            <h1 class="animate-slide-in" style="font-size: 2rem; font-weight: 700; margin-bottom: 20px;">Manage Payments</h1>
            <div class="progress-bar" id="progress-bar"></div>

            <!-- Tabs -->
            <div class="tab-container">
                <button class="tab-btn <?php echo !isset($_GET['tab']) || $_GET['tab'] === 'add' ? 'active' : ''; ?>" data-tab="add">Add Payment</button>
                <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'society' ? 'active' : ''; ?>" data-tab="society">Society Issued</button>
                <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'membership' ? 'active' : ''; ?>" data-tab="membership">Membership Fees</button>
                <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'loan' ? 'active' : ''; ?>" data-tab="loan">Loan Settlements</button>
            </div>

            <!-- Add Payment -->
            <div id="tab-add" class="tab-content <?php echo isset($_GET['tab']) && $_GET['tab'] !== 'add' ? 'hidden' : ''; ?>">
                <form method="POST" id="add-payment-form" class="form-section">
                    <h2>Add Payment</h2>
                    <div class="grid">
                        <div class="form-group">
                            <label for="member_id" class="form-label">Member <span class="required-mark">*</span></label>
                            <select name="member_id" id="member_id" class="input-field" required>
                                <option value="" disabled selected>Select Member</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="error-text" id="member_id-error">Please select a member.</span>
                        </div>
                        <div class="form-group">
                            <label for="amount" class="form-label">Amount (LKR) <span class="required-mark">*</span></label>
                            <input type="number" name="amount" id="amount" step="0.01" min="0.01" class="input-field" required placeholder="0.00">
                            <span class="error-text" id="amount-error">Amount must be greater than 0.</span>
                        </div>
                        <div class="form-group">
                            <label for="date" class="form-label">Date <span class="required-mark">*</span></label>
                            <input type="date" name="date" id="date" class="input-field" required value="<?php echo date('Y-m-d'); ?>">
                            <span class="error-text" id="date-error">Please select a valid date.</span>
                        </div>
                        <div class="form-group">
                            <label for="payment_mode" class="form-label">Payment Mode <span class="required-mark">*</span></label>
                            <select name="payment_mode" id="payment_mode" class="input-field" required>
                                <option value="" disabled selected>Select Mode</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                            <span class="error-text" id="payment_mode-error">Please select a payment mode.</span>
                        </div>
                        <div class="form-group">
                            <label for="payment_type" class="form-label">Payment Type <span class="required-mark">*</span></label>
                            <select name="payment_type" id="payment_type" class="input-field" required>
                                <option value="" disabled selected>Select Type</option>
                                <option value="Society Issued">Society Issued</option>
                                <option value="Membership Fee">Membership Fee</option>
                                <option value="Loan Settlement">Loan Settlement</option>
                            </select>
                            <span class="error-text" id="payment_type-error">Please select a payment type.</span>
                        </div>
                        <div class="form-group">
                            <label for="receipt_number" class="form-label">Receipt Number</label>
                            <input type="text" name="receipt_number" id="receipt_number" class="input-field" placeholder="e.g., R12345">
                            <span class="error-text" id="receipt_number-error"></span>
                        </div>
                        <div class="form-group hidden" id="loan-section">
                            <label for="loan_id" class="form-label">Select Loan <span class="required-mark">*</span></label>
                            <select name="loan_id" id="loan_id" class="input-field">
                                <option value="" disabled selected>Select a Loan</option>
                            </select>
                            <span class="error-text" id="loan_id-error">Please select a loan.</span>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="monthly_contribution" class="form-label">Remarks</label>
                            <textarea name="monthly_contribution" id="monthly_contribution" class="input-field" rows="4" placeholder="Additional details..."></textarea>
                            <span class="error-text" id="monthly_contribution-error"></span>
                        </div>
                    </div>
                    <div class="flex">
                        <button type="submit" name="add" class="btn btn-primary">
                            <i class="ri-add-line"></i>Add Payment
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm('add-payment-form')">
                            <i class="ri-close-line"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Society Issued -->
            <div id="tab-society" class="tab-content <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'society' ? 'hidden' : ''; ?>">
                <div class="flex justify-between items-center">
                    <h2>Society Issued Payments</h2>
                    <div class="form-group">
                        <label for="society-search" class="form-label">Search</label>
                        <input type="text" id="society-search" class="input-field" placeholder="Search payments...">
                    </div>
                </div>
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
                                <td><?php echo $p['is_confirmed'] ? 'Confirmed' : 'Pending'; ?></td>
                                <td><?php echo htmlspecialchars($p['confirmed_by'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!$p['is_confirmed']): ?>
                                        <button class="btn btn-primary" onclick="confirmPayment(<?php echo $p['id']; ?>)">
                                            <i class="ri-check-line"></i>Confirm
                                        </button>
                                        <button class="btn btn-primary" onclick='showEditModal(<?php echo json_encode($p, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>)'>
                                            <i class="ri-edit-line"></i>Edit
                                        </button>
                                        <button class="btn btn-danger" onclick="deletePayment(<?php echo $p['id']; ?>)">
                                            <i class="ri-delete-bin-line"></i>Delete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($society_payments)): ?>
                            <tr><td colspan="9">No payments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="pagination" id="society-pagination"></div>
            </div>

            <!-- Membership Fees -->
            <div id="tab-membership" class="tab-content <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'membership' ? 'hidden' : ''; ?>">
                <div class="flex justify-between items-center">
                    <h2>Membership Fees</h2>
                    <div class="flex gap-4">
                        <div class="form-group">
                            <label for="membership-search" class="form-label">Search</label>
                            <input type="text" id="membership-search" class="input-field" placeholder="Search payments...">
                        </div>
                        <a href="?tab=membership&auto_add_fees=1" class="btn btn-primary">
                            <i class="ri-add-line"></i>Auto-Add Fees
                        </a>
                    </div>
                </div>
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
                                <td><?php echo $p['is_confirmed'] ? 'Confirmed' : 'Pending'; ?></td>
                                <td><?php echo htmlspecialchars($p['confirmed_by'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!$p['is_confirmed']): ?>
                                        <button class="btn btn-primary" onclick="confirmPayment(<?php echo $p['id']; ?>)">
                                            <i class="ri-check-line"></i>Confirm
                                        </button>
                                        <button class="btn btn-primary" onclick='showEditModal(<?php echo json_encode($p, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>)'>
                                            <i class="ri-edit-line"></i>Edit
                                        </button>
                                        <button class="btn btn-danger" onclick="deletePayment(<?php echo $p['id']; ?>)">
                                            <i class="ri-delete-bin-line"></i>Delete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($membership_payments)): ?>
                            <tr><td colspan="9">No payments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="pagination" id="membership-pagination"></div>
            </div>

            <!-- Loan Settlements -->
            <div id="tab-loan" class="tab-content <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'loan' ? 'hidden' : ''; ?>">
                <div class="flex justify-between items-center">
                    <h2>Loan Settlements</h2>
                    <div class="flex gap-4">
                        <div class="form-group">
                            <label for="loan-search" class="form-label">Search</label>
                            <input type="text" id="loan-search" class="input-field" placeholder="Search payments...">
                        </div>
                        <a href="?tab=loan&auto_add_loan_settlements=1" class="btn btn-primary">
                            <i class="ri-add-line"></i>Auto-Add Settlements
                        </a>
                    </div>
                </div>
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
                                <td><?php echo $p['is_confirmed'] ? 'Confirmed' : 'Pending'; ?></td>
                                <td><?php echo htmlspecialchars($p['confirmed_by'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!$p['is_confirmed']): ?>
                                        <button class="btn btn-primary" onclick="confirmPayment(<?php echo $p['id']; ?>)">
                                            <i class="ri-check-line"></i>Confirm
                                        </button>
                                        <button class="btn btn-primary" onclick='showEditModal(<?php echo json_encode($p, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>)'>
                                            <i class="ri-edit-line"></i>Edit
                                        </button>
                                        <button class="btn btn-danger" onclick="deletePayment(<?php echo $p['id']; ?>)">
                                            <i class="ri-delete-bin-line"></i>Delete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($loan_payments)): ?>
                            <tr><td colspan="10">No payments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="pagination" id="loan-pagination"></div>
            </div>

            <!-- Edit Modal -->
            <div id="edit-modal" class="modal">
                <div class="modal-content">
                    <span class="modal-close" onclick="showCancelPopup()">×</span>
                    <form method="POST" id="edit-payment-form" class="form-section">
                        <h2>Edit Payment</h2>
                        <input type="hidden" name="id" id="edit-id">
                        <div class="grid">
                            <div class="form-group">
                                <label for="edit-member_id" class="form-label">Member <span class="required-mark">*</span></label>
                                <select name="member_id" id="edit-member_id" class="input-field" required>
                                    <option value="" disabled>Select Member</option>
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="error-text" id="edit-member_id-error">Please select a member.</span>
                            </div>
                            <div class="form-group">
                                <label for="edit-amount" class="form-label">Amount (LKR) <span class="required-mark">*</span></label>
                                <input type="number" name="amount" id="edit-amount" step="0.01" min="0.01" class="input-field" required>
                                <span class="error-text" id="edit-amount-error">Amount must be greater than 0.</span>
                            </div>
                            <div class="form-group">
                                <label for="edit-date" class="form-label">Date <span class="required-mark">*</span></label>
                                <input type="date" name="date" id="edit-date" class="input-field" required>
                                <span class="error-text" id="edit-date-error">Please select a valid date.</span>
                            </div>
                            <div class="form-group">
                                <label for="edit-payment_mode" class="form-label">Payment Mode <span class="required-mark">*</span></label>
                                <select name="payment_mode" id="edit-payment_mode" class="input-field" required>
                                    <option value="" disabled>Select Mode</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                                <span class="error-text" id="edit-payment_mode-error">Please select a payment mode.</span>
                            </div>
                            <div class="form-group">
                                <label for="edit-payment_type" class="form-label">Payment Type <span class="required-mark">*</span></label>
                                <select name="payment_type" id="edit-payment_type" class="input-field" required>
                                    <option value="" disabled>Select Type</option>
                                    <option value="Society Issued">Society Issued</option>
                                    <option value="Membership Fee">Membership Fee</option>
                                    <option value="Loan Settlement">Loan Settlement</option>
                                </select>
                                <span class="error-text" id="edit-payment_type-error">Please select a payment type.</span>
                            </div>
                            <div class="form-group">
                                <label for="edit-receipt_number" class="form-label">Receipt Number</label>
                                <input type="text" name="receipt_number" id="edit-receipt_number" class="input-field" placeholder="e.g., R12345">
                                <span class="error-text" id="edit-receipt_number-error"></span>
                            </div>
                            <div class="form-group hidden" id="edit-loan-section">
                                <label for="edit-loan_id" class="form-label">Select Loan <span class="required-mark">*</span></label>
                                <select name="loan_id" id="edit-loan_id" class="input-field">
                                    <option value="" disabled>Select a Loan</option>
                                </select>
                                <span class="error-text" id="edit-loan_id-error">Please select a loan.</span>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="edit-remarks" class="form-label">Remarks</label>
                                <textarea name="monthly_contribution" id="edit-remarks" class="input-field" rows="4"></textarea>
                                <span class="error-text" id="edit-remarks-error"></span>
                            </div>
                        </div>
                        <div class="flex">
                            <button type="submit" name="update" class="btn btn-primary">
                                <i class="ri-save-line"></i>Update
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="showCancelPopup()">
                                <i class="ri-close-line"></i>Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Popups -->
            <div class="popup-overlay" id="popup-overlay"></div>
            <div class="popup" id="success-popup">
                <div style="text-align: center;">
                    <div style="font-size: 3rem; color: #2ecc71; margin-bottom: 20px;"><i class="ri-checkbox-circle-fill"></i></div>
                    <h3 style="font-size: 1.5rem; font-weight: 700;">Success!</h3>
                    <p style="color: #7f8c8d; margin-top: 10px;" id="success-message"></p>
                    <div style="margin-top: 20px; font-size: 0.8rem; color: #7f8c8d;">
                        Redirecting in <span id="success-countdown" style="font-weight: 600;">3</span> seconds...
                    </div>
                </div>
            </div>
            <div class="popup" id="error-popup">
                <div style="text-align: center;">
                    <div style="font-size: 3rem; color: #e74c3c; margin-bottom: 20px;"><i class="ri-error-warning-fill"></i></div>
                    <h3 style="font-size: 1.5rem; font-weight: 700;">Error</h3>
                    <p style="color: #7f8c8d; margin-top: 10px;" id="error-message"></p>
                    <div style="margin-top: 20px; font-size: 0.8rem; color: #7f8c8d;">
                        Redirecting in <span id="error-countdown" style="font-weight: 600;">3</span> seconds...
                    </div>
                </div>
            </div>
            <div class="popup" id="cancel-popup">
                <div style="text-align: center;">
                    <div style="font-size: 3rem; color: #7f8c8d; margin-bottom: 20px;"><i class="ri-close-circle-fill"></i></div>
                    <h3 style="font-size: 1.5rem; font-weight: 700;">Cancelled</h3>
                    <p style="color: #7f8c8d; margin-top: 10px;">The operation has been cancelled.</p>
                    <div style="margin-top: 20px; font-size: 0.8rem; color: #7f8c8d;">
                        Redirecting in <span id="cancel-countdown" style="font-weight: 600;">3</span> seconds...
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    window.successMsg = '<?php echo $js_success; ?>';
    window.errorMsg = '<?php echo $js_error; ?>';
</script>
<script src="../../assets/js/payments.js"></script>
</body>
</html>