<?php
/**
 * Security, sanitization, and validation helper for NovaTransfer
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Utils.php';

class Security {

    /**
     * Set global security headers on responses
     */
    public static function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Sanitize user supplied filename to prevent path traversal or filesystem issues
     */
    public static function sanitizeFilename($name) {
        // Strip null bytes and control chars
        $name = str_replace(["\0", "\r", "\n"], '', $name);
        
        // Remove any directory path components (both / and \)
        $name = basename(str_replace('\\', '/', $name));

        // Strip leading dots to prevent hidden or traversal names
        $name = ltrim($name, '.');

        // If name becomes empty after sanitization, give a fallback
        if (empty($name)) {
            $name = 'unnamed_file_' . date('Ymd_His');
        }

        // Limit length
        if (mb_strlen($name) > 200) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = pathinfo($name, PATHINFO_FILENAME);
            $name = mb_substr($base, 0, 180) . ($ext ? '.' . $ext : '');
        }

        return $name;
    }

    /**
     * Check if extension is blocked
     */
    public static function isBlockedExtension($extension) {
        $ext = strtolower(trim($extension, '. '));
        return in_array($ext, BLOCKED_EXTENSIONS);
    }

    /**
     * Validate an uploaded file from $_FILES
     * Returns array ['valid' => bool, 'error' => string, 'sanitized_name' => string, 'extension' => string, 'mime' => string, 'size' => int]
     */
    public static function validateUploadedFile($file) {
        if (!isset($file) || !is_array($file)) {
            return ['valid' => false, 'error' => 'No file upload payload detected.'];
        }

        // Check standard PHP upload error codes
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    return [
                        'valid' => false, 
                        'error' => 'File size exceeds maximum allowed limit of ' . MAX_FILE_SIZE_HUMAN . '.'
                    ];
                case UPLOAD_ERR_PARTIAL:
                    return ['valid' => false, 'error' => 'The file was only partially uploaded. Please try again.'];
                case UPLOAD_ERR_NO_FILE:
                    return ['valid' => false, 'error' => 'No file was selected for upload.'];
                case UPLOAD_ERR_NO_TMP_DIR:
                    return ['valid' => false, 'error' => 'Server error: Temporary upload folder missing.'];
                case UPLOAD_ERR_CANT_WRITE:
                    return ['valid' => false, 'error' => 'Server error: Failed to write uploaded file to disk.'];
                default:
                    return ['valid' => false, 'error' => 'Unknown upload error occurred (Code: ' . $file['error'] . ').'];
            }
        }

        // Validate tmp_name exists and is an uploaded file
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Security warning: Uploaded file is not recognized by the server.'];
        }

        // Check size
        $fileSize = (int)$file['size'];
        if ($fileSize <= 0) {
            return ['valid' => false, 'error' => 'The selected file is empty (0 bytes).'];
        }

        if ($fileSize > MAX_FILE_SIZE_BYTES) {
            return [
                'valid' => false, 
                'error' => 'File exceeds the maximum allowed size of ' . MAX_FILE_SIZE_HUMAN . ' (' . Utils::formatBytes($fileSize) . ' uploaded).'
            ];
        }

        // Sanitize filename
        $sanitizedName = self::sanitizeFilename($file['name']);
        $extension = strtolower(pathinfo($sanitizedName, PATHINFO_EXTENSION));

        // Check blocked dangerous extensions
        if (self::isBlockedExtension($extension)) {
            return [
                'valid' => false, 
                'error' => 'Upload rejected: Files with extension ".' . htmlspecialchars($extension) . '" are forbidden for security reasons.'
            ];
        }

        // Determine actual MIME type using FileInfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!$detectedMime) {
            $detectedMime = $file['type'] ?? 'application/octet-stream';
        }

        // Block dangerous server-executable MIME types
        $blockedMimes = [
            'application/x-php',
            'application/x-httpd-php',
            'text/x-php',
            'application/x-msdos-program',
            'application/x-executable',
            'application/x-dosexec'
        ];
        if (in_array(strtolower($detectedMime), $blockedMimes)) {
            return [
                'valid' => false, 
                'error' => 'Upload rejected: Executable or script payload detected (' . htmlspecialchars($detectedMime) . ').'
            ];
        }

        return [
            'valid' => true,
            'sanitized_name' => $sanitizedName,
            'extension' => $extension,
            'mime' => $detectedMime,
            'size' => $fileSize,
            'tmp_name' => $file['tmp_name']
        ];
    }

    /**
     * Prevent Directory Traversal: Verify a file path is strictly inside the target base directory
     */
    public static function isPathContained($baseDir, $filePath) {
        $realBase = realpath($baseDir);
        $realTarget = realpath($filePath);

        if ($realBase === false || $realTarget === false) {
            return false;
        }

        return strpos($realTarget, $realBase) === 0;
    }

    /**
     * Password hashing and verification
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public static function verifyPassword($password, $hash) {
        if (empty($hash)) return true;
        return password_verify($password, $hash);
    }
}
