<?php
require_once __DIR__ . '/Database.php';

class Payment {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Add a payment with payment_type
    public function addPayment($member_id, $amount, $date, $payment_mode, $payment_type) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("INSERT INTO payments (member_id, amount, date, payment_mode, payment_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("idsss", $member_id, $amount, $date, $payment_mode, $payment_type);
        return $stmt->execute();
    }

    // Get payments for a member (for user dashboard)
    public function getPaymentsByMemberId($member_id) {
        $conn = $this->db->getConnection();
        if ($member_id === null) {
            // Fetch all payments when member_id is null (for admin payments.php)
            $result = $conn->query("SELECT * FROM payments");
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt = $conn->prepare("SELECT amount, date, payment_mode, payment_type FROM payments WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get all payments
    public function getAllPayments() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT * FROM payments");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get payments by type
    public function getPaymentsByType($payment_type) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_type = ?");
        $stmt->bind_param("s", $payment_type);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get total payments (for admin dashboard stats)
    public function getTotalPayments() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_type = 'Membership Fee'")->fetch_assoc();
        return $result['total'] ?? 0; // Only membership fees for dashboard stats
    }
    // Add this method to the existing Payment.php
    public function getTotalSocietyIssuedPayments() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_type = 'Society Issued'")->fetch_assoc();
        return $result['total'] ?? 0;
    }
}
?>