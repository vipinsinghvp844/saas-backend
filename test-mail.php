<?php
// test_connection.php
require_once 'config/db.php';

$database = new Database();
$conn = $database->connect();

if ($conn) {
    echo "Database connected successfully!";
} else {
    echo "Failed to connect to the database.";
}
