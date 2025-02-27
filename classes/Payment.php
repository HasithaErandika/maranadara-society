<?php
require_once __DIR__ . '/Database.php';

class Payment {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function addPayment($member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id = null, $is_confirmed = false, $confirmed_by = null) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare(
            "INSERT INTO payments (member_id, amount, date, payment_mode, payment_type, receipt_number, remarks, loan_id, is_confirmed, confirmed_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("idsssssisis", $member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id, $is_confirmed, $confirmed_by);
        return $stmt->execute();
    }

    public function getPaymentsByMemberId($member_id) {
        $conn = $this->db->getConnection();
        if ($member_id === null) {
            $result = $conn->query("SELECT * FROM payments");
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt = $conn->prepare("SELECT * FROM payments WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getAllPayments() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT * FROM payments");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getPaymentsByType($payment_type) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_type = ?");
        $stmt->bind_param("s", $payment_type);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getTotalPayments() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_type = 'Membership Fee' AND is_confirmed = TRUE")->fetch_assoc();
        return $result['total'] ?? 0;
    }

    public function getTotalSocietyIssuedPayments() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_type = 'Society Issued' AND is_confirmed = TRUE")->fetch_assoc();
        return $result['total'] ?? 0;
    }

    public function getTotalLoanSettlementPayments() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_type = 'Loan Settlement' AND is_confirmed = TRUE")->fetch_assoc();
        return $result['total'] ?? 0;
    }

    public function confirmPayment($id, $confirmed_by) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("UPDATE payments SET is_confirmed = TRUE, confirmed_by = ? WHERE id = ? AND is_confirmed = FALSE");
        $stmt->bind_param("si", $confirmed_by, $id);
        return $stmt->execute();
    }
}
?>