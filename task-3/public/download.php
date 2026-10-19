<?php
/**
 * Task 3 â€” avatar download endpoint.
 *
 * Uploaded images live in task-3/storage/uploads, outside every document root,
 * so they cannot be requested directly and a malicious upload never becomes an
 * executable script. This script is the only way to read them.
 *
 *   download.php?file=<stored-name>
 *
 * The requested name is reduced with basename() and then checked against a
 * strict pattern, so no traversal or absolute path can escape the directory.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$dir     = $APEX_CONFIG['upload']['dir'];
$file    = (string) ($_GET['file'] ?? '');
$file    = basename($file);

// Stored names are 32 hex characters plus one of the four known extensions.
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp)$/', $file)) {
    http_response_code(400);
    exit('Invalid file name.');
}

$path = $dir . DIRECTORY_SEPARATOR . $file;

// realpath() must stay inside the upload directory.
$realDir  = realpath($dir);
$realFile = realpath($path);
if ($realDir === false || $realFile === false || !str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Not found.');
}

$mimes = [
    'jpg'  => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];
$ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));

header('Content-Type: ' . $mimes[$ext]);
header('Content-Length: ' . (string) filesize($realFile));
header('Content-Disposition: inline; filename="' . $file . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($realFile);
