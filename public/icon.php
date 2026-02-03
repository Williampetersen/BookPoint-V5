<?php
// Simple icon proxy for hosts that block direct .svg access.
// Reads from /public/icons and outputs with correct headers.

declare(strict_types=1);

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
$file = isset($_GET['file']) ? (string) $_GET['file'] : '';
$file = basename($file);

if ($file === '' || !preg_match('/^[a-z0-9\\-]+\\.(svg|png)$/i', $file)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=UTF-8');
  echo 'Bad request';
  exit;
}

$path = __DIR__ . '/icons/' . $file;
if (!is_file($path)) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=UTF-8');
  echo 'Not found';
  exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if ($ext === 'png') {
  header('Content-Type: image/png');
} else {
  header('Content-Type: image/svg+xml; charset=UTF-8');
}

header('X-Content-Type-Options: nosniff');

// If a version is provided, allow long caching.
$has_v = isset($_GET['v']) && $_GET['v'] !== '';
if ($has_v) {
  header('Cache-Control: public, max-age=31536000, immutable');
} else {
  header('Cache-Control: public, max-age=600');
}

readfile($path);

