<?php
require_once '../classes/Database.php';

class Member {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function addMember($name, $address, $contact) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("INSERT INTO members (name, address, contact) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $address, $contact);
        return $stmt->execute();
    }

    public function getMembers() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT * FROM members");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>