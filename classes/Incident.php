<?php
require_once __DIR__ . '/Database.php';

class Incident {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Add an incident
    public function addIncident($incident_id, $member_id, $incident_type, $incident_datetime, $reporter_name, $reporter_member_id, $remarks) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("INSERT INTO incidents (incident_id, member_id, incident_type, incident_datetime, reporter_name, reporter_member_id, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisssss", $incident_id, $member_id, $incident_type, $incident_datetime, $reporter_name, $reporter_member_id, $remarks);
        return $stmt->execute();
    }

    // Get incidents for a member (for user dashboard)
    public function getIncidentsByMemberId($member_id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM incidents WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get all incidents (for admin incidents.php)
    public function getAllIncidents() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT * FROM incidents");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Generate unique incident_id
    public function generateIncidentId() {
        $conn = $this->db->getConnection();
        $last_incident = $conn->query("SELECT incident_id FROM incidents ORDER BY id DESC LIMIT 1")->fetch_assoc();
        $last_num = $last_incident ? (int)substr($last_incident['incident_id'], 4) : 0;
        return 'INC-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
    }
}
?>