<?php
require_once __DIR__ . '/Database.php';

class Loan {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function getLoansByMemberId($member_id) {
        $conn = $this->db->getConnection();
        if (!$conn) return false;

        $stmt = $conn->prepare("
            SELECT id, amount, interest_rate, duration, monthly_payment, 
                   total_payable, start_date, end_date, status, remarks, confirmed_by
            FROM loans 
            WHERE member_id = ? AND status != 'Settled'
        ");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $loans = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $loans;
    }

    public function getTotalLoans() {
        $conn = $this->db->getConnection();
        if (!$conn) return 0;

        $result = $conn->query("SELECT SUM(amount) as total FROM loans WHERE status IN ('Pending', 'Applied')");
        return $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
    }

    public function getAllLoans($member_id = null) {
        $conn = $this->db->getConnection();
        if (!$conn) return [];

        $query = "
            SELECT 
                l.*, 
                COALESCE(SUM(p.amount), 0) as total_paid
            FROM loans l
            LEFT JOIN payments p ON l.id = p.loan_id 
                AND p.payment_type = 'Loan Settlement' 
                AND p.is_confirmed = TRUE
            " . ($member_id ? "WHERE l.member_id = ?" : "") . "
            GROUP BY l.id
            ORDER BY l.start_date DESC
        ";

        if ($member_id) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function addLoan($member_id, $amount, $interest_rate, $duration, $start_date, $remarks = null, $monthly_payment = null, $total_payable = null, $end_date = null) {
        $conn = $this->db->getConnection();
        if (!$conn) return false;

        $check_stmt = $conn->prepare("SELECT id FROM loans WHERE member_id = ? AND status IN ('Applied', 'Pending')");
        $check_stmt->bind_param("i", $member_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            return false;
        }

        // Use provided values or calculate if not provided
        $monthly_payment = $monthly_payment ?? $this->calculateMonthlyPayment($amount, $interest_rate, $duration);
        $total_payable = $total_payable ?? ($amount + ($amount * ($interest_rate / 100)));
        $end_date = $end_date ?? date('Y-m-d', strtotime($start_date . " + $duration months"));

        $stmt = $conn->prepare("
            INSERT INTO loans (member_id, amount, interest_rate, duration, monthly_payment, 
                              total_payable, start_date, end_date, status, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Applied', ?)
        ");
        $stmt->bind_param("iddiddsss", $member_id, $amount, $interest_rate, $duration,
            $monthly_payment, $total_payable, $start_date, $end_date, $remarks);

        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function approveLoan($loan_id, $approved_by) {
        $conn = $this->db->getConnection();
        if (!$conn) return false;

        $stmt = $conn->prepare("
            UPDATE loans 
            SET status = 'Pending', 
                is_confirmed = 1, 
                confirmed_by = ? 
            WHERE id = ? AND status = 'Applied'
        ");
        $stmt->bind_param("si", $approved_by, $loan_id);
        $success = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        return $success;
    }

    public function settleLoan($loan_id, $settled_by) {
        $conn = $this->db->getConnection();
        if (!$conn) return false;

        $loan = $this->getLoanById($loan_id);
        if ($loan['total_paid'] < $loan['total_payable']) return false;

        $stmt = $conn->prepare("
            UPDATE loans 
            SET status = 'Settled', 
                is_confirmed = 1,
                confirmed_by = ? 
            WHERE id = ? AND status = 'Pending'
        ");
        $stmt->bind_param("si", $settled_by, $loan_id);
        $success = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        return $success;
    }

    public function calculateLoanBreakdown($amount, $interest_rate, $duration, $start_date) {
        $total_interest = $amount * ($interest_rate / 100); // Simple interest for the full term
        $total_payable = $amount + $total_interest;
        $monthly_payment = $total_payable / $duration;
        $end_date = date('Y-m-d', strtotime($start_date . " + $duration months"));

        return [
            'amount' => number_format($amount, 2),
            'monthly_payment' => number_format($monthly_payment, 2),
            'total_payable' => number_format($total_payable, 2),
            'interest' => number_format($total_interest, 2),
            'end_date' => $end_date
        ];
    }

    private function getLoanById($loan_id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT l.*, COALESCE(SUM(p.amount), 0) as total_paid
            FROM loans l
            LEFT JOIN payments p ON l.id = p.loan_id 
                AND p.payment_type = 'Loan Settlement' 
                AND p.is_confirmed = TRUE
            WHERE l.id = ?
            GROUP BY l.id
        ");
        $stmt->bind_param("i", $loan_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    private function calculateMonthlyPayment($amount, $interest_rate, $duration) {
        $total_interest = $amount * ($interest_rate / 100);
        $total_payable = $amount + $total_interest;
        return $total_payable / $duration;
    }
}
?>