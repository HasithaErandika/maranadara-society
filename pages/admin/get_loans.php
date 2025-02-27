<?php
header('Content-Type: application/json');
require_once '../../classes/Loan.php';

try {
    $loan = new Loan();

    if (isset($_GET['member_id']) && !empty($_GET['member_id'])) {
        $member_id = intval($_GET['member_id']);
        $loans = $loan->getLoansByMemberId($member_id);
        if ($loans === false) {
            throw new Exception("Failed to fetch loans from database.");
        }
        echo json_encode($loans);
    } else {
        echo json_encode([]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
?>