<?php
require_once __DIR__ . '/Database.php';

class Family {
    private $db;

    public function __construct() {
        try {
            $this->db = new Database();
        } catch (Exception $e) {
            error_log("Database initialization failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function addFamilyDetails($member_id, $spouse_name, $children_info, $dependents_info) {
        try {
            if (!is_int($member_id) || $member_id <= 0) {
                throw new Exception("Invalid member ID: $member_id");
            }
            if ($spouse_name && strlen($spouse_name) > 100) {
                throw new Exception("Spouse name exceeds 100 characters.");
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("INSERT INTO family_details (member_id, spouse_name, children_info, dependents_info) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("isss", $member_id, $spouse_name, $children_info, $dependents_info);
            $result = $stmt->execute();
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Error adding family details for member_id=$member_id: " . $e->getMessage());
            return false;
        }
    }

    public function getFamilyDetailsByMemberId($member_id) {
        try {
            if (!is_int($member_id) || $member_id <= 0) {
                throw new Exception("Invalid member ID: $member_id");
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT * FROM family_details WHERE member_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            return $result ?: null;
        } catch (Exception $e) {
            error_log("Error fetching family details: " . $e->getMessage());
            return null;
        }
    }
}
?>