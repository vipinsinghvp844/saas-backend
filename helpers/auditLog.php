<?php
require_once __DIR__ . "/../middleware/auditContext.php";

function logAudit(array $data) {
  global $conn;

  // 🔹 default context (from auth middleware)
  $ctx = getAuditContext();

  // 🔹 allow override (important for login / system events)
  $userId   = $data['user_id']   ?? $ctx['user_id'];
  $userName = $data['user_name'] ?? $ctx['user_name'];
  $userRole = $data['user_role'] ?? $ctx['user_role'];

  $stmt = $conn->prepare("
    INSERT INTO audit_logs
      (user_id, user_name, user_role,
       action, module,
       target_type, target_id,
       description,
       ip_address, user_agent, created_at)
    VALUES
      (:user_id, :user_name, :user_role,
       :action, :module,
       :target_type, :target_id,
       :description,
       :ip_address, :user_agent, NOW())
  ");

  $stmt->execute([
    ":user_id"     => $userId,
    ":user_name"   => $userName,
    ":user_role"   => $userRole,

    ":action"      => $data['action'],
    ":module"      => $data['module'],
    ":target_type" => $data['target_type'] ?? null,
    ":target_id"   => $data['target_id'] ?? null,
    ":description" => $data['description'],

    ":ip_address"  => $_SERVER['REMOTE_ADDR'] ?? null,
    ":user_agent"  => $_SERVER['HTTP_USER_AGENT'] ?? null,
  ]);
}
