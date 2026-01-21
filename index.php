<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "STEP 1: Script started<br>";

require __DIR__ . '/config/db.php';
echo "STEP 2: DB config loaded<br>";

$db = new Database();
$conn = $db->connect();
echo "STEP 3: DB connected<br>";

$sqlFile = __DIR__ . '/gym_saas.sql';

if (!file_exists($sqlFile)) {
    die("ERROR: SQL file not found");
}
echo "STEP 4: SQL file found<br>";

$sql = file_get_contents($sqlFile);
if (!$sql) {
    die("ERROR: SQL file empty");
}
echo "STEP 5: SQL file loaded<br>";

/**
 * IMPORTANT FIX:
 * Split SQL by semicolon
 */
$queries = array_filter(array_map('trim', explode(';', $sql)));

$success = 0;
$failed = 0;

foreach ($queries as $query) {
    if ($query === '') continue;
    try {
        $conn->exec($query);
        $success++;
    } catch (PDOException $e) {
        $failed++;
        echo "<pre>FAILED QUERY:\n$query\nERROR: ".$e->getMessage()."</pre>";
    }
}

echo "<br>IMPORT FINISHED<br>";
echo "SUCCESS: $success<br>";
echo "FAILED: $failed<br>";
