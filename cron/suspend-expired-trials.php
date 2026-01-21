<?php
require_once "../config/db.php";

$db = new Database();
$conn = $db->connect();

// जिनका trial खत्म हो गया और billing_status अभी भी trial है
$stmt = $conn->prepare("
  UPDATE gyms
  SET billing_status='suspended', status='suspended'
  WHERE billing_status='trial'
    AND trial_ends_at IS NOT NULL
    AND trial_ends_at < NOW()
");
$stmt->execute();

echo "Done: suspended " . $stmt->rowCount() . " gyms\n";
