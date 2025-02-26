<?php
require_once __DIR__ . '/Database.php';

class Loan {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Add a loan
    public function addLoan($member_id, $amount, $interest_rate, $duration) {
        $monthly_payment = $this->calculateMonthlyPayment($amount, $interest_rate, $duration);
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("INSERT INTO loans (member_id, amount, interest_rate, duration, monthly_payment) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iddid", $member_id, $amount, $interest_rate, $duration, $monthly_payment);
        return $stmt->execute();
    }

    // Get loans for a member (for user dashboard)
    public function getLoansByMemberId($member_id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM loans WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get all loans (for admin dashboard and loans.php)
    public function getAllLoans() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT * FROM loans");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get total loans (for admin dashboard stats)
    public function getTotalLoans() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT SUM(amount) as total FROM loans")->fetch_assoc();
        return $result['total'] ?? 0;
    }

    // Calculate monthly payment (amortization formula)
    public function calculateMonthlyPayment($principal, $rate, $months) {
        $monthly_rate = $rate / 12 / 100;
        return $principal * $monthly_rate * pow(1 + $monthly_rate, $months) / (pow(1 + $monthly_rate, $months) - 1);
    }
}
?>