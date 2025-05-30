<?php
require_once __DIR__ . '/Database.php';

class Family {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function addFamilyDetails($member_id, $spouse_data = null, $children_data = null, $dependents_data = null) {
        $conn = $this->db->getConnection();
        $conn->begin_transaction();

        try {
            // Add spouse if provided
            if ($spouse_data) {
                $stmt = $conn->prepare("
                    INSERT INTO family_details (
                        member_id, spouse_name, spouse_dob, spouse_gender
                    ) VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("isss", 
                    $member_id,
                    $spouse_data['name'],
                    $spouse_data['dob'],
                    $spouse_data['gender']
                );
                if (!$stmt->execute()) {
                    throw new Exception("Failed to add spouse details");
                }
            }

            // Add children if provided
            if ($children_data && is_array($children_data)) {
                $stmt = $conn->prepare("
                    INSERT INTO children (
                        member_id, name, child_dob, gender
                    ) VALUES (?, ?, ?, ?)
                ");
                
                foreach ($children_data as $child) {
                    $stmt->bind_param("isss", 
                        $member_id,
                        $child['name'],
                        $child['dob'],
                        $child['gender']
                    );
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to add child details");
                    }
                }
            }

            // Add dependents if provided
            if ($dependents_data && is_array($dependents_data)) {
                $stmt = $conn->prepare("
                    INSERT INTO dependents (
                        member_id, name, relationship, dependant_dob, dependant_address
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($dependents_data as $dependent) {
                    $stmt->bind_param("issss", 
                        $member_id,
                        $dependent['name'],
                        $dependent['relationship'],
                        $dependent['dob'],
                        $dependent['address']
                    );
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to add dependent details");
                    }
                }
            }

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

    public function getFamilyDetails($member_id) {
        $conn = $this->db->getConnection();
        $family = [
            'spouse' => null,
            'children' => [],
            'dependents' => []
        ];

        // Get spouse details
        $stmt = $conn->prepare("
            SELECT spouse_name as name, spouse_dob as dob, spouse_gender as gender 
            FROM family_details 
            WHERE member_id = ?
        ");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $family['spouse'] = $row;
        }

        // Get children details
        $stmt = $conn->prepare("
            SELECT name, child_dob as dob, gender 
            FROM children 
            WHERE member_id = ? 
            ORDER BY child_dob DESC
        ");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $family['children'][] = $row;
        }

        // Get dependents details
        $stmt = $conn->prepare("
            SELECT name, relationship, dependant_dob as dob, dependant_address as address 
            FROM dependents 
            WHERE member_id = ? 
            ORDER BY relationship
        ");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $family['dependents'][] = $row;
        }

        return $family;
    }

    public function updateFamilyDetails($member_id, $spouse_data = null, $children_data = null, $dependents_data = null) {
        $conn = $this->db->getConnection();
        $conn->begin_transaction();

        try {
            // Delete existing family details
            $stmt = $conn->prepare("DELETE FROM family_details WHERE member_id = ?");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();

            $stmt = $conn->prepare("DELETE FROM children WHERE member_id = ?");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();

            $stmt = $conn->prepare("DELETE FROM dependents WHERE member_id = ?");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();

            // Add new family details
            $this->addFamilyDetails($member_id, $spouse_data, $children_data, $dependents_data);

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

    public function deleteFamilyDetails($member_id) {
        $conn = $this->db->getConnection();
        $conn->begin_transaction();

        try {
            // Delete from all family-related tables
            $tables = ['family_details', 'children', 'dependents'];
            foreach ($tables as $table) {
                $stmt = $conn->prepare("DELETE FROM $table WHERE member_id = ?");
                $stmt->bind_param("i", $member_id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to delete from $table");
                }
            }

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

    public function getSpouseCount($member_id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM family_details 
            WHERE member_id = ?
        ");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['count'];
    }

    public function getChildrenCount($member_id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM children 
            WHERE member_id = ?
        ");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['count'];
    }

    public function getDependentsCount($member_id) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM dependents 
            WHERE member_id = ?
        ");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['count'];
    }

    public function validateSpouseData($data) {
        if (empty($data['name'])) {
            throw new Exception("Spouse name is required");
        }
        if (isset($data['dob']) && !DateTime::createFromFormat('Y-m-d', $data['dob'])) {
            throw new Exception("Invalid spouse date of birth");
        }
        if (isset($data['gender']) && !in_array($data['gender'], ['Male', 'Female', 'Other'])) {
            throw new Exception("Invalid spouse gender");
        }
        return true;
    }

    public function validateChildData($data) {
        if (empty($data['name'])) {
            throw new Exception("Child name is required");
        }
        if (empty($data['dob']) || !DateTime::createFromFormat('Y-m-d', $data['dob'])) {
            throw new Exception("Invalid child date of birth");
        }
        if (empty($data['gender']) || !in_array($data['gender'], ['Male', 'Female', 'Other'])) {
            throw new Exception("Invalid child gender");
        }
        return true;
    }

    public function validateDependentData($data) {
        if (empty($data['name'])) {
            throw new Exception("Dependent name is required");
        }
        if (empty($data['relationship'])) {
            throw new Exception("Dependent relationship is required");
        }
        if (empty($data['dob']) || !DateTime::createFromFormat('Y-m-d', $data['dob'])) {
            throw new Exception("Invalid dependent date of birth");
        }
        if (empty($data['address'])) {
            throw new Exception("Dependent address is required");
        }
        return true;
    }

    public function getSpouseDetails($memberId) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT spouse_name, spouse_dob, spouse_gender FROM family_details WHERE member_id = ?");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getChildren($memberId) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT name, child_dob, gender FROM children WHERE member_id = ?");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getDependents($memberId) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT name, relationship, dependant_dob, dependant_address FROM dependents WHERE member_id = ?");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getFamilyDetailsByMemberId($memberId) {
        $spouse = $this->getSpouseDetails($memberId);
        $children = $this->getChildren($memberId);
        $dependents = $this->getDependents($memberId);

        $children_info = [];
        foreach ($children as $child) {
            $children_info[] = $child['name'] . ' (' . $child['child_dob'] . ')';
        }

        $dependents_info = [];
        foreach ($dependents as $dependent) {
            $dependents_info[] = $dependent['name'] . ' (' . $dependent['relationship'] . ')';
        }

        return [
            'spouse_name' => $spouse ? $spouse['spouse_name'] : null,
            'children_info' => !empty($children_info) ? implode(', ', $children_info) : null,
            'dependents_info' => !empty($dependents_info) ? implode(', ', $dependents_info) : null
        ];
    }

    public function getFamilyMembersCount($memberId) {
        $spouseCount = $this->getSpouseCount($memberId);
        $childrenCount = $this->getChildrenCount($memberId);
        $dependentsCount = $this->getDependentsCount($memberId);
        
        return $spouseCount + $childrenCount + $dependentsCount;
    }

    public function hasSpouse($memberId) {
        return $this->getSpouseCount($memberId) > 0;
    }
}
?>