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
<<<<<<< HEAD
            $this->validateMemberData([
                'member_id' => $member_id,
                'full_name' => $full_name,
                'nic_number' => $nic_number,
                'contact_number' => $contact_number,
                'email' => $email,
                'occupation' => $occupation,
                'contribution_amount' => $contribution_amount
            ]);

            // Check for duplicate member_id and NIC
            if (!$this->isMemberIdUnique($member_id)) {
                throw new Exception("Member ID already exists.");
            }
            if (!$this->isNicUnique($nic_number)) {
                throw new Exception("NIC number already registered.");
=======
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
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare(
                "INSERT INTO members (member_id, full_name, date_of_birth, gender, nic_number, address, contact_number, email, occupation, date_of_joining, membership_type, contribution_amount, payment_status, member_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

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

            $member_id = $stmt->insert_id;
            $stmt->close();
<<<<<<< HEAD

            error_log("Member added successfully: member_id=$member_id, full_name=$full_name");
            return $member_id;
=======
            error_log("Member added successfully: member_id=$member_id, full_name=$full_name");
            return true;
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
        } catch (Exception $e) {
            error_log("Error adding member: " . $e->getMessage() . " | Input: member_id=$member_id, full_name=$full_name");
            throw $e;
        }
    }

<<<<<<< HEAD
    public function updateMember($id, $data) {
        try {
            if (!is_numeric($id) || $id <= 0) {
                throw new Exception("Invalid member ID.");
            }

            // Validate input data
            $this->validateMemberData($data);

            $conn = $this->db->getConnection();
            
            // Build update query dynamically based on provided data
            $updates = [];
            $types = '';
            $values = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, ['full_name', 'contact_number', 'email', 'address', 'occupation', 
                                  'membership_type', 'payment_status', 'member_status', 'contribution_amount'])) {
                    $updates[] = "$key = ?";
                    $types .= 's';
                    $values[] = $value;
                }
            }
            
            if (empty($updates)) {
                throw new Exception("No valid fields to update.");
            }

            $types .= 'i'; // for the WHERE id = ? clause
            $values[] = $id;

            $query = "UPDATE members SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param($types, ...$values);
            $result = $stmt->execute();
            
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
            error_log("Member updated successfully: id=$id");
            return true;
        } catch (Exception $e) {
            error_log("Error updating member: " . $e->getMessage() . " | ID: $id");
            throw $e;
        }
    }

    public function deleteMember($id) {
        try {
            if (!is_numeric($id) || $id <= 0) {
                throw new Exception("Invalid member ID.");
            }

            $conn = $this->db->getConnection();
            
            // Start transaction
            $conn->begin_transaction();

            try {
                // Delete related records first
                $tables = ['family_details', 'payments', 'incidents', 'loans', 'documents'];
                foreach ($tables as $table) {
                    $stmt = $conn->prepare("DELETE FROM $table WHERE member_id = ?");
                    if (!$stmt) {
                        throw new Exception("Prepare failed for $table: " . $conn->error);
                    }
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $stmt->close();
                }

                // Delete the member
                $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed for members: " . $conn->error);
                }
                $stmt->bind_param("i", $id);
                $result = $stmt->execute();
                $stmt->close();

                if (!$result) {
                    throw new Exception("Failed to delete member.");
                }

                // Commit transaction
                $conn->commit();
                error_log("Member deleted successfully: id=$id");
                return true;
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            error_log("Error deleting member: " . $e->getMessage() . " | ID: $id");
            throw $e;
        }
    }

    public function getAllMembers($filters = []) {
        try {
            $conn = $this->db->getConnection();
            
            $query = "SELECT * FROM members WHERE 1=1";
            $params = [];
            $types = "";
            
            if (!empty($filters)) {
                foreach ($filters as $key => $value) {
                    if (in_array($key, ['membership_type', 'payment_status', 'member_status'])) {
                        $query .= " AND $key = ?";
                        $params[] = $value;
                        $types .= "s";
                    }
                }
            }
            
            $query .= " ORDER BY CAST(member_id AS UNSIGNED) ASC";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
            } else {
                $result = $conn->query($query);
            }
            
=======
    public function getAllMembers() {
        try {
            $conn = $this->db->getConnection();
            $result = $conn->query("SELECT * FROM members ORDER BY CAST(member_id AS UNSIGNED) ASC");
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            if (!$result) {
                throw new Exception("Query failed: " . $conn->error);
            }
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching members: " . $e->getMessage());
            return [];
        }
    }

    public function getMemberById($id) {
        try {
<<<<<<< HEAD
            if (!is_numeric($id) || $id <= 0) {
                throw new Exception("Invalid member ID.");
=======
            // Allow string IDs but ensure it's numeric and positive
            if (!is_numeric($id) || (int)$id <= 0) {
                throw new Exception("Invalid member ID: $id");
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            // Bind as integer after casting
            $id = (int)$id;
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$result) {
                error_log("No member found for ID: $id");
                return null;
            }

            error_log("Fetched member for ID: $id, Name: " . ($result['full_name'] ?? 'N/A'));
            return $result;
        } catch (Exception $e) {
            error_log("Error fetching member by ID: $id, Message: " . $e->getMessage());
            return null;
        }
    }

    public function getMemberByMemberId($member_id) {
        try {
            if (empty($member_id)) {
<<<<<<< HEAD
                throw new Exception("Invalid member ID.");
=======
                throw new Exception("Invalid member ID: $member_id");
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("s", $member_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$result) {
                error_log("No member found for member_id: $member_id");
                return null;
            }

            error_log("Fetched member for member_id: $member_id, Name: " . ($result['full_name'] ?? 'N/A'));
            return $result;
        } catch (Exception $e) {
            error_log("Error fetching member by member_id: $member_id, Message: " . $e->getMessage());
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

<<<<<<< HEAD
    private function validateMemberData($data) {
        $validations = [
            'member_id' => ['max' => 10, 'required' => true],
            'full_name' => ['max' => 100, 'required' => true],
            'nic_number' => ['max' => 20, 'required' => true],
            'contact_number' => ['max' => 15, 'required' => true],
            'email' => ['max' => 100, 'required' => false],
            'occupation' => ['max' => 100, 'required' => false],
            'contribution_amount' => ['numeric' => true, 'min' => 0, 'required' => true]
        ];
=======
    public function updateMember($id, $data) {
        try {
            if (!is_int($id) || $id <= 0) {
                throw new Exception("Invalid member ID: $id");
            }
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0

        foreach ($validations as $field => $rules) {
            if (isset($data[$field])) {
                $value = $data[$field];
                
                if ($rules['required'] && empty($value)) {
                    throw new Exception("$field is required.");
                }
                
                if (!empty($value)) {
                    if (isset($rules['max']) && strlen($value) > $rules['max']) {
                        throw new Exception("$field exceeds maximum length of {$rules['max']} characters.");
                    }
                    
                    if (isset($rules['numeric']) && $rules['numeric'] && !is_numeric($value)) {
                        throw new Exception("$field must be a number.");
                    }
                    
                    if (isset($rules['min']) && is_numeric($value) && $value < $rules['min']) {
                        throw new Exception("$field must be greater than or equal to {$rules['min']}.");
                    }
                }
            }
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
            
            // Get total members
            $result = $conn->query("SELECT COUNT(*) as total FROM members");
            $stats['total'] = $result->fetch_assoc()['total'];
            
            // Get members by status
            $result = $conn->query("SELECT payment_status, COUNT(*) as count FROM members GROUP BY payment_status");
            while ($row = $result->fetch_assoc()) {
                $stats[strtolower($row['payment_status'])] = $row['count'];
            }
<<<<<<< HEAD
            
            // Get members by type
            $result = $conn->query("SELECT membership_type, COUNT(*) as count FROM members GROUP BY membership_type");
            while ($row = $result->fetch_assoc()) {
                $stats['by_type'][$row['membership_type']] = $row['count'];
=======

            $set_clause = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            $types = str_repeat('s', count($fields) - 1) . 'd';
            $values = array_values($fields);
            $values[] = $id;

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("UPDATE members SET $set_clause WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            }
            
            return $stats;
        } catch (Exception $e) {
<<<<<<< HEAD
            error_log("Error getting member stats: " . $e->getMessage());
=======
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
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            return null;
        }
    }
}
?>