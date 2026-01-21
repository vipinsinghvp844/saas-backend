<?php
require __DIR__ . '/config/db.php';

$db = new Database();
$conn = $db->connect();

$sql = file_get_contents(__DIR__ . '/gym_saas.sql');
$conn->exec($sql);

echo "DB IMPORTED";
