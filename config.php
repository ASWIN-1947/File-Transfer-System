<?php
/**
 * NovaTransfer - File Transfer System Configuration
 */

// Application Information
define('APP_NAME', 'NovaTransfer');
define('APP_TAGLINE', 'Fast, Secure & Encrypted File Sharing');
define('APP_VERSION', '1.0.0');

// Paths
define('ROOT_PATH', __DIR__);
define('DATA_DIR', ROOT_PATH . DIRECTORY_SEPARATOR . 'data');
define('STORAGE_DIR', ROOT_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'files');
define('METADATA_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'files.json');

// Helper to convert php.ini size strings (like "40M", "1G") to bytes
function parseIniSizeToBytes($sizeStr) {
    $sizeStr = trim($sizeStr);
    if (empty($sizeStr)) return 0;
    $unit = strtolower(substr($sizeStr, -1));
    $bytes = (float)$sizeStr;
    switch ($unit) {
        case 'g': $bytes *= 1024;
        case 'm': $bytes *= 1024;
        case 'k': $bytes *= 1024;
    }
    return (int)$bytes;
}

// Upload Size Limit: dynamic based on PHP ini, capped at 40MB
$maxUpload = parseIniSizeToBytes(ini_get('upload_max_filesize') ?: '40M');
$maxPost = parseIniSizeToBytes(ini_get('post_max_size') ?: '40M');
$systemMax = 40 * 1024 * 1024; // 40 MB

// Safe minimum of server limits
$effectiveMax = min(
    $systemMax,
    $maxUpload > 0 ? $maxUpload : $systemMax,
    $maxPost > 0 ? $maxPost : $systemMax
);
define('MAX_FILE_SIZE_BYTES', $effectiveMax);
define('MAX_FILE_SIZE_HUMAN', '40 MB');

// Disallowed extensions to prevent remote code execution
define('BLOCKED_EXTENSIONS', [
    'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar',
    'exe', 'bat', 'cmd', 'sh', 'bash', 'vbs', 'vbe', 'js', 'jse',
    'cgi', 'pl', 'py', 'dll', 'scr', 'msi', 'com', 'jar', 'apk',
    'hta', 'reg', 'ps1', 'ps2', 'wsf', 'wsh'
]);

// Allowed categories mapping
define('FILE_CATEGORIES', [
    'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'csv', 'odt', 'ods', 'odp', 'md'],
    'image'    => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'tiff', 'avif'],
    'video'    => ['mp4', 'webm', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'm4v'],
    'audio'    => ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac', 'wma'],
    'archive'  => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'iso'],
    'code'     => ['html', 'css', 'json', 'xml', 'sql', 'c', 'cpp', 'cs', 'java', 'ts', 'go', 'rs', 'swift', 'kt']
]);

// Base URL helper
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
    
    // Normalize script directory so we get the web root of this app
    if (basename($scriptDir) === 'api') {
        $scriptDir = dirname($scriptDir);
        $scriptDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
    }
    
    return $protocol . $host . ($scriptDir === '/' || $scriptDir === '.' ? '' : $scriptDir);
}
