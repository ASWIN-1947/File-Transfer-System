<?php
/**
 * API: Secure File Download & Streaming Endpoint
 * Enforces authentication (password check), expiration verification,
 * path traversal defense, and streams file in chunks.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Utils.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/Storage.php';

// Accept token or id
$token = trim($_GET['token'] ?? '');
$id = trim($_GET['id'] ?? '');
$password = trim($_GET['password'] ?? ($_POST['password'] ?? ''));
$preview = isset($_GET['preview']) && $_GET['preview'] === '1';

if (empty($token) && empty($id)) {
    http_response_code(400);
    exit('Bad Request: Missing download token or file ID.');
}

// Locate file metadata
$file = !empty($token) ? Storage::getFileByToken($token) : Storage::getFileById($id);

if (!$file) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    exit('<!DOCTYPE html><html><head><title>File Not Found</title><style>body{font-family:sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}.box{text-align:center;padding:2rem;background:#1e293b;border-radius:12px;border:1px solid #334155;max-width:400px;}</style></head><body><div class="box"><h2>File Not Found or Expired</h2><p>This file may have expired, reached its download limit, or was removed by the owner.</p><a href="../index.php" style="color:#6366f1;text-decoration:none;font-weight:600;">&larr; Return to NovaTransfer</a></div></body></html>');
}

// Check if expired
$currentTime = time();
if (!empty($file['expires_at']) && $file['expires_at'] < $currentTime) {
    Storage::purgeExpiredFiles();
    http_response_code(410);
    exit('This file has expired and is no longer available.');
}

// Check max downloads
if (!empty($file['max_downloads']) && ($file['downloads_count'] ?? 0) >= $file['max_downloads']) {
    Storage::purgeExpiredFiles();
    http_response_code(410);
    exit('This one-time download link has already been used.');
}

// Check password protection
if (!empty($file['password_hash'])) {
    if (empty($password) || !Security::verifyPassword($password, $file['password_hash'])) {
        // Return 401 Unauthorized
        if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
            Utils::sendJsonResponse([
                'status' => 'error',
                'message' => empty($password) ? 'Password required.' : 'Incorrect password. Access denied.'
            ], 401);
        }
        
        // Redirect to download landing page with error
        $baseUrl = getBaseUrl();
        header('Location: ' . $baseUrl . '/download.php?token=' . urlencode($file['share_token']) . '&error=invalid_password');
        exit;
    }
}

// Check physical file exists and path traversal defense
$storedPath = STORAGE_DIR . DIRECTORY_SEPARATOR . $file['stored_filename'];
if (!file_exists($storedPath) || !Security::isPathContained(STORAGE_DIR, $storedPath)) {
    http_response_code(404);
    exit('Error: The stored file payload could not be located on disk.');
}

// If this is an AJAX password check request, validate and return success
if (isset($_GET['check_auth']) && $_GET['check_auth'] === '1') {
    Utils::sendJsonResponse([
        'status' => 'success',
        'message' => 'Password authenticated successfully.'
    ]);
}

// Increment download count (only for full download, not image preview)
if (!$preview) {
    Storage::recordDownload($file['id']);
}

// Clean any previous output buffer to avoid corruption
if (ob_get_level()) {
    ob_end_clean();
}

$fileSize = filesize($storedPath);
$originalName = $file['original_name'] ?? 'download';
$mimeType = $file['mime_type'] ?? 'application/octet-stream';

// If preview mode for safe types (images, pdf), display inline
if ($preview && (strpos($mimeType, 'image/') === 0 || $mimeType === 'application/pdf')) {
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . addslashes($originalName) . '"');
} else {
    // Normal download attachment
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . rawurlencode($originalName) . '"; filename*=UTF-8\'\'' . rawurlencode($originalName));
}

header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $fileSize);
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');

// Stream file in 8KB chunks
$chunkSize = 8 * 1024;
$handle = fopen($storedPath, 'rb');
if ($handle !== false) {
    while (!feof($handle) && connection_status() == 0) {
        echo fread($handle, $chunkSize);
        flush();
    }
    fclose($handle);
}
exit;
