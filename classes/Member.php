<?php
require_once __DIR__ . '/Database.php';

class Member {
    private $db;

    public function __construct() {
        try {
            $this->db = new Database();
        } catch (Exception $e) {
            error_log("Database initialization failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function addMember($member_id, $full_name, $date_of_birth, $gender, $nic_number, $address, $contact_number, $email, $occupation, $date_of_joining, $membership_type, $contribution_amount, $payment_status, $member_status) {
        try {
            // Input validation
            if (strlen($member_id) > 10) {
                throw new Exception("Member ID exceeds 10 characters.");
            }
            if (strlen($full_name) > 100) {
                throw new Exception("Full name exceeds 100 characters.");
            }
            if (strlen($nic_number) > 20) {
                throw new Exception("NIC number exceeds 20 characters.");
            }
            if (strlen($contact_number) > 15) {
                throw new Exception("Contact number exceeds 15 characters.");
            }
            if ($email && strlen($email) > 100) {
                throw new Exception("Email exceeds 100 characters.");
            }
            if ($occupation && strlen($occupation) > 100) {
                throw new Exception("Occupation exceeds 100 characters.");
            }
            if (!is_numeric($contribution_amount) || $contribution_amount < 0) {
                throw new Exception("Contribution amount must be a non-negative number.");
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare(
                "INSERT INTO members (member_id, full_name, date_of_birth, gender, nic_number, address, contact_number, email, occupation, date_of_joining, membership_type, contribution_amount, payment_status, member_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            // Corrected bind_param with 14 parameters
            $stmt->bind_param(
                "ssssssssssssss",
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

            $result = $stmt->execute();
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
            error_log("Member added successfully: member_id=$member_id, full_name=$full_name");
            return true;
        } catch (Exception $e) {
            error_log("Error adding member: " . $e->getMessage() . " | Input: member_id=$member_id, full_name=$full_name");
            throw $e; // Re-throw to let add_member.php handle the error
        }
    }

    public function getAllMembers() {
        try {
            $conn = $this->db->getConnection();
            $result = $conn->query("SELECT * FROM members ORDER BY CAST(member_id AS UNSIGNED) ASC");
            if (!$result) {
                throw new Exception("Query failed: " . $conn->error);
            }
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching all members: " . $e->getMessage());
            return [];
        }
    }

    public function getMemberById($id) {
        try {
            if (!is_int($id) || $id <= 0) {
                throw new Exception("Invalid member ID: $id");
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            return $result ?: null;
        } catch (Exception $e) {
            error_log("Error fetching member by ID: " . $e->getMessage());
            return null;
        }
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

    public function generateMemberId() {
        try {
            $conn = $this->db->getConnection();
            $result = $conn->query("SELECT member_id FROM members ORDER BY CAST(member_id AS UNSIGNED) DESC LIMIT 1");
            if (!$result) {
                throw new Exception("Query failed: " . $conn->error);
            }

            $last_member = $result->fetch_assoc();
            $last_num = $last_member ? (int)$last_member['member_id'] : 0;
            return (string)($last_num + 1);
        } catch (Exception $e) {
            error_log("Error generating member ID: " . $e->getMessage());
            return '1';
        }
    }

    public function isMemberIdUnique($member_id) {
        try {
            if (empty($member_id)) {
                throw new Exception("Member ID cannot be empty");
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) FROM members WHERE member_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("s", $member_id);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_row()[0];
            $stmt->close();

            return $count == 0;
        } catch (Exception $e) {
            error_log("Error checking member ID uniqueness: " . $e->getMessage());
            return false;
        }
    }

    public function isNicUnique($nic_number) {
        try {
            if (empty($nic_number)) {
                throw new Exception("NIC number cannot be empty");
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) FROM members WHERE nic_number = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("s", $nic_number);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_row()[0];
            $stmt->close();

            return $count == 0;
        } catch (Exception $e) {
            error_log("Error checking NIC uniqueness: " . $e->getMessage());
            return false;
        }
    }

    public function updateMember($id, $data) {
        try {
            if (!is_int($id) || $id <= 0) {
                throw new Exception("Invalid member ID: $id");
            }

            $allowed_fields = [
                'full_name', 'date_of_birth', 'gender', 'nic_number', 'address',
                'contact_number', 'email', 'occupation', 'date_of_joining',
                'membership_type', 'contribution_amount', 'payment_status', 'member_status'
            ];
            $fields = array_intersect_key($data, array_flip($allowed_fields));
            if (empty($fields)) {
                throw new Exception("No valid fields provided for update");
            }

            $set_clause = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            $types = str_repeat('s', count($fields) - 1) . 'd';
            $values = array_values($fields);
            $values[] = $id;

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("UPDATE members SET $set_clause WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param($types, ...$values);
            $result = $stmt->execute();
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Error updating member: " . $e->getMessage());
            return false;
        }
    }

    public function deleteMember($id) {
        try {
            if (!is_int($id) || $id <= 0) {
                throw new Exception("Invalid member ID: $id");
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("i", $id);
            $result = $stmt->execute();
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Error deleting member: " . $e->getMessage());
            return false;
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