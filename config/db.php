<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        $this->host     = getenv("MYSQLHOST") ?: "mysql.railway.internal";
        $this->db_name  = getenv("MYSQLDATABASE") ?: "railway";
        $this->username = getenv("MYSQLUSER") ?: "root";
        $this->password = getenv("MYSQLPASSWORD");
        $this->port     = getenv("MYSQLPORT") ?: 3306;
    }

    public function connect() {
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";

            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            return $this->conn;

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "status" => false,
                "error" => "DB connection failed",
                "detail" => $e->getMessage() // dev ke liye
            ]);
            exit;
        }
    }
}
