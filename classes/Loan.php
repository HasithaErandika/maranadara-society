<?php
require_once __DIR__ . '/Database.php';

class Loan {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function getLoansByMemberId($member_id) {
        $conn = $this->db->getConnection();
        if (!$conn) {
            error_log("Database connection failed in getLoansByMemberId");
            return false;
        }

        $stmt = $conn->prepare("SELECT id, amount, date_issued FROM loans WHERE member_id = ? AND status = 'active'");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }

        $stmt->bind_param("i", $member_id);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            return false;
        }

        $result = $stmt->get_result();
        if (!$result) {
            error_log("Get result failed: " . $stmt->error);
            return false;
        }

        $loans = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $loans;
    }

    public function getTotalLoans() {
        $conn = $this->db->getConnection();
        if (!$conn) {
            error_log("Database connection failed in getTotalLoans");
            return 0;
        }

        $result = $conn->query("SELECT SUM(amount) as total FROM loans WHERE status = 'active'");
        if (!$result) {
            error_log("Query failed in getTotalLoans: " . $conn->error);
            return 0;
        }

        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }

    public function getAllLoans() {
        $conn = $this->db->getConnection();
        if (!$conn) {
            error_log("Database connection failed in getAllLoans");
            return [];
        }

        $query = "
            SELECT 
                l.*, 
                COALESCE(SUM(p.amount), 0) as total_paid
            FROM loans l
            LEFT JOIN payments p ON l.id = p.loan_id 
                AND p.payment_type = 'Loan Settlement' 
                AND p.is_confirmed = TRUE
            GROUP BY l.id
        ";
        $result = $conn->query($query);
        if (!$result) {
            error_log("Query failed in getAllLoans: " . $conn->error);
            return [];
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>