<?php
echo json_encode([
  "MYSQLHOST" => getenv("MYSQLHOST"),
  "MYSQLUSER" => getenv("MYSQLUSER"),
  "HAS_PASSWORD" => getenv("MYSQLPASSWORD") ? "YES" : "NO",
  "HAS_ROOT_PASSWORD" => getenv("MYSQL_ROOT_PASSWORD") ? "YES" : "NO"
]);
