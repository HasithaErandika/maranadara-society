<?php
require_once __DIR__ . '/Database.php';

class Family {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Add family details
    public function addFamilyDetails($member_id, $spouse_name, $children_info, $dependents_info) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("INSERT INTO family_details (member_id, spouse_name, children_info, dependents_info) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $member_id, $spouse_name, $children_info, $dependents_info);
        return $stmt->execute();
    }

    // Get family details for a member (for user dashboard)
    public function getFamilyDetailsByMemberId($member_id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM family_details WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
?>