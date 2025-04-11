<?php
require_once __DIR__ . '/../config/db_config.php';

class Database {
    private $conn;
    private $db_username;
    private $db_password;

    public function __construct($db_username = null, $db_password = null) {
        $this->db_username = $db_username ?? ($_SESSION['db_username'] ?? null);
        $this->db_password = $db_password ?? ($_SESSION['db_password'] ?? null);
    }

    public function connect() {
        if (!$this->db_username || !$this->db_password) {
            error_log("Database credentials not provided");
            throw new Exception("Database credentials not set.");
        }

        $this->conn = new mysqli(DB_HOST, $this->db_username, $this->db_password, DB_NAME);
        if ($this->conn->connect_error) {
            error_log("Database connection failed: " . $this->conn->connect_error);
            throw new Exception("Database connection failed.");
        }
        $this->conn->set_charset('utf8mb4');
        return $this->conn;
    }

    public function getConnection() {
        if (!$this->conn) {
            $this->connect();
        }
        return $this->conn;
    }

    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
    }

    public function __destruct() {
        $this->closeConnection();
    }
}
?>