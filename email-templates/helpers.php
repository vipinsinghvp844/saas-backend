<?php

function slugify($text) {
  $text = strtolower(trim($text));
  $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
  $text = preg_replace('/[\s-]+/', '-', $text);
  $text = trim($text, '-');
  return $text;
}

function renderTemplate($html, $vars = []) {
  if (!is_array($vars)) $vars = [];
  foreach ($vars as $key => $val) {
    $html = str_replace("{{" . $key . "}}", (string)$val, $html);
  }
  return $html;
}
