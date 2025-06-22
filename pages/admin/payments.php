<?php
define('APP_START', true);

ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

    session_start();

error_log("payments.php: Session: " . print_r($_SESSION, true));

try {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        error_log("Unauthorized access attempt - Session role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'not set'));
        header("Location: ../../admin-login.php");
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

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && isset($_GET['action']) && $_GET['action'] === 'get_loans') {
        ob_end_clean();
        header('Content-Type: application/json');
        
        $member_id = filter_input(INPUT_GET, 'member_id', FILTER_VALIDATE_INT);
        if (!$member_id || $member_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid member ID', 'code' => 1001]);
            exit;
        }

        try {
            $loans = $loan->getConfirmedPendingLoansByMemberId($member_id);
            echo json_encode(['status' => 'success', 'data' => $loans]);
        } catch (Exception $e) {
            error_log("payments.php: get_loans error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Failed to fetch loans: ' . $e->getMessage(), 'code' => $e->getCode() ?: 1012]);
        }
        exit;
    }

$members = $member->getAllMembers();
$society_payments = $payment->getPaymentsByType('Society Issued');
$membership_payments = $payment->getPaymentsByType('Membership Fee');
$loan_payments = $payment->getPaymentsByType('Loan Settlement');

$current_month = date('Y-m-01');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_add_fees']) && isset($_POST['tab']) && $_POST['tab'] === 'membership') {
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_add_loan_settlements']) && isset($_POST['tab']) && $_POST['tab'] === 'loan') {
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['auto_add_fees']) && !isset($_POST['auto_add_loan_settlements'])) {
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

                if (!$member_id || $member_id <= 0) {
                    throw new Exception("Invalid member selection.", 1001);
                }
                if (!$amount || $amount <= 0) {
                    throw new Exception("Amount must be greater than 0.", 1002);
                }
                if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    throw new Exception("Invalid date format.", 1003);
                }
                if (!$payment_mode || !in_array($payment_mode, ['Cash', 'Bank Transfer', 'Cheque'])) {
                    throw new Exception("Invalid payment mode.", 1004);
                }
                if (!$payment_type || !in_array($payment_type, ['Society Issued', 'Membership Fee', 'Loan Settlement'])) {
                    throw new Exception("Invalid payment type.", 1005);
                }

                $member_data = $member->getMemberById($member_id);
                if (!$member_data) {
                    throw new Exception("Selected member does not exist.", 1006);
                }

                if ($payment_type === 'Loan Settlement') {
                    if (!$loan_id || $loan_id <= 0) {
                        throw new Exception("Please select a valid loan for settlement.", 1007);
                    }
                    $loan_data = $loan->getLoanById($loan_id);
                    if (!$loan_data) {
                        throw new Exception("Selected loan does not exist.", 1008);
                    }
                    if ($loan_data['member_id'] !== $member_id) {
                        throw new Exception("Selected loan does not belong to this member.", 1009);
                    }
                    if ($loan_data['status'] !== 'Pending' || !$loan_data['is_confirmed']) {
                        throw new Exception("Selected loan is not eligible for settlement.", 1010);
                    }
            } else {
                    $loan_id = null;
                }

                if (!$payment->addPayment($member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id)) {
                    throw new Exception("Failed to add payment to database.", 1011);
                }

                $conn->commit();
                $success = "Payment added successfully!";
                $society_payments = $payment->getPaymentsByType('Society Issued');
                $membership_payments = $payment->getPaymentsByType('Membership Fee');
                $loan_payments = $payment->getPaymentsByType('Loan Settlement');
        } elseif (isset($_POST['update'])) {
            $id = intval($_POST['id']);
            $member_id = intval($_POST['member_id']);
            $amount = floatval($_POST['amount']);
            $date = $_POST['date'];
            $payment_mode = $_POST['payment_mode'];
            $payment_type = $_POST['payment_type'];
            $receipt_number = $_POST['receipt_number'] ?: null;
            $remarks = $_POST['remarks'] ?: null;
            $loan_id = $_POST['payment_type'] === 'Loan Settlement' ? intval($_POST['loan_id']) : null;

            if ($payment->updatePayment($id, $member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id)) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    echo json_encode(['success' => true, 'message' => 'Payment updated successfully!']);
                    exit;
                }
                $success = "Payment updated successfully!";
            } else {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    echo json_encode(['success' => false, 'message' => 'Error updating payment.']);
                    exit;
                }
                $error = "Error updating payment.";
            }
        } elseif (isset($_POST['delete'])) {
            $id = intval($_POST['id']);
            if ($payment->deletePayment($id)) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    echo json_encode(['success' => true, 'message' => 'Payment deleted successfully!']);
                    exit;
                }
                $success = "Payment deleted successfully!";
            } else {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    echo json_encode(['success' => false, 'message' => 'Error deleting payment.']);
                    exit;
                }
                $error = "Error deleting payment.";
            }
        } elseif (isset($_POST['confirm'])) {
            $id = intval($_POST['id']);
            if ($payment->confirmPayment($id)) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    echo json_encode(['success' => true, 'message' => 'Payment confirmed successfully!']);
                    exit;
                }
                $success = "Payment confirmed successfully!";
            } else {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    echo json_encode(['success' => false, 'message' => 'Error confirming payment.']);
                    exit;
                }
                $error = "Error confirming payment.";
            }
        }
    } catch (Exception $e) {
        error_log("Error in payments.php: " . $e->getMessage());
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
        $error = "Error: " . $e->getMessage();
    }
}

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_end_clean();
        header('Content-Type: application/json');
        if ($success) {
            echo json_encode(['success' => true, 'message' => $success]);
        } else {
            echo json_encode(['success' => false, 'message' => $error['message'] ?? 'Unknown server error', 'code' => $error['code'] ?? 1018]);
        }
        exit;
    }

    $js_success = json_encode($success);
    $js_error = json_encode($error ? $error['message'] : '');
    error_log("payments.php: JS Success: $js_success");
    error_log("payments.php: JS Error: $js_error");

} catch (Exception $e) {
    $error = "Initialization error: " . $e->getMessage();
    error_log("payments.php: Error: $error");
    $js_success = json_encode('');
    $js_error = json_encode($error);
}

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

            <div class="tab-container">
                <button class="tab-btn <?php echo !isset($_GET['tab']) || $_GET['tab'] === 'add' ? 'active' : ''; ?>" data-tab="add">Add Payment</button>
                <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'society' ? 'active' : ''; ?>" data-tab="society">Society Issued</button>
                <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'membership' ? 'active' : ''; ?>" data-tab="membership">Membership Fees</button>
                <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'loan' ? 'active' : ''; ?>" data-tab="loan">Loan Settlements</button>
    </div>

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
                            <tr data-payment-id="<?php echo $p['id']; ?>">
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
                                        <button class="btn btn-primary confirm-payment" data-id="<?php echo $p['id']; ?>">
                                            <i class="ri-check-line"></i>Confirm
                                        </button>
                                        <button class="btn btn-primary edit-payment" data-payment='<?php echo json_encode($p, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>'>
                                            <i class="ri-edit-line"></i>Edit
                                    </button>
                                        <button class="btn btn-danger delete-payment" data-id="<?php echo $p['id']; ?>">
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

            <div id="tab-membership" class="tab-content <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'membership' ? 'hidden' : ''; ?>">
                <div class="flex justify-between items-center">
                    <h2>Membership Fees</h2>
                    <div class="flex gap-4">
                        <div class="form-group">
                            <label for="membership-search" class="form-label">Search</label>
                            <input type="text" id="membership-search" class="input-field" placeholder="Search payments...">
                </div>
                        <form method="POST" class="inline">
                            <input type="hidden" name="tab" value="membership">
                            <button type="submit" name="auto_add_fees" value="1" class="btn btn-primary">
                                <i class="ri-add-line"></i>Auto-Add Fees
                            </button>
                        </form>
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
                            <tr data-payment-id="<?php echo $p['id']; ?>">
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
                                        <button class="btn btn-primary confirm-payment" data-id="<?php echo $p['id']; ?>">
                                            <i class="ri-check-line"></i>Confirm
                                        </button>
                                        <button class="btn btn-primary edit-payment" data-payment='<?php echo json_encode($p, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>'>
                                            <i class="ri-edit-line"></i>Edit
                                    </button>
                                        <button class="btn btn-danger delete-payment" data-id="<?php echo $p['id']; ?>">
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

            <div id="tab-loan" class="tab-content <?php echo !isset($_GET['tab']) || $_GET['tab'] !== 'loan' ? 'hidden' : ''; ?>">
                <div class="flex justify-between items-center">
                    <h2>Loan Settlements</h2>
                    <div class="flex gap-4">
                        <div class="form-group">
                            <label for="loan-search" class="form-label">Search</label>
                            <input type="text" id="loan-search" class="input-field" placeholder="Search payments...">
                </div>
                        <form method="POST" class="inline">
                            <input type="hidden" name="tab" value="loan">
                            <button type="submit" name="auto_add_loan_settlements" value="1" class="btn btn-primary">
                                <i class="ri-add-line"></i>Auto-Add Settlements
                            </button>
                        </form>
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
                            <tr data-payment-id="<?php echo $p['id']; ?>">
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
                                        <button class="btn btn-primary confirm-payment" data-id="<?php echo $p['id']; ?>">
                                            <i class="ri-check-line"></i>Confirm
                                        </button>
                                        <button class="btn btn-primary edit-payment" data-payment='<?php echo json_encode($p, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>'>
                                            <i class="ri-edit-line"></i>Edit
                                    </button>
                                        <button class="btn btn-danger delete-payment" data-id="<?php echo $p['id']; ?>">
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

            <div class="popup-overlay" id="popup-overlay"></div>
            <div class="popup" id="success-popup">
                <div>
                    <i class="ri-checkbox-circle-fill" style="color: var(--success-green);"></i>
                    <h3>Success!</h3>
                    <p id="success-message"></p>
                    <div class="countdown">
                        Redirecting in <span id="success-countdown">3</span> seconds...
                    </div>
                </div>
            </div>
            <div class="popup" id="error-popup">
                <div>
                    <i class="ri-error-warning-fill" style="color: var(--error-red);"></i>
                    <h3>Error</h3>
                    <p id="error-message"></p>
                    <div class="countdown">
                        Redirecting in <span id="error-countdown">3</span> seconds...
                    </div>
                </div>
            </div>
            <div class="popup" id="cancel-popup">
                <div>
                    <i class="ri-close-circle-fill" style="color: var(--cancel-gray);"></i>
                    <h3>Cancelled</h3>
                    <p>The operation has been cancelled.</p>
                    <div class="countdown">
                        Redirecting in <span id="cancel-countdown">3</span> seconds...
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</div>

<script>
    window.successMsg = <?php echo json_encode($success ?? ''); ?>;
    window.errorMsg = <?php echo json_encode($error ?? ''); ?>;
    window.baseUrl = '<?php echo htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES, 'UTF-8'); ?>';
</script>
<script src="../../assets/js/payments.js"></script>
</body>
</html>