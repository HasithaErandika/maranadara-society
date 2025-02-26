<?php
require_once '../classes/Database.php';

class Loan {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function addLoan($member_id, $amount, $interest_rate, $duration) {
        $monthly_payment = $this->calculateMonthlyPayment($amount, $interest_rate, $duration);
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("INSERT INTO loans (member_id, amount, interest_rate, duration, monthly_payment) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iddid", $member_id, $amount, $interest_rate, $duration, $monthly_payment);
        return $stmt->execute();
    }

    private function calculateMonthlyPayment($principal, $rate, $months) {
        $monthly_rate = $rate / 12 / 100;
        return $principal * $monthly_rate * pow(1 + $monthly_rate, $months) / (pow(1 + $monthly_rate, $months) - 1);
    }
}
?>