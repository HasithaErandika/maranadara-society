<?php
require_once __DIR__ . '/Database.php';

class Payment {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Automatically add membership fee entries for all active members
     * @param string $date The date for the payment (e.g., '2025-03-01')
     * @return int Number of entries added
     */
    public function autoAddMembershipFees($date) {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("SELECT id FROM members WHERE member_status = 'Active'");
        $stmt->execute();
        $result = $stmt->get_result();
        $active_members = $result->fetch_all(MYSQLI_ASSOC);

        $count = 0;
        $amount = 300.00;
        $payment_mode = 'Cash';
        $payment_type = 'Membership Fee';
        $remarks = 'Monthly Membership Payment';
        $is_confirmed = false;
        $confirmed_by = null;

        foreach ($active_members as $member) {
            $member_id = $member['id'];
            $month = date('Y-m', strtotime($date));
            $stmt = $conn->prepare("SELECT COUNT(*) FROM payments WHERE member_id = ? AND payment_type = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
            $stmt->bind_param("iss", $member_id, $payment_type, $month);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_row()[0];

            if ($exists == 0) {
                $stmt = $conn->prepare(
                    "INSERT INTO payments (member_id, amount, date, payment_mode, payment_type, remarks, is_confirmed, confirmed_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("idssssis", $member_id, $amount, $date, $payment_mode, $payment_type, $remarks, $is_confirmed, $confirmed_by);
                if ($stmt->execute()) {
                    $count++;
                } else {
                    error_log("Failed to auto-add membership fee for member $member_id: " . $stmt->error);
                }
            }
        }

        return $count;
    }

    /**
     * Automatically add loan settlement payments for pending loans
     * @param string $date The date for the payment (e.g., '2025-03-01')
     * @return int Number of entries added
     */
    public function autoAddLoanSettlements($date) {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("SELECT member_id, id AS loan_id, monthly_payment FROM loans WHERE status = 'Pending' AND is_confirmed = TRUE");
        $stmt->execute();
        $result = $stmt->get_result();
        $pending_loans = $result->fetch_all(MYSQLI_ASSOC);

        $count = 0;
        $payment_mode = 'Cash';
        $payment_type = 'Loan Settlement';
        $remarks = 'Monthly Loan Settlement Payment';
        $is_confirmed = false;
        $confirmed_by = null;

        foreach ($pending_loans as $loan) {
            $member_id = $loan['member_id'];
            $loan_id = $loan['loan_id'];
            $amount = floatval($loan['monthly_payment']);

            $month = date('Y-m', strtotime($date));
            $stmt = $conn->prepare("SELECT COUNT(*) FROM payments WHERE member_id = ? AND loan_id = ? AND payment_type = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
            $stmt->bind_param("iiss", $member_id, $loan_id, $payment_type, $month);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_row()[0];

            if ($exists == 0) {
                $stmt = $conn->prepare(
                    "INSERT INTO payments (member_id, amount, date, payment_mode, payment_type, remarks, loan_id, is_confirmed, confirmed_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("idssssisis", $member_id, $amount, $date, $payment_mode, $payment_type, $remarks, $loan_id, $is_confirmed, $confirmed_by);
                if ($stmt->execute()) {
                    $count++;
                } else {
                    error_log("Failed to auto-add loan settlement for loan $loan_id: " . $stmt->error);
                }
            }
        }

        return $count;
    }

    /**
     * Add a new payment to the database
     * @return bool True on success, false on failure with error logged
     */
    public function addPayment($member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id = null, $is_confirmed = false, $confirmed_by = null) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare(
            "INSERT INTO payments (member_id, amount, date, payment_mode, payment_type, receipt_number, remarks, loan_id, is_confirmed, confirmed_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("idsssssisis", $member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id, $is_confirmed, $confirmed_by);

        if ($stmt->execute()) {
            return true;
        } else {
            error_log("Failed to add payment: " . $stmt->error);
            return false;
        }
    }

    /**
     * Update an existing payment
     * @return bool True on success, false on failure
     */
    public function updatePayment($id, $member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id = null) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare(
            "UPDATE payments SET member_id = ?, amount = ?, date = ?, payment_mode = ?, payment_type = ?, receipt_number = ?, remarks = ?, loan_id = ? 
             WHERE id = ? AND is_confirmed = FALSE"
        );
        $stmt->bind_param("idsssssisi", $member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id, $id);

        if ($stmt->execute()) {
            return $stmt->affected_rows > 0;
        } else {
            error_log("Failed to update payment ID $id: " . $stmt->error);
            return false;
        }
    }

    /**
     * Delete a payment
     * @param int $id The payment ID
     * @return bool True on success, false if confirmed or on error
     */
    public function deletePayment($id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("DELETE FROM payments WHERE id = ? AND is_confirmed = FALSE");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            return $stmt->affected_rows > 0;
        } else {
            error_log("Failed to delete payment ID $id: " . $stmt->error);
            return false;
        }
    }

    /**
     * Get payments by member ID or all payments if member_id is null
     * @return array Array of payment records
     */
    public function getPaymentsByMemberId($member_id) {
        $conn = $this->db->getConnection();
        if ($member_id === null) {
            $result = $conn->query("SELECT * FROM payments");
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
        $stmt = $conn->prepare("SELECT * FROM payments WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Get all payments
     * @return array Array of all payment records
     */
    public function getAllPayments() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT * FROM payments");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Get payments by payment type
     * @return array Array of payment records for the given type
     */
    public function getPaymentsByType($payment_type) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_type = ? ORDER BY date DESC");
        $stmt->bind_param("s", $payment_type);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Get total confirmed membership fee payments
     * @return float Total amount
     */
    public function getTotalPayments() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_type = 'Membership Fee' AND is_confirmed = TRUE");
        return $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
    }

    /**
     * Get total confirmed society-issued payments
     * @return float Total amount
     */
    public function getTotalSocietyIssuedPayments() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_type = 'Society Issued' AND is_confirmed = TRUE");
        return $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
    }

    /**
     * Get total confirmed loan settlement payments
     * @return float Total amount
     */
    public function getTotalLoanSettlementPayments() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_type = 'Loan Settlement' AND is_confirmed = TRUE");
        return $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
    }

    /**
     * Confirm a payment
     * @return bool True on success, false if already confirmed or on error
     */
    public function confirmPayment($id, $confirmed_by = null) {
        $conn = $this->db->getConnection();
        $confirmed_by = $confirmed_by ?? $_SESSION['db_username'] ?? 'Unknown';
        $stmt = $conn->prepare("UPDATE payments SET is_confirmed = TRUE, confirmed_by = ? WHERE id = ? AND is_confirmed = FALSE");
        $stmt->bind_param("si", $confirmed_by, $id);
        if ($stmt->execute()) {
            return $stmt->affected_rows > 0;
        } else {
            error_log("Failed to confirm payment ID $id: " . $stmt->error);
            return false;
        }
    }
}
?>