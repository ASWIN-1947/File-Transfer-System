<?php
/**
 * Utility helper functions for NovaTransfer
 */

require_once __DIR__ . '/../config.php';

class Utils {

    /**
     * Format bytes into human readable format (KB, MB, GB)
     */
    public static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Determine file category based on extension and mime type
     */
    public static function getFileCategory($extension, $mimeType = '') {
        $ext = strtolower(trim($extension, '. '));
        
        foreach (FILE_CATEGORIES as $category => $extensions) {
            if (in_array($ext, $extensions)) {
                return $category;
            }
        }

        // Secondary check based on MIME type prefix
        if (!empty($mimeType)) {
            if (strpos($mimeType, 'image/') === 0) return 'image';
            if (strpos($mimeType, 'video/') === 0) return 'video';
            if (strpos($mimeType, 'audio/') === 0) return 'audio';
            if (strpos($mimeType, 'text/') === 0) return 'document';
            if (strpos($mimeType, 'application/pdf') === 0) return 'document';
            if (strpos($mimeType, 'application/zip') === 0 || strpos($mimeType, 'compressed') !== false) return 'archive';
        }

        return 'other';
    }

    /**
     * Get visual badge details (label, color class, icon)
     */
    public static function getCategoryMeta($category) {
        $meta = [
            'document' => ['label' => 'Document', 'color' => 'blue',   'icon' => 'file-text'],
            'image'    => ['label' => 'Image',    'color' => 'emerald','icon' => 'image'],
            'video'    => ['label' => 'Video',    'color' => 'purple', 'icon' => 'film'],
            'audio'    => ['label' => 'Audio',    'color' => 'amber',  'icon' => 'music'],
            'archive'  => ['label' => 'Archive',  'color' => 'orange', 'icon' => 'archive'],
            'code'     => ['label' => 'Code',     'color' => 'cyan',   'icon' => 'code'],
            'other'    => ['label' => 'File',     'color' => 'slate',  'icon' => 'file']
        ];
        return $meta[$category] ?? $meta['other'];
    }

    /**
     * Generate secure UUIDv4
     */
    public static function generateUuid() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generate secure URL-safe share token
     */
    public static function generateShareToken($length = 24) {
        return bin2hex(random_bytes((int)ceil($length / 2)));
    }

    /**
     * Human-friendly time ago
     */
    public static function timeAgo($timestamp) {
        $diff = time() - $timestamp;
        if ($diff < 5) return 'Just now';
        if ($diff < 60) return $diff . ' seconds ago';
        if ($diff < 3600) return floor($diff / 60) . ' mins ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';
        return date('M j, Y', $timestamp);
    }

    /**
     * Send standard JSON response and exit
     */
    public static function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
