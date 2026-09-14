/**
 * Pressli CMS - Media Library & Upload Scripts
 * Handles media grid, bulk actions, and multi-file AJAX uploads with progress bars
 * Used by both media library page and media picker modal
 */

(function() {
    'use strict';

    const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    document.addEventListener('DOMContentLoaded', function() {
        initModals();
        initBulkSelect();
        initUploadForm();
    });

    /**
     * Initialize modals
     */
    function initModals() {
        // Upload Modal
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadModal = document.getElementById('uploadModal');
        const closeUploadModal = document.getElementById('closeUploadModal');

        if (uploadBtn && uploadModal) {
            uploadBtn.addEventListener('click', function() {
                uploadModal.classList.add('active');
            });
        }

        if (closeUploadModal && uploadModal) {
            closeUploadModal.addEventListener('click', function() {
                uploadModal.classList.remove('active');
            });
        }

        // View Modal
        const viewModal = document.getElementById('viewModal');
        const closeViewModal = document.getElementById('closeViewModal');
        const viewMediaBtns = document.querySelectorAll('.view-media-btn');

        viewMediaBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                viewModal.classList.add('active');
            });
        });

        if (closeViewModal && viewModal) {
            closeViewModal.addEventListener('click', function() {
                viewModal.classList.remove('active');
            });
        }

        // Close modals when clicking overlay
        const modalOverlays = document.querySelectorAll('.modal-overlay');
        modalOverlays.forEach(function(overlay) {
            overlay.addEventListener('click', function() {
                const modal = overlay.closest('.modal');
                if (modal) {
                    modal.classList.remove('active');
                }
            });
        });

        // Close modals with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const activeModals = document.querySelectorAll('.modal.active');
                activeModals.forEach(function(modal) {
                    modal.classList.remove('active');
                });
            }
        });
    }

    /**
     * Bulk select functionality
     */
    function initBulkSelect() {
        const selectAll = document.getElementById('selectAllMedia');
        const checkboxes = document.querySelectorAll('.row-check');
        const bulkAction = document.getElementById('bulkActionMedia');
        const applyBulk = document.getElementById('applyBulkMedia');

        if (!selectAll || !checkboxes.length) return;

        // Select all
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = selectAll.checked;
            });
            updateBulkState();
        });

        // Individual checkboxes
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                const someChecked = Array.from(checkboxes).some(cb => cb.checked);

                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;

                updateBulkState();
            });
        });

        // Apply bulk action
        if (applyBulk) {
            applyBulk.addEventListener('click', function() {
                const action = bulkAction.value;
                const selected = document.querySelectorAll('.row-check:checked');

                if (!action || !selected.length) return;

                const message = 'Apply "' + bulkAction.options[bulkAction.selectedIndex].text +
                              '" to ' + selected.length + ' item(s)?';

                if (confirm(message)) {
                    console.log('Bulk action:', action, 'Items:', selected.length);
                    alert('Action would be applied to ' + selected.length + ' items');

                    // Reset
                    selected.forEach(function(cb) {
                        cb.checked = false;
                    });
                    selectAll.checked = false;
                    updateBulkState();
                }
            });
        }

        function updateBulkState() {
            const hasSelection = Array.from(checkboxes).some(cb => cb.checked);
            bulkAction.disabled = !hasSelection;
            applyBulk.disabled = !hasSelection;
        }
    }

    /**
     * Initialize AJAX upload form with multiple file support and preview
     */
    function initUploadForm() {
        const uploadForm = document.getElementById('uploadForm');
        if (!uploadForm) return;

        const fileInput = document.getElementById('fileInput');
        const chooseFilesBtn = document.getElementById('chooseFilesBtn');
        const uploadDropzone = document.getElementById('uploadDropzone');
        const filePreviewGrid = document.getElementById('filePreviewGrid');
        const uploadFilesBtn = document.getElementById('uploadFilesBtn');
        const cancelUploadBtn = document.getElementById('cancelUploadBtn');
        const progressContainer = document.getElementById('uploadProgressContainer');

        let selectedFiles = [];

        // Choose files button click
        chooseFilesBtn.addEventListener('click', () => {
            fileInput.click();
        });

        // Dropzone click
        uploadDropzone.addEventListener('click', (e) => {
            if (e.target !== chooseFilesBtn) {
                fileInput.click();
            }
        });

        // Drag and drop
        uploadDropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadDropzone.style.borderColor = '#3b82f6';
            uploadDropzone.style.background = '#eff6ff';
        });

        uploadDropzone.addEventListener('dragleave', () => {
            uploadDropzone.style.borderColor = '#d1d5db';
            uploadDropzone.style.background = '#f9fafb';
        });

        uploadDropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadDropzone.style.borderColor = '#d1d5db';
            uploadDropzone.style.background = '#f9fafb';

            const files = Array.from(e.dataTransfer.files);
            handleFilesSelected(files);
        });

        // File input change
        fileInput.addEventListener('change', () => {
            const files = Array.from(fileInput.files);
            handleFilesSelected(files);
        });

        // Handle files selected
        function handleFilesSelected(files) {
            selectedFiles = files;

            if (files.length === 0) {
                filePreviewGrid.style.display = 'none';
                uploadFilesBtn.style.display = 'none';
                cancelUploadBtn.style.display = 'none';
                return;
            }

            // Show preview grid
            filePreviewGrid.style.display = 'grid';
            filePreviewGrid.innerHTML = '';

            // Generate previews
            files.forEach((file, index) => {
                createFilePreview(file, index);
            });

            // Show upload button
            uploadFilesBtn.style.display = 'inline-block';
            uploadFilesBtn.textContent = files.length === 1 ? 'Upload 1 File' : `Upload ${files.length} Files`;
            cancelUploadBtn.style.display = 'inline-block';
        }

        // Create file preview
        function createFilePreview(file, index) {
            const isImage = file.type.startsWith('image/');
            const previewItem = document.createElement('div');
            previewItem.className = 'media-item';
            previewItem.dataset.fileIndex = index;

            if (isImage) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewItem.innerHTML = `
                        <div class="media-thumbnail">
                            <img src="${e.target.result}" alt="${file.name}">
                            <div class="media-overlay">
                                <button type="button" class="media-action-btn" title="Remove" data-action="remove" data-index="${index}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="media-info">
                            <div class="media-name">${file.name}</div>
                            <div class="media-meta">${(file.size / 1024).toFixed(1)} KB</div>
                        </div>
                    `;
                    filePreviewGrid.appendChild(previewItem);
                    attachRemoveHandler(previewItem);
                };
                reader.readAsDataURL(file);
            }
            else {
                const ext = file.name.split('.').pop().toUpperCase();
                previewItem.innerHTML = `
                    <div class="media-thumbnail media-document">
                        ${getFileIcon(getFileTypeFromMime(file.type), file.name)}
                        <div class="media-overlay">
                            <button type="button" class="media-action-btn" title="Remove" data-action="remove" data-index="${index}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="media-info">
                        <div class="media-name">${file.name}</div>
                        <div class="media-meta">${(file.size / 1024).toFixed(1)} KB</div>
                    </div>
                `;
                filePreviewGrid.appendChild(previewItem);
                attachRemoveHandler(previewItem);
            }
        }

        // Attach remove handler
        function attachRemoveHandler(previewItem) {
            const removeBtn = previewItem.querySelector('[data-action="remove"]');
            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const index = parseInt(removeBtn.dataset.index);
                selectedFiles.splice(index, 1);
                handleFilesSelected(selectedFiles);
            });
        }

        // Get file type from MIME
        function getFileTypeFromMime(mimeType) {
            if (mimeType.startsWith('image/')) return 'image';
            if (mimeType.startsWith('video/')) return 'video';
            if (mimeType.startsWith('audio/')) return 'audio';
            if (mimeType.includes('pdf') || mimeType.includes('document') || mimeType.includes('word')) return 'document';
            return 'other';
        }

        // Cancel button
        cancelUploadBtn.addEventListener('click', () => {
            selectedFiles = [];
            fileInput.value = '';
            filePreviewGrid.innerHTML = '';
            filePreviewGrid.style.display = 'none';
            uploadFilesBtn.style.display = 'none';
            cancelUploadBtn.style.display = 'none';
        });

        // Form submit - upload files
        uploadForm.addEventListener('submit', (e) => {
            e.preventDefault();

            if (selectedFiles.length === 0) {
                alert('Please select at least one file');
                return;
            }

            // Hide preview grid and upload button
            filePreviewGrid.style.display = 'none';
            uploadFilesBtn.disabled = true;
            cancelUploadBtn.disabled = true;

            // Clear progress container
            progressContainer.innerHTML = '';

            // Upload all files
            uploadMultipleFiles(selectedFiles, progressContainer, (uploadedMedia) => {
                // Success - close modal and reset
                document.getElementById('uploadModal').classList.remove('active');

                // Reset form
                selectedFiles = [];
                fileInput.value = '';
                filePreviewGrid.innerHTML = '';
                uploadFilesBtn.disabled = false;
                uploadFilesBtn.style.display = 'none';
                cancelUploadBtn.disabled = false;
                cancelUploadBtn.style.display = 'none';
                progressContainer.innerHTML = '';

                // Add uploaded items to grid
                uploadedMedia.forEach(media => addMediaItemToGrid(media));

                // Show success message
                const count = uploadedMedia.length;
                showSuccessMessage(count === 1 ? 'File uploaded successfully!' : `${count} files uploaded successfully!`);
            }, (error) => {
                // Error callback
                uploadFilesBtn.disabled = false;
                cancelUploadBtn.disabled = false;
            });
        });
    }

    /**
     * Upload multiple files with progress tracking
     */
    function uploadMultipleFiles(files, progressContainer, onComplete, onError) {
        const uploadedMedia = [];
        let completedCount = 0;

        files.forEach((file, index) => {
            uploadSingleFile(file, index, progressContainer, function(media) {
                // Single file success
                uploadedMedia.push(media);
                completedCount++;

                if (completedCount === files.length) {
                    onComplete(uploadedMedia);
                }
            }, function(error) {
                // Single file error
                completedCount++;

                if (completedCount === files.length) {
                    // All files processed (some may have failed)
                    if (uploadedMedia.length > 0) {
                        onComplete(uploadedMedia);
                    } else {
                        onError(error);
                    }
                }
            });
        });
    }

    /**
     * Upload single file with progress bar
     */
    function uploadSingleFile(file, index, progressContainer, onSuccess, onError) {
        // Create progress item
        const progressItem = document.createElement('div');
        progressItem.style.marginBottom = '0.75rem';
        progressItem.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.25rem;">
                <span style="font-size: 13px; color: var(--text-secondary);">${file.name}</span>
                <span style="font-size: 13px; color: var(--text-secondary);" class="progress-percent">0%</span>
            </div>
            <div style="width: 100%; height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden;">
                <div class="progress-bar" style="height: 100%; width: 0%; background: #3b82f6; transition: width 0.3s;"></div>
            </div>
        `;
        progressContainer.appendChild(progressItem);

        const progressBar = progressItem.querySelector('.progress-bar');
        const progressPercent = progressItem.querySelector('.progress-percent');

        // Create FormData
        const formData = new FormData();
        formData.append('file', file);

        // Create XMLHttpRequest for progress tracking
        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressPercent.textContent = percentComplete + '%';
            }
        });

        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success && data.media) {
                        // Success
                        progressBar.style.background = '#10b981';
                        progressPercent.textContent = '✓';
                        onSuccess(data.media);
                    }
                    else {
                        // Server error
                        progressBar.style.background = '#ef4444';
                        progressPercent.textContent = '✗';
                        progressPercent.title = data.message || 'Upload failed';
                        console.error('Upload failed:', data);
                        onError(data.message || 'Upload failed');
                    }
                }
                catch (e) {
                    // JSON parse error
                    progressBar.style.background = '#ef4444';
                    progressPercent.textContent = '✗';
                    console.error('Response parse error:', xhr.responseText);
                    onError('Invalid server response');
                }
            }
            else {
                // HTTP error
                progressBar.style.background = '#ef4444';
                progressPercent.textContent = '✗';
                console.error('HTTP error:', xhr.status, xhr.responseText);
                onError('Upload failed: ' + xhr.status);
            }
        });

        xhr.addEventListener('error', () => {
            progressBar.style.background = '#ef4444';
            progressPercent.textContent = '✗';
            onError('Network error');
        });

        xhr.open('POST', BASE + 'admin/media/upload');
        xhr.setRequestHeader('X-CSRF-TOKEN', getCsrfToken());
        xhr.send(formData);
    }

    /**
     * Add new media item to the grid (media library page)
     */
    function addMediaItemToGrid(media) {
        const mediaGrid = document.querySelector('.media-grid');
        if (!mediaGrid) return;

        // Remove empty state if it exists
        const emptyState = mediaGrid.querySelector('div[style*="grid-column: 1 / -1"]');
        if (emptyState) emptyState.remove();
        const isImage = media.file_type === 'image';

        const itemHTML = `
            <div class="media-item">
                <input type="checkbox" class="media-checkbox checkbox row-check">
                <div class="media-thumbnail${!isImage ? ' media-' + media.file_type : ''}">
                    ${isImage ?
                        `<img src="${BASE}/${media.file_path}" alt="${media.title}">` :
                        getFileIcon(media.file_type, media.filename)
                    }
                    <div class="media-overlay">
                        <a href="${BASE}/admin/media/edit/${media.id}" class="media-action-btn view-media-btn" title="Edit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </a>
                        <a href="${BASE}/${media.file_path}" class="media-action-btn" title="Download" download>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                        </a>
                        <a href="${BASE}/admin/media/delete/${media.id}" class="media-action-btn" title="Delete" onclick="return confirm('Delete this media file?')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="media-info">
                    <div class="media-name">${media.filename}</div>
                    <div class="media-meta">
                        ${media.width && media.height ? `${media.width}×${media.height} • ` : ''}
                        ${Math.round(media.file_size / 1024 * 10) / 10} KB
                    </div>
                </div>
            </div>
        `;

        // Prepend to grid
        mediaGrid.insertAdjacentHTML('afterbegin', itemHTML);
    }

    /**
     * Get file icon based on type
     */
    function getFileIcon(fileType, filename) {
        const ext = filename.split('.').pop().toUpperCase();
        let iconSvg = '';

        if (fileType === 'video') {
            iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="23 7 16 12 23 17 23 7"></polygon>
                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
            </svg>`;
        } else if (fileType === 'document') {
            iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
            </svg>`;
        } else if (fileType === 'audio') {
            iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18V5l12-2v13"></path>
                <circle cx="6" cy="18" r="3"></circle>
                <circle cx="18" cy="16" r="3"></circle>
            </svg>`;
        } else {
            iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                <polyline points="13 2 13 9 20 9"></polyline>
            </svg>`;
        }

        return `${iconSvg}<span class="file-ext">${ext}</span>`;
    }

    /**
     * Show success message
     */
    function showSuccessMessage(message) {
        const contentHeader = document.querySelector('.content-header');
        if (!contentHeader) return;

        const alert = document.createElement('div');
        alert.className = 'alert alert-success';
        alert.style.marginBottom = '1.5rem';
        alert.textContent = message;

        contentHeader.insertAdjacentElement('afterend', alert);

        // Remove after 3 seconds
        setTimeout(() => alert.remove(), 3000);
    }

    // Export utilities for media-picker.js to use
    window.MediaUploader = {
        uploadSingleFile: uploadSingleFile,
        getCsrfToken: getCsrfToken,
        getFileIcon: getFileIcon
    };

})();
