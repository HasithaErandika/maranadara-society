<?php
require_once '../../classes/Loan.php';

header('Content-Type: application/json');
// Debug header to track request
header('X-Debug: get_loans.php executed');

try {
    $member_id = filter_input(INPUT_GET, 'member_id', FILTER_VALIDATE_INT);
    if ($member_id === false || $member_id === null) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid member ID', 'data' => []]);
        exit;
    }

    $loan = new Loan();
    $loans = $loan->getConfirmedPendingLoansByMemberId($member_id);
    echo json_encode(['status' => 'success', 'data' => $loans]);
} catch (Exception $e) {
    error_log("Error in get_loans.php: " . $e->getMessage() . " | Member ID: " . ($member_id ?? 'unset'));
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error', 'data' => []]);
}
?>