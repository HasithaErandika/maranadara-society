<?php
require_once __DIR__ . '/Database.php';

class Payment {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    private function generateReceiptNumber($payment_type, $date, $conn) {
        $prefix = $payment_type === 'Loan Settlement' ? 'PL-' : ($payment_type === 'Membership Fee' ? 'PM-' : '');
        if (!$prefix) return null;
        $date_part = date('ymd', strtotime($date));
        $like = $prefix . $date_part . '%';
        $stmt = $conn->prepare("SELECT MAX(receipt_number) as max_receipt FROM payments WHERE receipt_number LIKE ?");
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $max = $stmt->get_result()->fetch_assoc()['max_receipt'];
        $stmt->close();
        $seq = 1;
        if ($max) {
            $seq = intval(substr($max, -6)) + 1;
        }
        return $prefix . $date_part . str_pad($seq, 6, '0', STR_PAD_LEFT);
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
                $receipt_number = $this->generateReceiptNumber($payment_type, $date, $conn);
                $stmt = $conn->prepare(
                    "INSERT INTO payments (member_id, amount, date, payment_mode, payment_type, receipt_number, remarks, is_confirmed, confirmed_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("idsssssis", $member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $is_confirmed, $confirmed_by);
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

        // Get all pending loans
        $stmt = $conn->prepare("
            SELECT l.*
            FROM loans l
            WHERE l.status = 'Pending' AND l.is_confirmed = TRUE
        ");
        $stmt->execute();
        $pending_loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $count = 0;
        $payment_mode = 'Cash';
        $payment_type = 'Loan Settlement';
        $is_confirmed = false;
        $confirmed_by = null;
        $fixed_amount = 4500.00;

        foreach ($pending_loans as $loan) {
            $member_id = $loan['member_id'];
            $loan_id = $loan['id'];
            $start_date = $loan['start_date'];

            // Find the last payment month for this loan
            $stmt2 = $conn->prepare("
                SELECT MAX(date) as last_payment_date
                FROM payments
                WHERE loan_id = ? AND payment_type = 'Loan Settlement'
            ");
            $stmt2->bind_param("i", $loan_id);
            $stmt2->execute();
            $last_payment_date = $stmt2->get_result()->fetch_assoc()['last_payment_date'];
            $stmt2->close();

            $from = $last_payment_date ? date('Y-m-01', strtotime($last_payment_date . ' +1 month')) : date('Y-m-01', strtotime($start_date));
            $to = date('Y-m-01', strtotime($date));

            // Add a payment for each month from $from to $to (inclusive if same month)
            while (strtotime($from) <= strtotime($to)) {
                // Check if payment already exists for this month
                $stmt3 = $conn->prepare("
                    SELECT COUNT(*) as exists_count
                    FROM payments
                    WHERE loan_id = ? AND payment_type = 'Loan Settlement' AND DATE_FORMAT(date, '%Y-%m') = ?
                ");
                $month_str = date('Y-m', strtotime($from));
                $stmt3->bind_param("is", $loan_id, $month_str);
                $stmt3->execute();
                $exists = $stmt3->get_result()->fetch_assoc()['exists_count'];
                $stmt3->close();

                if ($exists == 0) {
                    $remarks = 'Auto loan settlement for ' . $month_str;
                    $receipt_number = $this->generateReceiptNumber($payment_type, $from, $conn);
                    $stmt4 = $conn->prepare(
                        "INSERT INTO payments (member_id, amount, date, payment_mode, payment_type, receipt_number, remarks, loan_id, is_confirmed, confirmed_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt4->bind_param("idssssssii", $member_id, $fixed_amount, $from, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id, $is_confirmed, $confirmed_by);
                    if ($stmt4->execute()) {
                        $count++;
                    } else {
                        error_log("Failed to auto-add loan settlement for loan $loan_id: " . $stmt4->error);
                    }
                    $stmt4->close();
                }
                $from = date('Y-m-01', strtotime($from . ' +1 month'));
            }
        }

        return $count;
    }

    /**
     * Add a new payment to the database
     * @param int $member_id The member ID
     * @param float $amount The payment amount
     * @param string $date The payment date (YYYY-MM-DD)
     * @param string $payment_mode The payment mode (e.g., Cash, Bank Transfer, Cheque)
     * @param string $payment_type The payment type (e.g., Society Issued, Membership Fee, Loan Settlement)
     * @param string|null $receipt_number The receipt number, if any
     * @param string|null $remarks Additional remarks, if any
     * @param int|null $loan_id The loan ID for Loan Settlement, if applicable
     * @return bool True on success, throws exception on failure
     * @throws Exception If validation fails or database insertion fails
     */
    public function addPayment($member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number = null, $remarks = null, $loan_id = null) {
        $conn = $this->db->getConnection();

        // Validate inputs
        if (!is_numeric($member_id) || $member_id <= 0) {
            throw new Exception("Invalid member ID.");
        }
        if (!is_numeric($amount) || $amount <= 0) {
            throw new Exception("Amount must be greater than 0.");
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception("Invalid date format. Use YYYY-MM-DD.");
        }
        $allowed_modes = ['Cash', 'Bank Transfer', 'Cheque'];
        if (!in_array($payment_mode, $allowed_modes)) {
            throw new Exception("Invalid payment mode. Allowed: " . implode(', ', $allowed_modes) . ".");
        }
        $allowed_types = ['Society Issued', 'Membership Fee', 'Loan Settlement'];
        if (!in_array($payment_type, $allowed_types)) {
            throw new Exception("Invalid payment type. Allowed: " . implode(', ', $allowed_types) . ".");
        }

        // Verify member exists
        $stmt = $conn->prepare("SELECT id FROM members WHERE id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            throw new Exception("Member ID $member_id does not exist.");
        }
        $stmt->close();

        // Handle loan_id for Loan Settlement
        if ($payment_type === 'Loan Settlement') {
            if (!is_numeric($loan_id) || $loan_id <= 0) {
                throw new Exception("Loan ID is required for Loan Settlement.");
            }
            // Verify loan exists, belongs to member, and is eligible
            $stmt = $conn->prepare("SELECT member_id, status, is_confirmed FROM loans WHERE id = ?");
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $stmt->close();
                throw new Exception("Loan ID $loan_id does not exist.");
            }
            $loan = $result->fetch_assoc();
            if ($loan['member_id'] !== $member_id) {
                $stmt->close();
                throw new Exception("Loan ID $loan_id does not belong to member ID $member_id.");
            }
            if ($loan['status'] !== 'Pending' || !$loan['is_confirmed']) {
                $stmt->close();
                throw new Exception("Loan ID $loan_id is not eligible for settlement.");
            }
            $stmt->close();
        } else {
            $loan_id = null; // Ensure loan_id is NULL for non-loan payments
        }

        // Convert empty strings to NULL for nullable fields
        $receipt_number = empty($receipt_number) ? null : $receipt_number;
        $remarks = empty($remarks) ? null : $remarks;

        // Generate receipt number if needed
        if (empty($receipt_number) && in_array($payment_type, ['Loan Settlement', 'Membership Fee'])) {
            $receipt_number = $this->generateReceiptNumber($payment_type, $date, $conn);
        }

        // Insert payment
        $stmt = $conn->prepare(
            "INSERT INTO payments (member_id, amount, date, payment_mode, payment_type, receipt_number, remarks, loan_id, is_confirmed, confirmed_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, FALSE, NULL)"
        );
        $stmt->bind_param("idsssssi", $member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            error_log("Failed to add payment for member ID $member_id: $error");
            throw new Exception("Database error: $error");
        }

        $stmt->close();
        return true;
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