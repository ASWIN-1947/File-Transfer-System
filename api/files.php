<?php
/**
 * API: File Management (Listing & Deletion)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Utils.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/Storage.php';

Security::setSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET: List files and retrieve system stats
if ($method === 'GET') {
    $category = $_GET['category'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'newest';

    $files = Storage::getFiles($category, $search, $sort);
    $stats = Storage::getStats();

    Utils::sendJsonResponse([
        'status' => 'success',
        'count' => count($files),
        'files' => $files,
        'stats' => $stats
    ]);
}

// Handle DELETE: Delete a file
if ($method === 'DELETE' || ($method === 'POST' && ($_POST['action'] ?? '') === 'delete')) {
    $id = '';

    if ($method === 'DELETE') {
        // Can be query parameter or JSON body
        if (!empty($_GET['id'])) {
            $id = trim($_GET['id']);
        } else {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            $id = trim($data['id'] ?? '');
        }
    } else {
        $id = trim($_POST['id'] ?? '');
    }

    if (empty($id)) {
        Utils::sendJsonResponse([
            'status' => 'error',
            'message' => 'Missing file ID for deletion.'
        ], 400);
    }

    $deleted = Storage::deleteFile($id);
    if ($deleted) {
        Utils::sendJsonResponse([
            'status' => 'success',
            'message' => 'File deleted successfully from storage and records.'
        ]);
    } else {
        Utils::sendJsonResponse([
            'status' => 'error',
            'message' => 'File not found or already deleted.'
        ], 404);
    }
}

// Unsupported method
Utils::sendJsonResponse([
    'status' => 'error',
    'message' => 'Method not allowed.'
], 405);
