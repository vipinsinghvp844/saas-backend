<?php
function getAuditContext() {
  $user = $GLOBALS['auth_user'] ?? null;

  return [
    "user_id"   => $user['id']   ?? null,
    "user_name" => $user['name'] ?? 'System',
    "user_role" => $user['role'] ?? 'system',
  ];
}
