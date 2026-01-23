<?php 
// config/db.php 
 class Database { 
    private $host = "MYSQL8003.site4now.net";
     private $db_name = "db_ac4346_gym"; 
     private $username = "ac4346_gym"; 
     private $password = "vipin@844";
     public $conn; public function connect() { 
        $this->conn = null; 
        try { 
            $this->conn = new PDO( "mysql:host=".$this->host."; 
            dbname=".$this->db_name."; 
            charset=utf8", $this->username, $this->password ); 
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
            } catch(PDOException $e) { 
                http_response_code(500); 
                echo json_encode([ "status" => false, "message" => "Database connection failed" ]); 
                exit; 
                } 
                return $this->conn; 
                } 
                }
                