<?php
// config/db.php

class Database {
    private $host = "mysql.railway.internal";
    private $db_name = "railway";
    private $username = "root";
    private $password = "NTOLZmAKWUyNWEKNNyozVvvsdFrVKwJW";
    public $conn;

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=".$this->host.";
                dbname=".$this->db_name.";
                charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "status" => false,
                "message" => "Database connection failed"
            ]);
            exit;
        }

        return $this->conn;
    }
}
