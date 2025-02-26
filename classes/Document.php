<?php
require_once __DIR__ . '/Database.php';

class Document {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Add a document
    public function addDocument($member_id, $document_type, $file_path, $notes, $upload_date) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("INSERT INTO documents (member_id, document_type, file_path, notes, upload_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $member_id, $document_type, $file_path, $notes, $upload_date);
        return $stmt->execute();
    }

    // Get documents for a member (for user dashboard)
    public function getDocumentsByMemberId($member_id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM documents WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>