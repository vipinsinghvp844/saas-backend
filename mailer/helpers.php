<?php
require_once "../config/db.php";

/**
 * Replace placeholders in template.
 * Example:
 *  {{gym_name}} -> PowerFit Gym
 */
function renderTemplateVars($text, $vars = []) {
  if (!is_array($vars)) $vars = [];

  foreach ($vars as $k => $v) {
    $text = str_replace("{{" . $k . "}}", (string)$v, $text);
  }
  return $text;
}

/**
 * Basic send function placeholder.
 * ✅ Replace this with your SMTP sender.
 */
function sendEmailSMTP($to, $subject, $html, $fromName = "Gym SaaS", $fromEmail = "no-reply@example.com") {
  // ✅ If you already have PHPMailer setup, call it here.
  // For now, using PHP mail() only for demo (not recommended for production).
  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-type:text/html;charset=UTF-8\r\n";
  $headers .= "From: {$fromName} <{$fromEmail}>\r\n";

  return mail($to, $subject, $html, $headers);
}
