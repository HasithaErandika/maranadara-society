<?php
require_once __DIR__ . '/Database.php';

class Payment {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function addPayment($member_id, $amount, $date) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("INSERT INTO payments (member_id, amount, date) VALUES (?, ?, ?)");
        $stmt->bind_param("ids", $member_id, $amount, $date);
        return $stmt->execute();
    }
}
?>