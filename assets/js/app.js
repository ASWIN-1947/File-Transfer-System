/**
 * NovaTransfer - Frontend JavaScript Application
 * Handles drag-and-drop uploads, real-time XHR progress, client-side validation,
 * clipboard copy, QR code generation, theme toggle, and file management.
 */

(function () {
    'use strict';

    // Application State
    let selectedFile = null;
    let currentXhr = null;
    let uploadStartTime = 0;
    let lastLoadedBytes = 0;
    let lastTime = 0;
    let activeCategory = 'all';
    let searchQuery = '';
    let sortOrder = 'newest';
    let deleteTargetId = null;
    let searchDebounceTimeout = null;

    // DOM Elements
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('fileInput');
    const dropzoneDefault = document.getElementById('dropzoneDefault');
    const selectedFilePreview = document.getElementById('selectedFilePreview');
    const selectedFileName = document.getElementById('selectedFileName');
    const selectedFileSize = document.getElementById('selectedFileSize');
    const btnCancelSelection = document.getElementById('btnCancelSelection');
    const uploadAlert = document.getElementById('uploadAlert');
    const uploadAlertMsg = document.getElementById('uploadAlertMsg');

    const toggleOptionsBtn = document.getElementById('toggleOptionsBtn');
    const transferOptionsAccordion = document.getElementById('transferOptionsAccordion');
    const uploadPassword = document.getElementById('uploadPassword');
    const uploadExpiry = document.getElementById('uploadExpiry');
    const uploadDescription = document.getElementById('uploadDescription');

    const uploadBtnWrap = document.getElementById('uploadBtnWrap');
    const startUploadBtn = document.getElementById('startUploadBtn');

    const uploadProgressSection = document.getElementById('uploadProgressSection');
    const progressFileName = document.getElementById('progressFileName');
    const progressSpeed = document.getElementById('progressSpeed');
    const progressPercentage = document.getElementById('progressPercentage');
    const progressBarFill = document.getElementById('progressBarFill');
    const progressBytes = document.getElementById('progressBytes');
    const progressEta = document.getElementById('progressEta');
    const cancelUploadBtn = document.getElementById('cancelUploadBtn');

    const uploadSuccessSection = document.getElementById('uploadSuccessSection');
    const successFileNameLabel = document.getElementById('successFileNameLabel');
    const shareUrlInput = document.getElementById('shareUrlInput');
    const copyShareUrlBtn = document.getElementById('copyShareUrlBtn');
    const copyShareBtnText = document.getElementById('copyShareBtnText');
    const btnShowSuccessQr = document.getElementById('btnShowSuccessQr');
    const btnViewSharePage = document.getElementById('btnViewSharePage');
    const btnUploadAnother = document.getElementById('btnUploadAnother');

    const filesContainer = document.getElementById('filesContainer');
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const sortSelect = document.getElementById('sortSelect');
    const categoryPills = document.getElementById('categoryPills');
    const btnRefreshFiles = document.getElementById('btnRefreshFiles');

    const themeToggle = document.getElementById('themeToggle');
    const qrModal = document.getElementById('qrModal');
    const deleteModal = document.getElementById('deleteModal');
    const deleteFileNameTarget = document.getElementById('deleteFileNameTarget');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    const statFilesCount = document.getElementById('statFilesCount');
    const statStorageTotal = document.getElementById('statStorageTotal');
    const statDownloadsCount = document.getElementById('statDownloadsCount');

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initDropzoneEvents();
        initOptionsAccordion();
        initSearchAndFilters();
        if (filesContainer) {
            loadFiles();
        }
    });

    // -------------------------------------------------------------------------
    // Theme Management
    // -------------------------------------------------------------------------
    function initTheme() {
        const savedTheme = localStorage.getItem('nova_theme') || 'dark';
        if (savedTheme === 'light') {
            document.body.classList.remove('dark-theme');
            document.body.classList.add('light-theme');
        } else {
            document.body.classList.remove('light-theme');
            document.body.classList.add('dark-theme');
        }

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const isLight = document.body.classList.contains('light-theme');
                if (isLight) {
                    document.body.classList.remove('light-theme');
                    document.body.classList.add('dark-theme');
                    localStorage.setItem('nova_theme', 'dark');
                    showToast('Switched to Dark Mode', 'info');
                } else {
                    document.body.classList.remove('dark-theme');
                    document.body.classList.add('light-theme');
                    localStorage.setItem('nova_theme', 'light');
                    showToast('Switched to Light Mode', 'info');
                }
            });
        }
    }

    // -------------------------------------------------------------------------
    // Drag & Drop / File Selection
    // -------------------------------------------------------------------------
    function initDropzoneEvents() {
        if (!dropzone || !fileInput) return;

        // Click to browse
        dropzone.addEventListener('click', (e) => {
            if (e.target.closest('#btnCancelSelection')) return;
            fileInput.click();
        });

        // Keydown enter/space
        dropzone.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                fileInput.click();
            }
        });

        // Drag events
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('drag-over');
            });
        });

        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                handleFileSelection(dt.files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files && e.target.files.length > 0) {
                handleFileSelection(e.target.files[0]);
            }
        });

        if (btnCancelSelection) {
            btnCancelSelection.addEventListener('click', (e) => {
                e.stopPropagation();
                resetFileSelection();
            });
        }

        if (startUploadBtn) {
            startUploadBtn.addEventListener('click', startUpload);
        }

        if (cancelUploadBtn) {
            cancelUploadBtn.addEventListener('click', cancelUpload);
        }

        if (btnUploadAnother) {
            btnUploadAnother.addEventListener('click', () => {
                resetFileSelection();
                uploadSuccessSection.style.display = 'none';
                dropzone.style.display = 'block';
                uploadBtnWrap.style.display = 'block';
            });
        }
    }

    // -------------------------------------------------------------------------
    // Client-Side File Validation
    // -------------------------------------------------------------------------
    function handleFileSelection(file) {
        hideAlert();

        if (!file) {
            resetFileSelection();
            return;
        }

        const maxBytes = window.NOVA_CONFIG?.maxFileSize || (40 * 1024 * 1024);
        const maxHuman = window.NOVA_CONFIG?.maxFileSizeHuman || '40 MB';
        const blockedExts = window.NOVA_CONFIG?.blockedExtensions || [
            'php', 'exe', 'bat', 'cmd', 'sh', 'vbs', 'js', 'cgi', 'pl', 'dll', 'scr', 'msi'
        ];

        // 1. Validate file size
        if (file.size <= 0) {
            showAlert('Selected file is empty (0 bytes). Please select a valid file.');
            resetFileSelection();
            return;
        }

        if (file.size > maxBytes) {
            showAlert(`File exceeds the maximum allowed size of ${maxHuman} (${formatBytes(file.size)} selected).`);
            resetFileSelection();
            return;
        }

        // 2. Validate dangerous extensions
        const fileName = file.name;
        const ext = fileName.includes('.') ? fileName.split('.').pop().toLowerCase() : '';

        if (blockedExts.includes(ext)) {
            showAlert(`Executable file type (.${ext}) is blocked for security reasons.`);
            resetFileSelection();
            return;
        }

        // Validation Passed!
        selectedFile = file;

        // Display preview
        if (dropzoneDefault) dropzoneDefault.style.display = 'none';
        if (selectedFilePreview) selectedFilePreview.style.display = 'flex';
        if (selectedFileName) selectedFileName.textContent = file.name;
        if (selectedFileSize) selectedFileSize.textContent = formatBytes(file.size);

        if (startUploadBtn) {
            startUploadBtn.disabled = false;
        }
    }

    function resetFileSelection() {
        selectedFile = null;
        if (fileInput) fileInput.value = '';
        if (dropzoneDefault) dropzoneDefault.style.display = 'block';
        if (selectedFilePreview) selectedFilePreview.style.display = 'none';
        if (startUploadBtn) startUploadBtn.disabled = true;
    }

    function showAlert(msg) {
        if (!uploadAlert || !uploadAlertMsg) return;
        uploadAlertMsg.textContent = msg;
        uploadAlert.style.display = 'flex';
    }

    function hideAlert() {
        if (uploadAlert) uploadAlert.style.display = 'none';
    }

    // -------------------------------------------------------------------------
    // Accordion Options Toggle
    // -------------------------------------------------------------------------
    function initOptionsAccordion() {
        if (!toggleOptionsBtn || !transferOptionsAccordion) return;
        toggleOptionsBtn.addEventListener('click', () => {
            transferOptionsAccordion.classList.toggle('open');
        });
    }

    // -------------------------------------------------------------------------
    // File Upload via XHR with Real-Time Progress
    // -------------------------------------------------------------------------
    function startUpload() {
        if (!selectedFile) return;

        hideAlert();

        // Prepare FormData
        const formData = new FormData();
        formData.append('file', selectedFile);

        const password = uploadPassword ? uploadPassword.value.trim() : '';
        const expiry = uploadExpiry ? uploadExpiry.value : 'never';
        const description = uploadDescription ? uploadDescription.value.trim() : '';

        if (password) {
            if (password.length < 4) {
                showAlert('Password must be at least 4 characters long.');
                return;
            }
            formData.append('password', password);
        }

        formData.append('expiry', expiry);
        if (description) {
            formData.append('description', description);
        }

        // UI Transition to Progress Mode
        dropzone.style.display = 'none';
        uploadBtnWrap.style.display = 'none';
        transferOptionsAccordion.style.display = 'none';
        uploadProgressSection.style.display = 'block';

        if (progressFileName) progressFileName.textContent = selectedFile.name;
        if (progressBarFill) progressBarFill.style.width = '0%';
        if (progressPercentage) progressPercentage.textContent = '0%';
        if (progressSpeed) progressSpeed.textContent = 'Starting...';
        if (progressBytes) progressBytes.textContent = `0 MB / ${formatBytes(selectedFile.size)}`;
        if (progressEta) progressEta.textContent = 'Calculating ETA...';

        uploadStartTime = Date.now();
        lastTime = uploadStartTime;
        lastLoadedBytes = 0;

        currentXhr = new XMLHttpRequest();

        // Track Progress
        currentXhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percent = Math.min(Math.round((e.loaded / e.total) * 100), 100);
                if (progressBarFill) progressBarFill.style.width = `${percent}%`;
                if (progressPercentage) progressPercentage.textContent = `${percent}%`;

                // Speed calculation
                const now = Date.now();
                const timeDiff = (now - lastTime) / 1000; // seconds

                if (timeDiff >= 0.3) {
                    const bytesDiff = e.loaded - lastLoadedBytes;
                    const speedBytesPerSec = bytesDiff / timeDiff;

                    if (progressSpeed) {
                        progressSpeed.textContent = `${formatBytes(speedBytesPerSec)}/s`;
                    }

                    // ETA calculation
                    const remainingBytes = e.total - e.loaded;
                    if (speedBytesPerSec > 0) {
                        const etaSecs = Math.ceil(remainingBytes / speedBytesPerSec);
                        if (progressEta) {
                            progressEta.textContent = formatEta(etaSecs);
                        }
                    }

                    lastTime = now;
                    lastLoadedBytes = e.loaded;
                }

                if (progressBytes) {
                    progressBytes.textContent = `${formatBytes(e.loaded)} / ${formatBytes(e.total)}`;
                }
            }
        };

        // Complete handler
        currentXhr.onload = () => {
            let res = null;
            try {
                res = JSON.parse(currentXhr.responseText);
            } catch (err) {
                res = { status: 'error', message: 'Invalid server response. Please check server logs.' };
            }

            if (currentXhr.status >= 200 && currentXhr.status < 300 && res.status === 'success') {
                handleUploadSuccess(res.file);
            } else {
                handleUploadError(res.message || 'Upload failed with HTTP status ' + currentXhr.status);
            }
        };

        // Error handler
        currentXhr.onerror = () => {
            handleUploadError('Network error occurred during file upload. Please check your connection.');
        };

        // Abort handler
        currentXhr.onabort = () => {
            showToast('Upload was cancelled.', 'info');
            resetUploadForm();
        };

        // Send request
        currentXhr.open('POST', 'api/upload.php', true);
        currentXhr.send(formData);
    }

    function cancelUpload() {
        if (currentXhr) {
            currentXhr.abort();
            currentXhr = null;
        }
    }

    function handleUploadSuccess(fileData) {
        uploadProgressSection.style.display = 'none';
        uploadSuccessSection.style.display = 'block';

        if (successFileNameLabel) {
            successFileNameLabel.textContent = `${fileData.name} (${fileData.formatted_size})`;
        }

        if (shareUrlInput) {
            shareUrlInput.value = fileData.share_url;
        }

        if (btnViewSharePage) {
            btnViewSharePage.href = fileData.share_url;
        }

        if (btnShowSuccessQr) {
            btnShowSuccessQr.onclick = () => {
                openQrModal(fileData.share_url, fileData.name);
            };
        }

        showToast('File uploaded successfully!', 'success');

        // Reset form fields
        if (uploadPassword) uploadPassword.value = '';
        if (uploadDescription) uploadDescription.value = '';
        if (uploadExpiry) uploadExpiry.value = 'never';

        // Reload dashboard files list and stats
        loadFiles();
    }

    function handleUploadError(errMsg) {
        uploadProgressSection.style.display = 'none';
        dropzone.style.display = 'block';
        uploadBtnWrap.style.display = 'block';
        transferOptionsAccordion.style.display = 'block';
        showAlert(errMsg);
        showToast(errMsg, 'error');
    }

    function resetUploadForm() {
        uploadProgressSection.style.display = 'none';
        uploadSuccessSection.style.display = 'none';
        dropzone.style.display = 'block';
        uploadBtnWrap.style.display = 'block';
        transferOptionsAccordion.style.display = 'block';
        resetFileSelection();
    }

    // -------------------------------------------------------------------------
    // Files List Dashboard, Search & Filters
    // -------------------------------------------------------------------------
    function initSearchAndFilters() {
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();
                if (clearSearchBtn) {
                    clearSearchBtn.style.display = query ? 'block' : 'none';
                }

                clearTimeout(searchDebounceTimeout);
                searchDebounceTimeout = setTimeout(() => {
                    searchQuery = query;
                    loadFiles();
                }, 300);
            });
        }

        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', () => {
                searchInput.value = '';
                clearSearchBtn.style.display = 'none';
                searchQuery = '';
                loadFiles();
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                sortOrder = e.target.value;
                loadFiles();
            });
        }

        if (categoryPills) {
            categoryPills.addEventListener('click', (e) => {
                const btn = e.target.closest('.cat-pill');
                if (!btn) return;

                document.querySelectorAll('.cat-pill').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                activeCategory = btn.getAttribute('data-category') || 'all';
                loadFiles();
            });
        }

        if (btnRefreshFiles) {
            btnRefreshFiles.addEventListener('click', () => {
                btnRefreshFiles.classList.add('spinning');
                loadFiles(() => {
                    btnRefreshFiles.classList.remove('spinning');
                    showToast('Files list refreshed', 'info');
                });
            });
        }
    }

    async function loadFiles(callback) {
        if (!filesContainer) return;

        try {
            const params = new URLSearchParams({
                category: activeCategory,
                search: searchQuery,
                sort: sortOrder
            });

            const res = await fetch(`api/files.php?${params.toString()}`);
            const data = await res.json();

            if (res.ok && data.status === 'success') {
                renderFiles(data.files);
                updateStats(data.stats);
            } else {
                filesContainer.innerHTML = `
                    <div class="empty-state">
                        <p class="empty-state-title">Failed to load files</p>
                        <p class="empty-state-text">${data.message || 'Server error occurred'}</p>
                    </div>
                `;
            }
        } catch (err) {
            filesContainer.innerHTML = `
                <div class="empty-state">
                    <p class="empty-state-title">Connection Error</p>
                    <p class="empty-state-text">Could not communicate with the file management API.</p>
                </div>
            `;
        } finally {
            if (typeof callback === 'function') callback();
        }
    }

    function renderFiles(files) {
        if (!files || files.length === 0) {
            filesContainer.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"></path><path d="M12 12v9"></path><path d="m16 16-4-4-4 4"></path></svg>
                    </div>
                    <h3 class="empty-state-title">No files found</h3>
                    <p class="empty-state-text">
                        ${searchQuery || activeCategory !== 'all' ? 'Try adjusting your search or category filter.' : 'Drag & drop a file above to begin your first secure transfer.'}
                    </p>
                </div>
            `;
            return;
        }

        let html = '';
        files.forEach(file => {
            const cat = file.category_meta || { label: 'File', color: 'slate', icon: 'file' };
            const ext = (file.extension || 'file').toUpperCase();

            // SVG icon based on category
            let catSvg = '';
            if (file.category === 'image') {
                catSvg = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>`;
            } else if (file.category === 'video') {
                catSvg = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect width="14" height="14" x="1" y="5" rx="2" ry="2"/></svg>`;
            } else if (file.category === 'audio') {
                catSvg = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>`;
            } else if (file.category === 'archive') {
                catSvg = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg>`;
            } else if (file.category === 'code') {
                catSvg = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>`;
            } else {
                catSvg = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>`;
            }

            const safeOriginalName = escapeHtml(file.original_name);
            const safeShareUrl = escapeHtml(file.share_url);
            const safeDirectDownloadUrl = escapeHtml(file.direct_download_url);

            html += `
                <div class="file-card glass-panel" data-id="${file.id}">
                    <div>
                        <div class="file-card-top">
                            <div class="file-avatar ${cat.color}">
                                ${catSvg}
                            </div>
                            <div class="file-meta-top">
                                <h3 class="file-title" title="${safeOriginalName}">${safeOriginalName}</h3>
                                <p class="file-subtext font-mono">${file.formatted_size} &bull; ${file.time_ago}</p>
                            </div>
                        </div>

                        ${file.description ? `<p class="file-note">"${escapeHtml(file.description)}"</p>` : ''}

                        <div class="file-badges-row">
                            <span class="badge badge-${cat.color}">.${ext}</span>
                            <span class="badge badge-slate" title="Total downloads">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                ${file.downloads_count}
                            </span>
                            ${file.has_password ? `
                                <span class="badge badge-lock" title="Password protected">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    Protected
                                </span>
                            ` : ''}
                            ${file.expires_in_human && file.expires_in_human !== 'Never' ? `
                                <span class="badge badge-expiry" title="Expiration">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    ${file.expires_in_human}
                                </span>
                            ` : ''}
                        </div>
                    </div>

                    <div class="file-card-actions">
                        <button type="button" class="btn btn-secondary btn-sm btn-copy-link" onclick="copyShareLink('${safeShareUrl}', this)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                            <span>Copy Link</span>
                        </button>

                        <button type="button" class="btn-icon" title="Show QR Code" onclick="openQrModal('${safeShareUrl}', '${escapeHtml(file.original_name)}')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></svg>
                        </button>

                        <a href="${safeShareUrl}" target="_blank" class="btn-icon" title="View Recipient Page">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        </a>

                        <a href="${safeDirectDownloadUrl}" class="btn-icon" title="Download File Directly" download="${safeOriginalName}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </a>

                        <button type="button" class="btn-icon" title="Delete File" onclick="openDeleteModal('${file.id}', '${escapeHtml(file.original_name)}')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        </button>
                    </div>
                </div>
            `;
        });

        filesContainer.innerHTML = html;
    }

    function updateStats(stats) {
        if (!stats) return;
        if (statFilesCount) statFilesCount.textContent = stats.total_files ?? 0;
        if (statStorageTotal) statStorageTotal.textContent = stats.total_formatted_size ?? '0 B';
        if (statDownloadsCount) statDownloadsCount.textContent = stats.total_downloads ?? 0;
    }

    // -------------------------------------------------------------------------
    // Delete File Flow
    // -------------------------------------------------------------------------
    window.openDeleteModal = function (id, filename) {
        deleteTargetId = id;
        if (deleteFileNameTarget) deleteFileNameTarget.textContent = `"${filename}"`;
        if (deleteModal) deleteModal.style.display = 'flex';
    };

    window.closeDeleteModal = function () {
        deleteTargetId = null;
        if (deleteModal) deleteModal.style.display = 'none';
    };

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', async () => {
            if (!deleteTargetId) return;

            confirmDeleteBtn.disabled = true;
            try {
                const res = await fetch(`api/files.php?id=${encodeURIComponent(deleteTargetId)}`, {
                    method: 'DELETE'
                });
                const data = await res.json();

                if (res.ok && data.status === 'success') {
                    showToast('File deleted successfully', 'success');
                    closeDeleteModal();
                    loadFiles();
                } else {
                    showToast(data.message || 'Failed to delete file', 'error');
                }
            } catch (err) {
                showToast('Network error while deleting file', 'error');
            } finally {
                confirmDeleteBtn.disabled = false;
            }
        });
    }

    // -------------------------------------------------------------------------
    // QR Code Modal
    // -------------------------------------------------------------------------
    window.openQrModal = function (url, filename) {
        if (!qrModal) return;

        const container = document.getElementById('qrCanvasContainer');
        const nameLabel = document.getElementById('qrFileName');
        const linkInput = document.getElementById('qrLinkInput');

        if (container) {
            container.innerHTML = '';
            if (typeof QRCode !== 'undefined') {
                try {
                    new QRCode(container, {
                        text: url,
                        width: 180,
                        height: 180,
                        typeNumber: 0,
                        colorDark: "#0f172a",
                        colorLight: "#ffffff"
                    });
                } catch (e) {
                    container.innerHTML = '<p style="color:var(--text-muted);font-size:0.8rem;">(QR code preview unavailable)</p>';
                }
            }
        }

        if (nameLabel) nameLabel.textContent = filename || 'File Download';
        if (linkInput) linkInput.value = url;

        qrModal.style.display = 'flex';
    };

    window.closeQrModal = function () {
        if (qrModal) qrModal.style.display = 'none';
    };

    // -------------------------------------------------------------------------
    // Clipboard Copy Helpers
    // -------------------------------------------------------------------------
    window.copyShareLink = function (url, btnElement) {
        copyToClipboard(url, () => {
            showToast('Download link copied to clipboard!', 'success');
            if (btnElement) {
                const originalHtml = btnElement.innerHTML;
                btnElement.innerHTML = `
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Copied!</span>
                `;
                btnElement.classList.add('btn-copied');
                setTimeout(() => {
                    btnElement.innerHTML = originalHtml;
                    btnElement.classList.remove('btn-copied');
                }, 2000);
            }
        });
    };

    window.copyUploadLink = function () {
        if (!shareUrlInput) return;
        copyToClipboard(shareUrlInput.value, () => {
            showToast('Link copied to clipboard!', 'success');
            if (copyShareBtnText) {
                copyShareBtnText.textContent = 'Copied!';
                setTimeout(() => {
                    copyShareBtnText.textContent = 'Copy Link';
                }, 2000);
            }
        });
    };

    window.copyInput = function (inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        copyToClipboard(input.value, () => {
            showToast('Copied to clipboard!', 'success');
        });
    };

    function copyToClipboard(text, onSuccess) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                if (typeof onSuccess === 'function') onSuccess();
            }).catch(() => {
                fallbackCopy(text, onSuccess);
            });
        } else {
            fallbackCopy(text, onSuccess);
        }
    }

    function fallbackCopy(text, onSuccess) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            if (typeof onSuccess === 'function') onSuccess();
        } catch (err) {
            showToast('Could not copy link automatically.', 'error');
        }
        document.body.removeChild(textarea);
    }

    // -------------------------------------------------------------------------
    // Toast Notification System
    // -------------------------------------------------------------------------
    window.showToast = function (message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        let iconSvg = '';
        if (type === 'success') {
            iconSvg = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`;
        } else if (type === 'error') {
            iconSvg = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;
        } else {
            iconSvg = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`;
        }

        toast.innerHTML = `
            ${iconSvg}
            <span>${escapeHtml(message)}</span>
        `;

        container.appendChild(toast);

        // Auto remove after 4 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 4000);
    };

    // -------------------------------------------------------------------------
    // Utility Helpers
    // -------------------------------------------------------------------------
    function formatBytes(bytes, decimals = 2) {
        if (!+bytes) return '0 B';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    }

    function formatEta(seconds) {
        if (!seconds || seconds <= 0) return 'Few seconds remaining';
        if (seconds < 60) return `${seconds}s remaining`;
        const mins = Math.floor(seconds / 60);
        const remainingSecs = seconds % 60;
        return `${mins}m ${remainingSecs}s remaining`;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

})();
