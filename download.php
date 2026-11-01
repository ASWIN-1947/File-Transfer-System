<?php
/**
 * NovaTransfer - Public Download & File Details Page
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Utils.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/Storage.php';

Security::setSecurityHeaders();

$token = trim($_GET['token'] ?? '');
$id = trim($_GET['id'] ?? '');

$file = null;
if (!empty($token)) {
    $file = Storage::getFileByToken($token);
} elseif (!empty($id)) {
    $file = Storage::getFileById($id);
}

$isExpired = false;
$expiredReason = '';

if ($file) {
    if (!empty($file['expires_at']) && $file['expires_at'] < time()) {
        $isExpired = true;
        $expiredReason = 'This file has reached its expiration time limit and was automatically removed.';
        Storage::purgeExpiredFiles();
        $file = null;
    } elseif (!empty($file['max_downloads']) && ($file['downloads_count'] ?? 0) >= $file['max_downloads']) {
        $isExpired = true;
        $expiredReason = 'This file was configured for single-use download and has already been accessed.';
        Storage::purgeExpiredFiles();
        $file = null;
    }
}

$hasPassword = !empty($file['password_hash']);
$categoryMeta = $file ? Utils::getCategoryMeta($file['category']) : null;
$baseUrl = getBaseUrl();
$currentUrl = $baseUrl . '/download.php?' . (!empty($token) ? 'token=' . urlencode($token) : 'id=' . urlencode($id));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $file ? htmlspecialchars($file['original_name']) . ' - ' . APP_NAME : 'File Not Found - ' . APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dark-theme">
    <div class="app-background">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
    </div>

    <!-- Header Navigation -->
    <header class="navbar">
        <div class="nav-container">
            <a href="index.php" class="brand-logo">
                <div class="logo-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"></path>
                        <path d="M12 12v9"></path>
                        <path d="m16 16-4-4-4 4"></path>
                    </svg>
                </div>
                <div class="brand-text">
                    <span class="brand-title"><?= APP_NAME ?></span>
                    <span class="brand-badge">SECURE</span>
                </div>
            </a>
            
            <div class="nav-actions">
                <button id="themeToggle" class="btn-icon" title="Toggle Theme" aria-label="Toggle Theme">
                    <svg class="sun-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                    <svg class="moon-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span>Upload Files</span>
                </a>
            </div>
        </div>
    </header>

    <main class="main-container single-page-container">
        <?php if (!$file): ?>
            <!-- Error / Expired Card -->
            <div class="card error-card glass-panel">
                <div class="status-icon-wrapper error-icon-bg">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <h2><?= $isExpired ? 'Link Expired' : 'File Not Found' ?></h2>
                <p class="card-subtitle">
                    <?= $isExpired ? htmlspecialchars($expiredReason) : 'The transfer link you followed is invalid, deleted, or has been removed from the server.' ?>
                </p>
                <div class="card-actions">
                    <a href="index.php" class="btn btn-primary btn-glow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"></path><path d="M12 12v9"></path><path d="m16 16-4-4-4 4"></path></svg>
                        <span>Transfer New Files</span>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Active File Download Card -->
            <div class="card download-card glass-panel">
                <div class="file-hero-badge-wrap">
                    <div class="file-avatar <?= htmlspecialchars($categoryMeta['color']) ?>">
                        <?php if ($file['category'] === 'image'): ?>
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                        <?php elseif ($file['category'] === 'video'): ?>
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect width="14" height="14" x="1" y="5" rx="2" ry="2"/></svg>
                        <?php elseif ($file['category'] === 'audio'): ?>
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                        <?php elseif ($file['category'] === 'archive'): ?>
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg>
                        <?php elseif ($file['category'] === 'code'): ?>
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                        <?php else: ?>
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <?php endif; ?>
                    </div>
                    <span class="badge badge-<?= htmlspecialchars($categoryMeta['color']) ?>">
                        <?= htmlspecialchars($categoryMeta['label']) ?> &bull; .<?= htmlspecialchars(strtoupper($file['extension'] ?: 'BIN')) ?>
                    </span>
                </div>

                <h1 class="file-hero-title" title="<?= htmlspecialchars($file['original_name']) ?>">
                    <?= htmlspecialchars($file['original_name']) ?>
                </h1>

                <?php if (!empty($file['description'])): ?>
                    <p class="file-hero-description">
                        "<?= htmlspecialchars($file['description']) ?>"
                    </p>
                <?php endif; ?>

                <!-- File Metadata Grid -->
                <div class="metadata-grid">
                    <div class="meta-item">
                        <span class="meta-label">File Size</span>
                        <span class="meta-val font-mono"><?= Utils::formatBytes($file['size']) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Uploaded</span>
                        <span class="meta-val"><?= Utils::timeAgo($file['uploaded_timestamp']) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Downloads</span>
                        <span class="meta-val font-mono"><?= (int)$file['downloads_count'] ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Expires</span>
                        <span class="meta-val">
                            <?php 
                            if (!empty($file['expires_at'])) {
                                echo date('M j, Y H:i', $file['expires_at']);
                            } elseif (!empty($file['max_downloads'])) {
                                echo 'After 1 download';
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Password Protection Lock Screen if required -->
                <?php if ($hasPassword): ?>
                    <div id="passwordSection" class="lock-section">
                        <div class="lock-banner">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <span>Password Protected File</span>
                        </div>
                        <p class="lock-hint">The sender secured this file with a password. Please enter it below to unlock the download.</p>
                        
                        <form id="passwordForm" class="password-form" onsubmit="handleDownloadAuth(event)">
                            <div class="input-with-button">
                                <input type="password" id="filePassword" class="form-input" placeholder="Enter file password" required autofocus>
                                <button type="submit" id="unlockBtn" class="btn btn-primary">
                                    <span>Unlock</span>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                                </button>
                            </div>
                            <div id="passwordError" class="auth-error-msg" style="display:none;"></div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Action Controls (Visible directly if no password, or revealed after password entry) -->
                <div id="downloadActionGroup" class="download-action-group" style="<?= $hasPassword ? 'display: none;' : '' ?>">
                    <a id="mainDownloadBtn" 
                       href="api/download.php?token=<?= urlencode($file['share_token']) ?>" 
                       class="btn btn-primary btn-large btn-glow" 
                       download="<?= htmlspecialchars($file['original_name']) ?>">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span>Download Now (<?= Utils::formatBytes($file['size']) ?>)</span>
                    </a>

                    <!-- In-browser preview for safe images and PDFs -->
                    <?php if ($file['category'] === 'image' || $file['mime_type'] === 'application/pdf'): ?>
                        <button id="previewBtn" class="btn btn-secondary" onclick="openPreviewModal()">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <span>Preview</span>
                        </button>
                    <?php endif; ?>

                    <div class="secondary-actions">
                        <button class="btn btn-outline" id="copyLinkBtn" onclick="copyShareLink('<?= htmlspecialchars($currentUrl) ?>')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                            <span id="copyLinkText">Copy Link</span>
                        </button>

                        <button class="btn btn-outline" onclick="openQrModal('<?= htmlspecialchars($currentUrl) ?>', '<?= htmlspecialchars(addslashes($file['original_name'])) ?>')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></svg>
                            <span>QR Code</span>
                        </button>
                    </div>
                </div>

                <!-- Safe Security Verification Badge -->
                <div class="security-footer-badge">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Scanned &amp; Protected &bull; Direct Secure Stream</span>
                </div>
            </div>

            <!-- Preview Modal -->
            <div id="previewModal" class="modal-backdrop" style="display:none;" onclick="closePreviewModal(event)">
                <div class="modal-content glass-panel preview-modal-content" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <h3 class="modal-title"><?= htmlspecialchars($file['original_name']) ?></h3>
                        <button class="modal-close-btn" onclick="closePreviewModal()">&times;</button>
                    </div>
                    <div class="modal-body preview-modal-body">
                        <?php if ($file['category'] === 'image'): ?>
                            <img id="previewImage" src="api/download.php?token=<?= urlencode($file['share_token']) ?>&preview=1" alt="File Preview" class="preview-img">
                        <?php elseif ($file['mime_type'] === 'application/pdf'): ?>
                            <iframe src="api/download.php?token=<?= urlencode($file['share_token']) ?>&preview=1" class="preview-iframe" title="PDF Preview"></iframe>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Global QR Modal -->
    <div id="qrModal" class="modal-backdrop" style="display:none;" onclick="closeQrModal(event)">
        <div class="modal-content glass-panel qr-modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 class="modal-title">Scan on Mobile</h3>
                <button class="modal-close-btn" onclick="closeQrModal()">&times;</button>
            </div>
            <div class="modal-body text-center">
                <div id="qrCanvasContainer" class="qr-canvas-box"></div>
                <p id="qrFileName" class="qr-file-label"></p>
                <div class="qr-link-copy-box">
                    <input type="text" id="qrLinkInput" class="form-input font-mono" readonly>
                    <button class="btn btn-secondary btn-sm" onclick="copyInput('qrLinkInput')">Copy</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="assets/js/qrcode.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        // Password unlock logic for download page
        async function handleDownloadAuth(e) {
            e.preventDefault();
            const passwordInput = document.getElementById('filePassword');
            const errorDiv = document.getElementById('passwordError');
            const unlockBtn = document.getElementById('unlockBtn');
            const password = passwordInput.value.trim();

            if (!password) return;

            unlockBtn.disabled = true;
            errorDiv.style.display = 'none';

            try {
                const token = '<?= urlencode($token) ?>';
                const id = '<?= urlencode($id) ?>';
                const url = `api/download.php?${token ? 'token=' + token : 'id=' + id}&password=${encodeURIComponent(password)}&check_auth=1&ajax=1`;
                
                const response = await fetch(url);
                const data = await response.json();

                if (response.ok && data.status === 'success') {
                    // Password authenticated!
                    document.getElementById('passwordSection').style.display = 'none';
                    const actionGroup = document.getElementById('downloadActionGroup');
                    actionGroup.style.display = 'flex';
                    
                    // Update direct download link with password
                    const downloadBtn = document.getElementById('mainDownloadBtn');
                    const baseHref = downloadBtn.getAttribute('href');
                    downloadBtn.setAttribute('href', baseHref + '&password=' + encodeURIComponent(password));
                    
                    showToast('File unlocked successfully!', 'success');
                } else {
                    errorDiv.innerText = data.message || 'Incorrect password. Please try again.';
                    errorDiv.style.display = 'block';
                    passwordInput.focus();
                }
            } catch (err) {
                errorDiv.innerText = 'Network error verifying password.';
                errorDiv.style.display = 'block';
            } finally {
                unlockBtn.disabled = false;
            }
        }

        function openPreviewModal() {
            const modal = document.getElementById('previewModal');
            if (modal) modal.style.display = 'flex';
        }

        function closePreviewModal(e) {
            const modal = document.getElementById('previewModal');
            if (modal) modal.style.display = 'none';
        }
    </script>
</body>
</html>
