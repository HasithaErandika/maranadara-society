<?php
define('APP_START', true);

session_start();

// Disable displaying errors
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // Verify admin session
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' ||
        !isset($_SESSION['db_username']) || !isset($_SESSION['db_password'])) {
        error_log("get_loans.php: Unauthorized access attempt");
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
        exit;
    }

    require_once __DIR__ . '/../../classes/Loan.php';

    $loan = new Loan();

    // Get member_id from query parameter
    $member_id = filter_input(INPUT_GET, 'member_id', FILTER_VALIDATE_INT);
    if (!$member_id || $member_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid member ID']);
        exit;
    }

    // Fetch confirmed and pending loans
    $loans = $loan->getConfirmedPendingLoansByMemberId($member_id);
    echo json_encode(['status' => 'success', 'data' => $loans]);
} catch (Exception $e) {
    error_log("get_loans.php: Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
?>