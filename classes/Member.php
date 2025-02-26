<?php
require_once __DIR__ . '/Database.php';

class Member {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Add a new member
    public function addMember($member_id, $full_name, $date_of_birth, $gender, $nic_number, $address, $contact_number, $email, $occupation, $date_of_joining, $membership_type, $contribution_amount, $payment_status, $member_status) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("INSERT INTO members (member_id, full_name, date_of_birth, gender, nic_number, address, contact_number, email, occupation, date_of_joining, membership_type, contribution_amount, payment_status, member_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssssdds", $member_id, $full_name, $date_of_birth, $gender, $nic_number, $address, $contact_number, $email, $occupation, $date_of_joining, $membership_type, $contribution_amount, $payment_status, $member_status);
        return $stmt->execute();
    }

    // Get all members (for admin dashboard)
    public function getAllMembers() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT * FROM members");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get member by ID (for incident reporting or detailed views)
    public function getMemberById($id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Get member by username (for user dashboard)
    public function getMemberByUsername($username) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT m.* FROM members m JOIN users u ON m.id = u.member_id WHERE u.username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Generate unique member_id
    public function generateMemberId() {
        $conn = $this->db->getConnection();
        $last_member = $conn->query("SELECT member_id FROM members ORDER BY id DESC LIMIT 1")->fetch_assoc();
        $last_num = $last_member ? (int)substr($last_member['member_id'], 3) : 0;
        return 'MS-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
    }
}
?>