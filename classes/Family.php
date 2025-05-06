<?php
require_once __DIR__ . '/Database.php';

class Family {
    private $db;

    public function __construct() {
        try {
            $this->db = new Database();
        } catch (Exception $e) {
            error_log("Database initialization failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function addFamilyDetails($member_id, $spouse_name, $children_info, $dependents_info) {
        try {
<<<<<<< HEAD
            if (!is_numeric($member_id) || $member_id <= 0) {
                throw new Exception("Invalid member ID.");
            }

            // Validate input data
            $this->validateFamilyData([
                'spouse_name' => $spouse_name,
                'children_info' => $children_info,
                'dependents_info' => $dependents_info
            ]);

            $conn = $this->db->getConnection();
            
            // Check if family details already exist
            $stmt = $conn->prepare("SELECT id FROM family_details WHERE member_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Family details already exist for this member.");
            }
            
            $stmt->close();

            // Insert new family details
            $stmt = $conn->prepare(
                "INSERT INTO family_details (member_id, spouse_name, children_info, dependents_info) 
                VALUES (?, ?, ?, ?)"
            );
            
=======
            if (!is_int($member_id) || $member_id <= 0) {
                throw new Exception("Invalid member ID: $member_id");
            }
            if ($spouse_name && strlen($spouse_name) > 100) {
                throw new Exception("Spouse name exceeds 100 characters.");
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("INSERT INTO family_details (member_id, spouse_name, children_info, dependents_info) VALUES (?, ?, ?, ?)");
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("isss", $member_id, $spouse_name, $children_info, $dependents_info);
            $result = $stmt->execute();
<<<<<<< HEAD
            
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $family_id = $stmt->insert_id;
            $stmt->close();

            error_log("Family details added successfully: member_id=$member_id");
            return $family_id;
        } catch (Exception $e) {
            error_log("Error adding family details: " . $e->getMessage() . " | member_id=$member_id");
            throw $e;
        }
    }

    public function updateFamilyDetails($member_id, $data) {
        try {
            if (!is_numeric($member_id) || $member_id <= 0) {
                throw new Exception("Invalid member ID.");
            }

            // Validate input data
            $this->validateFamilyData($data);

            $conn = $this->db->getConnection();
            
            // Build update query dynamically based on provided data
            $updates = [];
            $types = '';
            $values = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, ['spouse_name', 'children_info', 'dependents_info'])) {
                    $updates[] = "$key = ?";
                    $types .= 's';
                    $values[] = $value;
                }
            }
            
            if (empty($updates)) {
                throw new Exception("No valid fields to update.");
            }

            $types .= 'i'; // for the WHERE member_id = ? clause
            $values[] = $member_id;

            $query = "UPDATE family_details SET " . implode(', ', $updates) . " WHERE member_id = ?";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param($types, ...$values);
            $result = $stmt->execute();
            
=======
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
<<<<<<< HEAD
            error_log("Family details updated successfully: member_id=$member_id");
            return true;
        } catch (Exception $e) {
            error_log("Error updating family details: " . $e->getMessage() . " | member_id=$member_id");
            throw $e;
=======
            return true;
        } catch (Exception $e) {
            error_log("Error adding family details for member_id=$member_id: " . $e->getMessage());
            return false;
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
        }
    }

    public function getFamilyDetailsByMemberId($member_id) {
        try {
<<<<<<< HEAD
            if (!is_numeric($member_id) || $member_id <= 0) {
                throw new Exception("Invalid member ID.");
=======
            if (!is_int($member_id) || $member_id <= 0) {
                throw new Exception("Invalid member ID: $member_id");
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT * FROM family_details WHERE member_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

<<<<<<< HEAD
            if (!$result) {
                error_log("No family details found for member_id: $member_id");
                return null;
            }

            // Parse JSON fields
            if ($result['children_info']) {
                $result['children_info'] = json_decode($result['children_info'], true);
            }
            if ($result['dependents_info']) {
                $result['dependents_info'] = json_decode($result['dependents_info'], true);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error fetching family details: " . $e->getMessage() . " | member_id=$member_id");
            return null;
        }
    }

    public function deleteFamilyDetails($member_id) {
        try {
            if (!is_numeric($member_id) || $member_id <= 0) {
                throw new Exception("Invalid member ID.");
            }

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("DELETE FROM family_details WHERE member_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("i", $member_id);
            $result = $stmt->execute();
            $stmt->close();

            if (!$result) {
                throw new Exception("Failed to delete family details.");
            }

            error_log("Family details deleted successfully: member_id=$member_id");
            return true;
        } catch (Exception $e) {
            error_log("Error deleting family details: " . $e->getMessage() . " | member_id=$member_id");
            throw $e;
        }
    }

    public function addChild($member_id, $child_data) {
        try {
            if (!is_numeric($member_id) || $member_id <= 0) {
                throw new Exception("Invalid member ID.");
            }

            // Validate child data
            $this->validateChildData($child_data);

            $conn = $this->db->getConnection();
            
            // Get existing children info
            $stmt = $conn->prepare("SELECT children_info FROM family_details WHERE member_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$result) {
                throw new Exception("Family details not found for this member.");
            }

            // Parse existing children info
            $children = $result['children_info'] ? json_decode($result['children_info'], true) : [];
            
            // Add new child
            $children[] = $child_data;

            // Update children info
            $children_json = json_encode($children);
            $stmt = $conn->prepare("UPDATE family_details SET children_info = ? WHERE member_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("si", $children_json, $member_id);
            $result = $stmt->execute();
            $stmt->close();

            if (!$result) {
                throw new Exception("Failed to add child.");
            }

            error_log("Child added successfully: member_id=$member_id");
            return true;
        } catch (Exception $e) {
            error_log("Error adding child: " . $e->getMessage() . " | member_id=$member_id");
            throw $e;
        }
    }

    public function addDependent($member_id, $dependent_data) {
        try {
            if (!is_numeric($member_id) || $member_id <= 0) {
                throw new Exception("Invalid member ID.");
            }

            // Validate dependent data
            $this->validateDependentData($dependent_data);

            $conn = $this->db->getConnection();
            
            // Get existing dependents info
            $stmt = $conn->prepare("SELECT dependents_info FROM family_details WHERE member_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$result) {
                throw new Exception("Family details not found for this member.");
            }

            // Parse existing dependents info
            $dependents = $result['dependents_info'] ? json_decode($result['dependents_info'], true) : [];
            
            // Add new dependent
            $dependents[] = $dependent_data;

            // Update dependents info
            $dependents_json = json_encode($dependents);
            $stmt = $conn->prepare("UPDATE family_details SET dependents_info = ? WHERE member_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("si", $dependents_json, $member_id);
            $result = $stmt->execute();
            $stmt->close();

            if (!$result) {
                throw new Exception("Failed to add dependent.");
            }

            error_log("Dependent added successfully: member_id=$member_id");
            return true;
        } catch (Exception $e) {
            error_log("Error adding dependent: " . $e->getMessage() . " | member_id=$member_id");
            throw $e;
        }
    }

    private function validateFamilyData($data) {
        $validations = [
            'spouse_name' => ['max' => 100, 'required' => false],
            'children_info' => ['json' => true, 'required' => false],
            'dependents_info' => ['json' => true, 'required' => false]
        ];

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
                    
                    if (isset($rules['json']) && $rules['json']) {
                        if (!is_string($value)) {
                            $value = json_encode($value);
                        }
                        if (json_decode($value) === null) {
                            throw new Exception("$field must be valid JSON.");
                        }
                    }
                }
            }
        }
    }

    private function validateChildData($data) {
        $required_fields = ['name', 'date_of_birth', 'gender'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Child $field is required.");
            }
        }

        if (strlen($data['name']) > 100) {
            throw new Exception("Child name exceeds maximum length of 100 characters.");
        }

        if (!in_array($data['gender'], ['Male', 'Female', 'Other'])) {
            throw new Exception("Invalid gender value.");
        }

        // Validate date of birth
        $dob = strtotime($data['date_of_birth']);
        if (!$dob) {
            throw new Exception("Invalid date of birth format.");
        }
    }

    private function validateDependentData($data) {
        $required_fields = ['name', 'relationship', 'date_of_birth'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Dependent $field is required.");
            }
        }

        if (strlen($data['name']) > 100) {
            throw new Exception("Dependent name exceeds maximum length of 100 characters.");
        }

        if (strlen($data['relationship']) > 50) {
            throw new Exception("Relationship exceeds maximum length of 50 characters.");
        }

        // Validate date of birth
        $dob = strtotime($data['date_of_birth']);
        if (!$dob) {
            throw new Exception("Invalid date of birth format.");
        }
=======
            return $result ?: null;
        } catch (Exception $e) {
            error_log("Error fetching family details: " . $e->getMessage());
            return null;
        }
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
    }
}
?>