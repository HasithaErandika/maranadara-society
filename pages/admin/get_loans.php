<?php
require_once '../classes/Payment.php';
header('Content-Type: application/json');
if (isset($_GET['member_id'])) {
    $member_id = intval($_GET['member_id']);
    $payment = new Payment();
    $loans = $payment->getConfirmedLoansByMemberId($member_id);
    echo json_encode($loans);
} else {
    echo json_encode([]);
}
?>