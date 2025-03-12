<?php
require_once __DIR__ . '/Database.php';

class Member {
    private $db;

    /**
     * Member constructor.
     * Initializes the database connection.
     */
    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Add a new member to the database.
     *
     * @param string $member_id Unique member ID (e.g., MS-001)
     * @param string $full_name Full name of the member
     * @param string $date_of_birth Date of birth (YYYY-MM-DD)
     * @param string $gender Gender (Male, Female, Other)
     * @param string $nic_number National ID number
     * @param string $address Member's address
     * @param string $contact_number Contact number (e.g., +94771234567)
     * @param string|null $email Email address (optional)
     * @param string|null $occupation Occupation (optional)
     * @param string $date_of_joining Date of joining (YYYY-MM-DD)
     * @param string $membership_type Membership type (Individual, Family, Senior Citizen)
     * @param float $contribution_amount Monthly contribution amount
     * @param string $payment_status Payment status (Active, Pending, Inactive)
     * @param string $member_status Member status (Active, Deceased, Resigned)
     * @return bool True on success, false on failure
     * @throws Exception If database operation fails
     */
    public function addMember($member_id, $full_name, $date_of_birth, $gender, $nic_number, $address, $contact_number, $email, $occupation, $date_of_joining, $membership_type, $contribution_amount, $payment_status, $member_status) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare(
                "INSERT INTO members (member_id, full_name, date_of_birth, gender, nic_number, address, contact_number, email, occupation, date_of_joining, membership_type, contribution_amount, payment_status, member_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param(
                "sssssssssssdds",
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
            return true;
        } catch (Exception $e) {
            error_log("Error adding member: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve all members from the database.
     *
     * @return array Array of member records
     * @throws Exception If query fails
     */
    public function getAllMembers() {
        try {
            $conn = $this->db->getConnection();
            $result = $conn->query("SELECT * FROM members ORDER BY member_id ASC");
            if (!$result) {
                throw new Exception("Query failed: " . $conn->error);
            }
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching all members: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a member by their ID.
     *
     * @param int $id Member's database ID
     * @return array|null Member data or null if not found
     * @throws Exception If query fails
     */
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

    /**
     * Get a member by their associated username.
     *
     * @param string $username User's username
     * @return array|null Member data or null if not found
     * @throws Exception If query fails
     */
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

    /**
     * Generate a unique member ID (e.g., MS-001).
     *
     * @return string Generated member ID
     * @throws Exception If query fails
     */
    public function generateMemberId() {
        try {
            $conn = $this->db->getConnection();
            $result = $conn->query("SELECT member_id FROM members ORDER BY id DESC LIMIT 1");
            if (!$result) {
                throw new Exception("Query failed: " . $conn->error);
            }

            $last_member = $result->fetch_assoc();
            $last_num = $last_member ? (int)substr($last_member['member_id'], 3) : 0;
            return 'MS-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            error_log("Error generating member ID: " . $e->getMessage());
            return 'MS-001'; // Fallback to first ID
        }
    }

    /**
     * Check if an NIC number is unique.
     *
     * @param string $nic_number National ID number to check
     * @return bool True if unique, false if already exists
     * @throws Exception If query fails
     */
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

    /**
     * Update an existing member's details.
     *
     * @param int $id Member's database ID
     * @param array $data Associative array of fields to update
     * @return bool True on success, false on failure
     * @throws Exception If update fails
     */
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
            $types = str_repeat('s', count($fields) - 1) . 'd'; // All strings except contribution_amount (double)
            $values = array_values($fields);
            $values[] = $id; // Append ID for WHERE clause

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

    /**
     * Delete a member from the database.
     *
     * @param int $id Member's database ID
     * @return bool True on success, false on failure
     * @throws Exception If deletion fails
     */
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

    /**
     * Get the last member added to the database.
     *
     * @return array|null Last member's data or null if none exist
     * @throws Exception If query fails
     */
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