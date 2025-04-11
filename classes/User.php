<?php
require_once __DIR__ . '/Database.php';

class User {
    private $db;

    public function __construct($db_username, $db_password) {
        $this->db = new Database($db_username, $db_password);
    }

    public function verifyUser($username, $password) {
        try {
            $conn = $this->db->getConnection();
            $username = $conn->real_escape_string($username);
            $stmt = $conn->prepare("SELECT username, password FROM users WHERE username = ?");
            if (!$stmt) {
                error_log("Prepare failed: " . $conn->error);
                return false;
            }
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $stmt->close();
                    return true;
                }
            }

            $stmt->close();
            return false;
        } catch (Exception $e) {
            error_log("User verification failed: " . $e->getMessage());
            return false;
        }
    }
}
?>