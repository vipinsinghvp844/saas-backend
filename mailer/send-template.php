<?php
// /mailer/send-template.php

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../utils/mail.php";

function renderTemplateVars(string $html, array $vars = []): string {
  foreach ($vars as $k => $v) {
    $html = str_replace("{{" . $k . "}}", (string)$v, $html);
  }
  return $html;
}

function sendTemplateMail(string $toEmail, string $templateSlug, array $vars = []): bool {
  $db = new Database();
  $conn = $db->connect();

  $stmt = $conn->prepare("SELECT * FROM email_templates WHERE slug=:slug AND status='active' LIMIT 1");
  $stmt->execute([":slug" => $templateSlug]);
  $tpl = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$tpl) {
    throw new Exception("Email template not found or inactive: " . $templateSlug);
  }

  $subject = renderTemplateVars($tpl['subject'], $vars);
  $body = renderTemplateVars($tpl['body_html'], $vars);

  return sendMail($toEmail, $subject, $body);
}
