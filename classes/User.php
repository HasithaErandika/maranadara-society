<?php
require_once '../classes/Database.php';

class User {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function login($username, $password) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($hashed_password, $role);
        if ($stmt->fetch() && password_verify($password, $hashed_password)) {
            session_start();
            $_SESSION['user'] = $username;
            $_SESSION['role'] = $role;
            return $role;
        }
        return false;
    }

    public function logout() {
        session_start();
        session_destroy();
    }
}
?>