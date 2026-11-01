<?php
/**
 * Storage and JSON Metadata Handler for NovaTransfer
 * Safely stores, retrieves, and updates file metadata in data/files.json
 * using file locking (flock) to prevent concurrency collisions.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/Security.php';

class Storage {

    private static $initialized = false;

    /**
     * Ensure storage and data directories, .htaccess guards, and files.json exist
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }

        if (!is_dir(DATA_DIR)) {
            @mkdir(DATA_DIR, 0755, true);
        }
        if (!is_dir(STORAGE_DIR)) {
            @mkdir(STORAGE_DIR, 0755, true);
        }

        if (!file_exists(METADATA_FILE)) {
            file_put_contents(METADATA_FILE, json_encode([], JSON_PRETTY_PRINT));
        }

        self::$initialized = true;
    }

    /**
     * Read all raw metadata records from data/files.json with shared lock
     */
    private static function readAllRaw() {
        self::init();

        if (!file_exists(METADATA_FILE)) {
            return [];
        }

        $fp = fopen(METADATA_FILE, 'r');
        if (!$fp) {
            return [];
        }

        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Write raw metadata array to data/files.json with exclusive lock
     */
    private static function writeAllRaw(array $files) {
        self::init();

        $fp = fopen(METADATA_FILE, 'c+');
        if (!$fp) {
            return false;
        }

        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            $json = json_encode(array_values($files), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }

        fclose($fp);
        return false;
    }

    /**
     * Purge expired files from disk and metadata
     */
    public static function purgeExpiredFiles() {
        self::init();
        if (!file_exists(METADATA_FILE)) {
            return;
        }

        $files = self::readAllRaw();
        $currentTime = time();
        $updatedFiles = [];
        $changed = false;

        foreach ($files as $file) {
            $isExpired = false;

            // Check time-based expiration
            if (!empty($file['expires_at']) && $file['expires_at'] < $currentTime) {
                $isExpired = true;
            }

            // Check download-count-based expiration (e.g. burn after 1 download)
            if (!empty($file['max_downloads']) && ($file['downloads_count'] ?? 0) >= $file['max_downloads']) {
                $isExpired = true;
            }

            if ($isExpired) {
                // Remove physical file from storage safely
                $storedPath = STORAGE_DIR . DIRECTORY_SEPARATOR . $file['stored_filename'];
                if (file_exists($storedPath) && Security::isPathContained(STORAGE_DIR, $storedPath)) {
                    @unlink($storedPath);
                }
                $changed = true;
            } else {
                $updatedFiles[] = $file;
            }
        }

        if ($changed) {
            self::writeAllRaw($updatedFiles);
        }
    }

    /**
     * Retrieve all active files with optional filtering, searching, and sorting
     */
    public static function getFiles($filterCategory = '', $search = '', $sort = 'newest') {
        self::purgeExpiredFiles();
        $files = self::readAllRaw();
        $baseUrl = getBaseUrl();

        $results = [];
        $searchLower = mb_strtolower(trim($search));

        foreach ($files as $file) {
            // Category filter
            if (!empty($filterCategory) && $filterCategory !== 'all') {
                if (($file['category'] ?? 'other') !== strtolower($filterCategory)) {
                    continue;
                }
            }

            // Search filter
            if (!empty($searchLower)) {
                $origLower = mb_strtolower($file['original_name'] ?? '');
                $descLower = mb_strtolower($file['description'] ?? '');
                if (strpos($origLower, $searchLower) === false && strpos($descLower, $searchLower) === false) {
                    continue;
                }
            }

            // Enrich file details for UI
            $file['formatted_size'] = Utils::formatBytes($file['size'] ?? 0);
            $file['time_ago'] = Utils::timeAgo($file['uploaded_timestamp'] ?? time());
            $file['category_meta'] = Utils::getCategoryMeta($file['category'] ?? 'other');
            $file['has_password'] = !empty($file['password_hash']);
            $file['share_url'] = $baseUrl . '/download.php?token=' . urlencode($file['share_token']);
            $file['direct_download_url'] = $baseUrl . '/api/download.php?token=' . urlencode($file['share_token']);

            // Expiration note
            if (!empty($file['expires_at'])) {
                $remainingSecs = $file['expires_at'] - time();
                $file['expires_in_human'] = $remainingSecs > 0 ? Utils::timeAgo(time() - $remainingSecs) : 'Expired';
            } else if (!empty($file['max_downloads'])) {
                $file['expires_in_human'] = ($file['max_downloads'] - ($file['downloads_count'] ?? 0)) . ' download left';
            } else {
                $file['expires_in_human'] = 'Never';
            }

            // Strip sensitive password hash before client delivery
            unset($file['password_hash']);

            $results[] = $file;
        }

        // Sorting
        switch ($sort) {
            case 'oldest':
                usort($results, fn($a, $b) => ($a['uploaded_timestamp'] ?? 0) <=> ($b['uploaded_timestamp'] ?? 0));
                break;
            case 'size_desc':
                usort($results, fn($a, $b) => ($b['size'] ?? 0) <=> ($a['size'] ?? 0));
                break;
            case 'size_asc':
                usort($results, fn($a, $b) => ($a['size'] ?? 0) <=> ($b['size'] ?? 0));
                break;
            case 'name_asc':
                usort($results, fn($a, $b) => strcasecmp($a['original_name'] ?? '', $b['original_name'] ?? ''));
                break;
            case 'newest':
            default:
                usort($results, fn($a, $b) => ($b['uploaded_timestamp'] ?? 0) <=> ($a['uploaded_timestamp'] ?? 0));
                break;
        }

        return $results;
    }

    /**
     * Get single file record by UUID
     */
    public static function getFileById($id) {
        $files = self::readAllRaw();
        foreach ($files as $file) {
            if (($file['id'] ?? '') === $id) {
                return $file;
            }
        }
        return null;
    }

    /**
     * Get single file record by share token
     */
    public static function getFileByToken($token) {
        $files = self::readAllRaw();
        foreach ($files as $file) {
            if (($file['share_token'] ?? '') === $token) {
                return $file;
            }
        }
        return null;
    }

    /**
     * Store metadata record into JSON
     */
    public static function saveFileMetadata(array $record) {
        $files = self::readAllRaw();
        // Insert at beginning for fast retrieval
        array_unshift($files, $record);
        return self::writeAllRaw($files);
    }

    /**
     * Increment download count safely
     */
    public static function recordDownload($id) {
        $files = self::readAllRaw();
        $targetFile = null;

        foreach ($files as &$file) {
            if (($file['id'] ?? '') === $id) {
                $file['downloads_count'] = ($file['downloads_count'] ?? 0) + 1;
                $file['last_downloaded_at'] = date('Y-m-d H:i:s');
                $targetFile = $file;
                break;
            }
        }

        if ($targetFile) {
            self::writeAllRaw($files);
            // If download exceeded max_downloads, trigger purge
            if (!empty($targetFile['max_downloads']) && $targetFile['downloads_count'] >= $targetFile['max_downloads']) {
                self::purgeExpiredFiles();
            }
        }

        return $targetFile;
    }

    /**
     * Delete file completely (physical file + metadata)
     */
    public static function deleteFile($id) {
        $files = self::readAllRaw();
        $updatedFiles = [];
        $found = false;

        foreach ($files as $file) {
            if (($file['id'] ?? '') === $id) {
                $found = true;
                // Delete physical file safely
                $storedPath = STORAGE_DIR . DIRECTORY_SEPARATOR . $file['stored_filename'];
                if (file_exists($storedPath) && Security::isPathContained(STORAGE_DIR, $storedPath)) {
                    @unlink($storedPath);
                }
            } else {
                $updatedFiles[] = $file;
            }
        }

        if ($found) {
            self::writeAllRaw($updatedFiles);
            return true;
        }

        return false;
    }

    /**
     * Compute system statistics for dashboard
     */
    public static function getStats() {
        self::purgeExpiredFiles();
        $files = self::readAllRaw();

        $totalFiles = count($files);
        $totalBytes = 0;
        $totalDownloads = 0;
        $categoriesCount = [];

        foreach ($files as $f) {
            $totalBytes += ($f['size'] ?? 0);
            $totalDownloads += ($f['downloads_count'] ?? 0);
            $cat = $f['category'] ?? 'other';
            $categoriesCount[$cat] = ($categoriesCount[$cat] ?? 0) + 1;
        }

        return [
            'total_files' => $totalFiles,
            'total_bytes' => $totalBytes,
            'total_formatted_size' => Utils::formatBytes($totalBytes),
            'total_downloads' => $totalDownloads,
            'max_allowed_file_size' => MAX_FILE_SIZE_BYTES,
            'max_allowed_file_size_human' => MAX_FILE_SIZE_HUMAN,
            'categories' => $categoriesCount
        ];
    }
}
