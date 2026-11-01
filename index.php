<?php
/**
 * NovaTransfer - Online File Transfer System
 * Main Dashboard & File Transfer Interface
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Utils.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/Storage.php';

Security::setSecurityHeaders();
Storage::init();

$stats = Storage::getStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= APP_TAGLINE ?></title>
    <meta name="description" content="Secure, fast, and private online file transfer system. Upload, share with encrypted links, set expiration, and prevent unauthorized access.">
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

    <!-- Navigation Header -->
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

            <!-- Quick Stats Pill -->
            <div class="nav-stats-pill" id="navStatsPill">
                <div class="stat-pill-item">
                    <span class="stat-pill-label">Files</span>
                    <span class="stat-pill-val font-mono" id="statFilesCount"><?= $stats['total_files'] ?></span>
                </div>
                <div class="stat-pill-divider"></div>
                <div class="stat-pill-item">
                    <span class="stat-pill-label">Storage</span>
                    <span class="stat-pill-val font-mono" id="statStorageTotal"><?= $stats['total_formatted_size'] ?></span>
                </div>
                <div class="stat-pill-divider"></div>
                <div class="stat-pill-item">
                    <span class="stat-pill-label">Downloads</span>
                    <span class="stat-pill-val font-mono" id="statDownloadsCount"><?= $stats['total_downloads'] ?></span>
                </div>
            </div>

            <div class="nav-actions">
                <button id="themeToggle" class="btn-icon" title="Toggle Theme" aria-label="Toggle Dark and Light Mode">
                    <svg class="sun-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                    <svg class="moon-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                </button>
            </div>
        </div>
    </header>

    <main class="main-container">
        <!-- Hero Section -->
        <section class="hero-section">
            <h1 class="hero-title">
                Transfer files <span class="gradient-text">effortlessly & securely</span>
            </h1>
            <p class="hero-subtitle">
                Upload up to <?= MAX_FILE_SIZE_HUMAN ?>, set custom expiration or password protection, and share instant direct download links.
            </p>
        </section>

        <!-- Upload & Transfer Center -->
        <section class="transfer-section">
            <div class="transfer-card glass-panel">
                
                <!-- 1. Drag & Drop Upload Zone -->
                <div id="dropzone" class="dropzone" tabindex="0" role="region" aria-label="File upload dropzone">
                    <input type="file" id="fileInput" class="file-input-hidden" aria-hidden="true">
                    
                    <div class="dropzone-content" id="dropzoneDefault">
                        <div class="dropzone-icon-bubble">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"></path>
                                <path d="M12 12v9"></path>
                                <path d="m16 16-4-4-4 4"></path>
                            </svg>
                        </div>
                        <h2 class="dropzone-title">Drag &amp; drop your file here, or <span class="browse-link">browse</span></h2>
                        <p class="dropzone-hint">
                            Maximum file size: <strong><?= MAX_FILE_SIZE_HUMAN ?></strong> &bull; Any documents, media, or archives
                        </p>
                        <div class="dropzone-security-pill">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <span>Restricted storage &bull; Obfuscated payloads &bull; Direct access blocked</span>
                        </div>
                    </div>

                    <!-- Selected File Preview before upload -->
                    <div class="selected-file-preview" id="selectedFilePreview" style="display: none;">
                        <div class="selected-file-icon" id="selectedFileCategoryIcon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="selected-file-info">
                            <span class="selected-file-name" id="selectedFileName">filename.ext</span>
                            <span class="selected-file-size font-mono" id="selectedFileSize">0 KB</span>
                        </div>
                        <button type="button" class="btn-icon btn-cancel-selection" id="btnCancelSelection" title="Remove file">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>
                </div>

                <!-- Error Alert Box -->
                <div id="uploadAlert" class="alert-box alert-error" style="display: none;" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span id="uploadAlertMsg">An error occurred during upload.</span>
                </div>

                <!-- 2. Transfer Options (Collapsible) -->
                <div class="transfer-options-accordion" id="transferOptionsAccordion">
                    <button type="button" class="accordion-toggle" id="toggleOptionsBtn">
                        <div class="accordion-toggle-left">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                            <span>Security &amp; Expiration Settings</span>
                        </div>
                        <svg class="accordion-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </button>

                    <div class="accordion-body" id="optionsBody">
                        <div class="options-grid">
                            <!-- Password Protection -->
                            <div class="option-field">
                                <label for="uploadPassword" class="form-label">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    <span>Password Protection (Optional)</span>
                                </label>
                                <input type="password" id="uploadPassword" class="form-input" placeholder="Protect file with a password">
                                <small class="form-hint">Recipients will need this password to download.</small>
                            </div>

                            <!-- Expiry Duration -->
                            <div class="option-field">
                                <label for="uploadExpiry" class="form-label">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <span>Expiration Time</span>
                                </label>
                                <select id="uploadExpiry" class="form-input form-select">
                                    <option value="never" selected>Never Expire</option>
                                    <option value="1h">1 Hour</option>
                                    <option value="24h">24 Hours (1 Day)</option>
                                    <option value="7d">7 Days (1 Week)</option>
                                    <option value="30d">30 Days (1 Month)</option>
                                    <option value="1download">Burn after 1 Download</option>
                                </select>
                                <small class="form-hint">Expired files are automatically purged from storage.</small>
                            </div>

                            <!-- File Note / Description -->
                            <div class="option-field full-width">
                                <label for="uploadDescription" class="form-label">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                    <span>File Note / Description (Optional)</span>
                                </label>
                                <input type="text" id="uploadDescription" class="form-input" placeholder="Add a message for the recipient..." maxlength="300">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Upload Trigger Button -->
                <div class="transfer-btn-wrap" id="uploadBtnWrap">
                    <button type="button" id="startUploadBtn" class="btn btn-primary btn-large btn-glow" disabled>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <span>Start Transfer</span>
                    </button>
                </div>

                <!-- 4. Upload Progress Bar & Status (Hidden until upload begins) -->
                <div id="uploadProgressSection" class="upload-progress-box" style="display: none;">
                    <div class="progress-header">
                        <div class="progress-file-meta">
                            <span class="progress-filename" id="progressFileName">Uploading...</span>
                            <span class="progress-speed font-mono" id="progressSpeed">0 KB/s</span>
                        </div>
                        <span class="progress-percentage font-mono" id="progressPercentage">0%</span>
                    </div>

                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" id="progressBarFill" style="width: 0%;"></div>
                    </div>

                    <div class="progress-footer">
                        <span class="progress-bytes font-mono" id="progressBytes">0 MB / 0 MB</span>
                        <span class="progress-eta" id="progressEta">Calculating...</span>
                        <button type="button" class="btn-cancel-upload" id="cancelUploadBtn">Cancel</button>
                    </div>
                </div>

                <!-- 5. Upload Success Result Card (Hidden until complete) -->
                <div id="uploadSuccessSection" class="upload-success-box" style="display: none;">
                    <div class="success-badge-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <h3 class="success-title">File Uploaded Successfully!</h3>
                    <p class="success-subtitle" id="successFileNameLabel">Your file is ready to share.</p>

                    <div class="share-link-input-group">
                        <input type="text" id="shareUrlInput" class="form-input font-mono" readonly>
                        <button type="button" class="btn btn-primary" id="copyShareUrlBtn" onclick="copyUploadLink()">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                            <span id="copyShareBtnText">Copy Link</span>
                        </button>
                    </div>

                    <div class="success-quick-actions">
                        <button type="button" class="btn btn-secondary" id="btnShowSuccessQr">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></svg>
                            <span>QR Code</span>
                        </button>
                        <a href="#" target="_blank" class="btn btn-secondary" id="btnViewSharePage">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            <span>Open Recipient Page</span>
                        </a>
                        <button type="button" class="btn btn-outline" id="btnUploadAnother">
                            <span>Upload Another</span>
                        </button>
                    </div>
                </div>

            </div>
        </section>

        <!-- Uploaded Files Management Section -->
        <section class="files-section">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Uploaded Transfers</h2>
                    <p class="section-subtitle">Manage files, track downloads, copy share links, or delete records.</p>
                </div>
                <button type="button" class="btn btn-outline btn-sm" id="btnRefreshFiles" title="Refresh files list">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>
                    <span>Refresh</span>
                </button>
            </div>

            <!-- Filters, Search & Sort Bar -->
            <div class="filter-controls-bar glass-panel">
                <!-- Search input -->
                <div class="search-wrap">
                    <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" id="searchInput" class="search-input" placeholder="Search files by name or note...">
                    <button type="button" id="clearSearchBtn" class="btn-clear-search" style="display:none;" title="Clear search">&times;</button>
                </div>

                <!-- Sort dropdown -->
                <div class="sort-wrap">
                    <label for="sortSelect" class="sort-label">Sort:</label>
                    <select id="sortSelect" class="form-select sort-select">
                        <option value="newest" selected>Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="size_desc">Largest Size</option>
                        <option value="size_asc">Smallest Size</option>
                        <option value="name_asc">Name (A-Z)</option>
                    </select>
                </div>
            </div>

            <!-- Category Pills -->
            <div class="category-pills" id="categoryPills">
                <button type="button" class="cat-pill active" data-category="all">All Files</button>
                <button type="button" class="cat-pill" data-category="document">Documents</button>
                <button type="button" class="cat-pill" data-category="image">Images</button>
                <button type="button" class="cat-pill" data-category="video">Videos</button>
                <button type="button" class="cat-pill" data-category="audio">Audio</button>
                <button type="button" class="cat-pill" data-category="archive">Archives</button>
                <button type="button" class="cat-pill" data-category="code">Code</button>
            </div>

            <!-- Files Grid Container -->
            <div id="filesContainer" class="files-grid">
                <!-- Dynamically populated via assets/js/app.js -->
                <div class="files-loading-state">
                    <div class="spinner"></div>
                    <p>Loading files...</p>
                </div>
            </div>
        </section>
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
                <p id="qrFileName" class="qr-file-label font-mono"></p>
                <div class="qr-link-copy-box">
                    <input type="text" id="qrLinkInput" class="form-input font-mono" readonly>
                    <button class="btn btn-secondary btn-sm" onclick="copyInput('qrLinkInput')">Copy</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-backdrop" style="display:none;" onclick="closeDeleteModal(event)">
        <div class="modal-content glass-panel confirm-modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 class="modal-title text-danger">Delete File</h3>
                <button class="modal-close-btn" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete <strong id="deleteFileNameTarget">this file</strong>?</p>
                <p class="modal-warning-subtext">This action will remove the file from storage and its share link will stop working immediately.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete File</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="toast-container" role="status" aria-live="polite"></div>

    <!-- Config script object for frontend -->
    <script>
        window.NOVA_CONFIG = {
            maxFileSize: <?= MAX_FILE_SIZE_BYTES ?>,
            maxFileSizeHuman: '<?= MAX_FILE_SIZE_HUMAN ?>',
            blockedExtensions: <?= json_encode(BLOCKED_EXTENSIONS) ?>,
            baseUrl: '<?= getBaseUrl() ?>'
        };
    </script>
    <script src="assets/js/qrcode.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
