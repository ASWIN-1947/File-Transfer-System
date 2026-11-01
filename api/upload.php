<?php
/**
 * API: File Upload Endpoint
 * Handles file validation, secure obfuscated disk storage, and JSON metadata recording.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Utils.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/Storage.php';

Security::setSecurityHeaders();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Utils::sendJsonResponse([
        'status' => 'error',
        'message' => 'Invalid request method. Only POST is accepted.'
    ], 405);
}

// Check if file payload was sent
if (!isset($_FILES['file'])) {
    Utils::sendJsonResponse([
        'status' => 'error',
        'message' => 'No file was received. If the file was very large, it may have exceeded the server post limit (' . MAX_FILE_SIZE_HUMAN . ').'
    ], 400);
}

// Initialize storage structure
Storage::init();

// Validate file
$validation = Security::validateUploadedFile($_FILES['file']);
if (!$validation['valid']) {
    Utils::sendJsonResponse([
        'status' => 'error',
        'message' => $validation['error']
    ], 422);
}

// Generate unique identifiers
$id = Utils::generateUuid();
$shareToken = Utils::generateShareToken(32);
$storedFilename = $id . '.vault'; // Obfuscated extension prevents direct execution
$destination = STORAGE_DIR . DIRECTORY_SEPARATOR . $storedFilename;

// Move uploaded file to secure storage directory
if (!move_uploaded_file($validation['tmp_name'], $destination)) {
    Utils::sendJsonResponse([
        'status' => 'error',
        'message' => 'Failed to save uploaded file to storage. Please check disk permissions.'
    ], 500);
}

// Process expiration options
$expiryOption = trim($_POST['expiry'] ?? 'never');
$expiresAt = null;
$maxDownloads = null;

switch ($expiryOption) {
    case '1h':
        $expiresAt = time() + 3600;
        break;
    case '24h':
        $expiresAt = time() + 86400;
        break;
    case '7d':
        $expiresAt = time() + (7 * 86400);
        break;
    case '30d':
        $expiresAt = time() + (30 * 86400);
        break;
    case '1download':
        $maxDownloads = 1;
        break;
    case 'never':
    default:
        $expiresAt = null;
        break;
}

// Process password protection
$password = trim($_POST['password'] ?? '');
$passwordHash = null;
if (!empty($password)) {
    if (strlen($password) < 4) {
        // Clean up uploaded file if validation fails
        @unlink($destination);
        Utils::sendJsonResponse([
            'status' => 'error',
            'message' => 'Password must be at least 4 characters long.'
        ], 422);
    }
    $passwordHash = Security::hashPassword($password);
}

// Process optional description
$description = trim($_POST['description'] ?? '');
if (mb_strlen($description) > 300) {
    $description = mb_substr($description, 0, 300);
}

// Category
$category = Utils::getFileCategory($validation['extension'], $validation['mime']);

// Construct metadata record for JSON
$metadata = [
    'id' => $id,
    'share_token' => $shareToken,
    'original_name' => $validation['sanitized_name'],
    'stored_filename' => $storedFilename,
    'extension' => $validation['extension'],
    'mime_type' => $validation['mime'],
    'category' => $category,
    'size' => $validation['size'],
    'uploaded_at' => date('Y-m-d H:i:s'),
    'uploaded_timestamp' => time(),
    'expires_at' => $expiresAt,
    'max_downloads' => $maxDownloads,
    'downloads_count' => 0,
    'password_hash' => $passwordHash,
    'description' => htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
    'client_ip' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') // Anonymized
];

// Save metadata to data/files.json
if (!Storage::saveFileMetadata($metadata)) {
    @unlink($destination);
    Utils::sendJsonResponse([
        'status' => 'error',
        'message' => 'Failed to write file metadata to JSON database.'
    ], 500);
}

// Build URLs
$baseUrl = getBaseUrl();
$shareUrl = $baseUrl . '/download.php?token=' . urlencode($shareToken);
$directDownloadUrl = $baseUrl . '/api/download.php?token=' . urlencode($shareToken);

Utils::sendJsonResponse([
    'status' => 'success',
    'message' => 'File uploaded successfully!',
    'file' => [
        'id' => $id,
        'name' => $validation['sanitized_name'],
        'size' => $validation['size'],
        'formatted_size' => Utils::formatBytes($validation['size']),
        'category' => $category,
        'category_meta' => Utils::getCategoryMeta($category),
        'share_url' => $shareUrl,
        'direct_download_url' => $directDownloadUrl,
        'has_password' => !empty($passwordHash),
        'expires_at' => $expiresAt,
        'max_downloads' => $maxDownloads
    ]
], 201);
