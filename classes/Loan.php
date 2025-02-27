<?php
require_once __DIR__ . '/Database.php';

class Loan {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Add a loan with more details
    public function addLoan($member_id, $amount, $interest_rate, $duration, $start_date, $remarks = null) {
        $monthly_payment = $this->calculateMonthlyPayment($amount, $interest_rate, $duration);
        $end_date = date('Y-m-d', strtotime($start_date . " + $duration months")); // Calculate end date
        $status = 'Pending'; // Default status for new loans

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare(
            "INSERT INTO loans (member_id, amount, interest_rate, duration, monthly_payment, start_date, end_date, status, remarks) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iddidssss", $member_id, $amount, $interest_rate, $duration, $monthly_payment, $start_date, $end_date, $status, $remarks);
        return $stmt->execute();
    }

    // Get loans for a member
    public function getLoansByMemberId($member_id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM loans WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get all loans
    public function getAllLoans() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT * FROM loans");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get total loans
    public function getTotalLoans() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT SUM(amount) as total FROM loans")->fetch_assoc();
        return $result['total'] ?? 0;
    }

    // Calculate monthly payment
    public function calculateMonthlyPayment($principal, $rate, $months) {
        $monthly_rate = $rate / 12 / 100; // Convert annual rate to monthly decimal
        if ($monthly_rate == 0) {
            return $principal / $months; // Handle 0% interest case
        }
        return $principal * $monthly_rate * pow(1 + $monthly_rate, $months) / (pow(1 + $monthly_rate, $months) - 1);
    }

    // Calculate and format monthly payment
    public function calculateAndFormatMonthlyPayment($amount, $rate, $duration) {
        return number_format($this->calculateMonthlyPayment($amount, $rate, $duration), 2);
    }

    // Confirm a loan
    public function confirmLoan($id, $confirmed_by) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare(
            "UPDATE loans SET is_confirmed = TRUE, confirmed_by = ?, status = 'Active' 
             WHERE id = ? AND is_confirmed = FALSE"
        );
        $stmt->bind_param("si", $confirmed_by, $id);
        return $stmt->execute();
    }
}
?>