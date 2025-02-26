<?php
require_once '../classes/Database.php';

class Incident {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function addIncident($member_id, $type, $date, $payment) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("INSERT INTO incidents (member_id, type, date, payment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issd", $member_id, $type, $date, $payment);
        return $stmt->execute();
    }
}
?>