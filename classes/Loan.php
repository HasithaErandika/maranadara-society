<?php
require_once __DIR__ . '/Database.php';

class Loan {
    private $db;

    public function __construct() {
        try {
            $this->db = new Database();
        } catch (Exception $e) {
            error_log("Database initialization failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function getConfirmedPendingLoansByMemberId($member_id) {
        $conn = $this->db->getConnection();
        if (!$conn) {
            error_log("Failed to connect to database in getConfirmedPendingLoansByMemberId");
            return [];
        }

        $stmt = $conn->prepare("
            SELECT id, amount, monthly_payment
            FROM loans 
            WHERE member_id = ? 
            AND status = 'Pending' 
            AND is_confirmed = TRUE
            ORDER BY start_date ASC
        ");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $loans = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $loans;
    }

    public function getLoansByMemberId($member_id) {
        $conn = $this->db->getConnection();
        if (!$conn) {
            error_log("Failed to connect to database in getLoansByMemberId");
            return [];
        }

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
        if (!$conn) {
            error_log("Failed to connect to database in getTotalLoans");
            return 0;
        }

        $result = $conn->query("SELECT SUM(amount) as total FROM loans WHERE status IN ('Pending', 'Applied')");
        if (!$result) {
            error_log("Query failed in getTotalLoans: " . $conn->error);
            return 0;
        }
        return $result->fetch_assoc()['total'] ?? 0;
    }

    public function getAllLoans($member_id = null, $page = 1, $limit = 10) {
        $conn = $this->db->getConnection();
        if (!$conn) {
            error_log("Failed to connect to database in getAllLoans");
            return [];
        }

        $offset = ($page - 1) * $limit;
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
            LIMIT ? OFFSET ?
        ";

        if ($member_id) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iii", $member_id, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
        }

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function addLoan($member_id, $amount, $interest_rate, $duration, $start_date, $remarks = null, $monthly_payment = null, $total_payable = null, $end_date = null) {
        $conn = $this->db->getConnection();
        if (!$conn) {
            error_log("Failed to connect to database in addLoan");
            return false;
        }

        $check_stmt = $conn->prepare("SELECT id FROM loans WHERE member_id = ? AND status IN ('Applied', 'Pending')");
        $check_stmt->bind_param("i", $member_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $check_stmt->close();
            return false;
        }
        $check_stmt->close();

        $monthly_payment = $monthly_payment ?? $this->calculateMonthlyPayment($amount, $interest_rate, $duration);
        $total_payable = $total_payable ?? ($monthly_payment * $duration);
        $end_date = $end_date ?? date('Y-m-d', strtotime($start_date . " + $duration months"));

        $stmt = $conn->prepare("
            INSERT INTO loans (member_id, amount, interest_rate, duration, monthly_payment, 
                              total_payable, start_date, end_date, status, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Applied', ?)
        ");
        $stmt->bind_param("iddiddsss", $member_id, $amount, $interest_rate, $duration,
            $monthly_payment, $total_payable, $start_date, $end_date, $remarks);

        $success = $stmt->execute();
        if (!$success) {
            error_log("Failed to add loan for member $member_id: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }

    public function approveLoan($loan_id, $user_id) {
        $conn = $this->db->getConnection();
        if (!$conn) {
            error_log("Failed to connect to database in approveLoan");
            return false;
        }

        $stmt = $conn->prepare("
            UPDATE loans 
            SET status = 'Pending', 
                is_confirmed = 1, 
                confirmed_by = ? 
            WHERE id = ? AND status = 'Applied'
        ");
        $stmt->bind_param("si", $user_id, $loan_id);
        $success = $stmt->execute() && $stmt->affected_rows > 0;
        if (!$success) {
            error_log("Failed to approve loan $loan_id: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }

    public function settleLoan($loan_id, $user_id) {
        $conn = $this->db->getConnection();
        if (!$conn) {
            error_log("Failed to connect to database in settleLoan");
            return false;
        }

        $loan = $this->getLoanById($loan_id);
        if (!$loan || $loan['total_paid'] < $loan['total_payable']) {
            error_log("Cannot settle loan $loan_id: Insufficient payment or loan not found");
            return false;
        }

        $stmt = $conn->prepare("
            UPDATE loans 
            SET status = 'Settled', 
                is_confirmed = 1,
                confirmed_by = ? 
            WHERE id = ? AND status = 'Pending'
        ");
        $stmt->bind_param("si", $user_id, $loan_id);
        $success = $stmt->execute() && $stmt->affected_rows > 0;
        if (!$success) {
            error_log("Failed to settle loan $loan_id: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }

    public function calculateLoanBreakdown($amount, $interest_rate, $duration, $start_date) {
        $monthly_payment = $this->calculateMonthlyPayment($amount, $interest_rate, $duration);
        $total_payable = $monthly_payment * $duration;
        $total_interest = $total_payable - $amount;
        $end_date = date('Y-m-d', strtotime($start_date . " + $duration months"));

        return [
            'amount' => number_format($amount, 2),
            'monthly_payment' => number_format($monthly_payment, 2),
            'total_payable' => number_format($total_payable, 2),
            'interest' => number_format($total_interest, 2),
            'end_date' => $end_date
        ];
    }

    public function deleteLoan($id) {
        $conn = $this->db->getConnection();
        if (!$conn) {
            error_log("Failed to connect to database in deleteLoan");
            return false;
        }

        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("SELECT status FROM loans WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare status query: " . $conn->error);
            }
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute status query: " . $stmt->error);
            }
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$result || $result['status'] !== 'Applied') {
                throw new Exception("Loan not found or not in 'Applied' status");
            }

            $stmt = $conn->prepare("DELETE FROM loans WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare delete query: " . $conn->error);
            }
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete loan: " . $stmt->error);
            }
            $success = $stmt->affected_rows > 0;
            $stmt->close();

            $conn->commit();
            return $success;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error deleting loan ID $id: " . $e->getMessage());
            return false;
        }
    }

    public function getLoanById($loan_id) {
        $conn = $this->db->getConnection();
        if (!$conn) {
            error_log("Failed to connect to database in getLoanById");
            return null;
        }

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
        $monthly_rate = $interest_rate / 100 / 12;
        $months = $duration;
        if ($monthly_rate == 0) {
            return $amount / $months;
        }
        $monthly_payment = $amount * ($monthly_rate * pow(1 + $monthly_rate, $months)) / (pow(1 + $monthly_rate, $months) - 1);
        return round($monthly_payment, 2);
    }
}
?>