<?php
require_once __DIR__ . '/Database.php';

class Member {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function generateMemberId() {
        $conn = $this->db->getConnection();
        $year = date('Y');
        $stmt = $conn->prepare("SELECT member_id FROM members WHERE member_id LIKE ? ORDER BY member_id DESC LIMIT 1");
        $pattern = $year . '%';
        $stmt->bind_param("s", $pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $last_id = $result->fetch_assoc()['member_id'];
            $sequence = intval(substr($last_id, -4)) + 1;
        } else {
            $sequence = 1;
        }
        return $year . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function isMemberIdUnique($member_id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM members WHERE member_id = ?");
        $stmt->bind_param("s", $member_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] == 0;
    }

    public function isNicUnique($nic_number) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM members WHERE nic_number = ?");
        $stmt->bind_param("s", $nic_number);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] == 0;
    }

    public function addMember(
        $member_id,
        $full_name,
        $date_of_birth,
        $gender,
        $nic_number,
        $address,
        $contact_number,
        $email,
        $occupation,
        $date_of_joining,
        $membership_type,
        $contribution_amount,
        $payment_status,
        $member_status
    ) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            INSERT INTO members (
                member_id, full_name, date_of_birth, gender, nic_number,
                address, contact_number, email, occupation, date_of_joining,
                membership_type, contribution_amount, payment_status, member_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssssssssdss",
            $member_id,
            $full_name,
            $date_of_birth,
            $gender,
            $nic_number,
            $address,
            $contact_number,
            $email,
            $occupation,
            $date_of_joining,
            $membership_type,
            $contribution_amount,
            $payment_status,
            $member_status
        );
        if (!$stmt->execute()) {
            throw new Exception("Failed to add member: " . $stmt->error);
        }
        return $conn->insert_id;
    }

    public function getMemberById($id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getMemberByMemberId($member_id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
        $stmt->bind_param("s", $member_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function updateMember($id, $data) {
        $conn = $this->db->getConnection();
        $fields = [];
        $types = "";
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $types .= "s";
            $values[] = $value;
        }
        $values[] = $id;
        $types .= "i";
        $sql = "UPDATE members SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        return $stmt->execute();
    }

    public function deleteMember($id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getAllMembers() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT * FROM members ORDER BY date_of_joining DESC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function searchMembers($query) {
        $conn = $this->db->getConnection();
        $search = "%$query%";
        $stmt = $conn->prepare("
            SELECT * FROM members 
            WHERE member_id LIKE ? 
            OR full_name LIKE ? 
            OR nic_number LIKE ? 
            OR contact_number LIKE ?
            ORDER BY date_of_joining DESC
        ");
        $stmt->bind_param("ssss", $search, $search, $search, $search);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getTotalMembers() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT COUNT(*) as total FROM members");
        return $result->fetch_assoc()['total'];
    }

    public function getMemberByUsername($username) {
        try {
            if (empty($username)) {
                throw new Exception("Username cannot be empty");
            }
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare(
                "SELECT m.* FROM members m 
                JOIN users u ON m.id = u.member_id 
                WHERE u.username = ?"
            );
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $result ?: null;
        } catch (Exception $e) {
            error_log("Error fetching member by username: " . $e->getMessage());
            return null;
        }
    }

    public function getMemberStats() {
        try {
            $conn = $this->db->getConnection();
            $stats = [
                'total' => 0,
                'active' => 0,
                'pending' => 0,
                'inactive' => 0,
                'by_type' => [
                    'Individual' => 0,
                    'Family' => 0,
                    'Senior Citizen' => 0
                ]
            ];
            $result = $conn->query("SELECT COUNT(*) as total FROM members");
            $stats['total'] = $result->fetch_assoc()['total'];
            $result = $conn->query("SELECT payment_status, COUNT(*) as count FROM members GROUP BY payment_status");
            while ($row = $result->fetch_assoc()) {
                $stats[strtolower($row['payment_status'])] = $row['count'];
            }
            $result = $conn->query("SELECT membership_type, COUNT(*) as count FROM members GROUP BY membership_type");
            while ($row = $result->fetch_assoc()) {
                $stats['by_type'][$row['membership_type']] = $row['count'];
            }
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting member stats: " . $e->getMessage());
            return null;
        }
    }

    public function getLastMember() {
        try {
            $conn = $this->db->getConnection();
            $result = $conn->query("SELECT * FROM members ORDER BY id DESC LIMIT 1");
            if (!$result) {
                throw new Exception("Query failed: " . $conn->error);
            }
            return $result->fetch_assoc() ?: null;
        } catch (Exception $e) {
            error_log("Error fetching last member: " . $e->getMessage());
            return null;
        }
    }
}
?>
