<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/utils/mail.php';

sendMail(
  "vipin.stevesai@gmail.com",
  "SMTP Test",
  "Gmail SMTP is working 🎉"
);

echo "Mail sent";
