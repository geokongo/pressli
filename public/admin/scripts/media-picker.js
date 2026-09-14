/**
 * Media Picker for Rich Text Editors - Pressli CMS
 *
 * Media library modal for selecting/uploading images from within the post editor.
 * Integrates with existing MediaController for unified media management.
 */

class MediaPicker {
    constructor(callback) {
        this.callback = callback;
        this.modal = null;
        this.init();
    }

    init() {
        // Create modal if it doesn't exist
        if (!document.getElementById('mediaPickerModal')) {
            this.createModal();
        } else {
            this.modal = document.getElementById('mediaPickerModal');
        }

        this.attachEvents();
        this.loadMedia();
    }

    createModal() {
        const modalHTML = `
            <div class="modal" id="mediaPickerModal">
                <div class="modal-overlay"></div>
                <div class="modal-content modal-large">
                    <div class="modal-header">
                        <h2 class="modal-title">Select or Upload Media</h2>
                        <button class="modal-close" id="closeMediaPicker">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <!-- Upload Section -->
                        <div style="margin-bottom: 1.5rem;">
                            <button class="btn btn-secondary" id="showUploadSection">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                Upload New Image
                            </button>
                        </div>

                        <!-- Upload Form (Hidden by default) -->
                        <div id="uploadSection" style="display: none; margin-bottom: 2rem;">
                            <form id="mediaPickerUploadForm" enctype="multipart/form-data">
                                <div class="upload-area" style="border: 2px dashed #d1d5db; border-radius: 8px; padding: 2rem; text-align: center; background: #f9fafb;">
                                    <input type="file" id="mediaPickerFileInput" accept="image/*,video/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" style="display: none;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 48px; height: 48px; margin: 0 auto 1rem; color: #9ca3af;">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    <h3 style="margin-bottom: 0.5rem;">Click to select files</h3>
                                    <p style="color: #6b7280;">Images, videos, documents up to 10MB</p>
                                    <button type="button" class="btn btn-primary" id="selectFileBtn" style="margin-top: 1rem;">Choose Files</button>
                                    <div id="uploadProgressContainer" style="margin-top: 1rem;"></div>
                                </div>
                            </form>
                        </div>

                        <!-- Media Grid -->
                        <div class="media-grid" id="mediaPickerGrid">
                            <div style="text-align: center; padding: 3rem; grid-column: 1 / -1;">
                                <p style="color: #6b7280;">Loading media...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('mediaPickerModal');
    }

    attachEvents() {
        // Close modal
        document.getElementById('closeMediaPicker').addEventListener('click', () => {
            this.close();
        });

        // Close on overlay click
        this.modal.querySelector('.modal-overlay').addEventListener('click', () => {
            this.close();
        });

        // Show/hide upload section
        document.getElementById('showUploadSection').addEventListener('click', () => {
            const uploadSection = document.getElementById('uploadSection');
            uploadSection.style.display = uploadSection.style.display === 'none' ? 'block' : 'none';
        });

        // File select button
        document.getElementById('selectFileBtn').addEventListener('click', () => {
            document.getElementById('mediaPickerFileInput').click();
        });

        // File input change - support multiple files
        const fileInput = document.getElementById('mediaPickerFileInput');
        fileInput.setAttribute('multiple', 'multiple');

        fileInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            if (files.length > 0) {
                this.uploadFiles(files);
            }
        });
    }

    async loadMedia() {
        try {
            const response = await fetch(BASE + 'admin/media?type=image');
            const html = await response.text();

            // Parse HTML to extract media items
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const mediaItems = doc.querySelectorAll('.media-item');

            const grid = document.getElementById('mediaPickerGrid');
            grid.innerHTML = '';

            if (mediaItems.length === 0) {
                grid.innerHTML = '<div style="text-align: center; padding: 3rem; grid-column: 1 / -1;"><p style="color: #6b7280;">No images yet. Upload your first image to get started.</p></div>';
                return;
            }

            mediaItems.forEach(item => {
                const img = item.querySelector('img');
                if (!img) return;

                // Use img.src to get full absolute URL
                const imgSrc = img.src;

                // Extract media ID from parent media-item data attribute
                const mediaId = item.dataset.id;

                const mediaItem = document.createElement('div');
                mediaItem.className = 'media-item picker-item';
                mediaItem.innerHTML = `
                    <div class="media-thumbnail">
                        <img src="${imgSrc}" alt="${img.alt}">
                        <div class="media-overlay">
                            <button type="button" class="btn btn-primary btn-sm select-media-btn" data-id="${mediaId}" data-url="${imgSrc}" data-alt="${img.alt || ''}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                Select
                            </button>
                        </div>
                    </div>
                    <div class="media-info">
                        <div class="media-name">${img.alt || 'Image'}</div>
                    </div>
                `;

                // Add click handler to select button
                const selectBtn = mediaItem.querySelector('.select-media-btn');
                selectBtn.addEventListener('click', () => {
                    this.selectMedia({
                        id: selectBtn.dataset.id,
                        url: selectBtn.dataset.url,
                        alt: selectBtn.dataset.alt
                    });
                });

                grid.appendChild(mediaItem);
            });
        } catch (error) {
            console.error('Failed to load media:', error);
            document.getElementById('mediaPickerGrid').innerHTML = '<div style="text-align: center; padding: 3rem; grid-column: 1 / -1;"><p style="color: #ef4444;">Failed to load media. Please try again.</p></div>';
        }
    }

    uploadFiles(files) {
        const progressContainer = document.getElementById('uploadProgressContainer');
        progressContainer.innerHTML = '';

        const uploadedMedia = [];
        let completedCount = 0;

        files.forEach((file, index) => {
            window.MediaUploader.uploadSingleFile(file, index, progressContainer, (media) => {
                // Single file success
                uploadedMedia.push(media);
                completedCount++;

                if (completedCount === files.length) {
                    // All uploads complete
                    this.onUploadComplete(uploadedMedia);
                }
            }, (error) => {
                // Single file error
                completedCount++;

                if (completedCount === files.length) {
                    // All files processed
                    if (uploadedMedia.length > 0) {
                        this.onUploadComplete(uploadedMedia);
                    } else {
                        alert('All uploads failed. Please try again.');
                        progressContainer.innerHTML = '';
                    }
                }
            });
        });
    }

    onUploadComplete(uploadedMedia) {
        // Add uploaded items to grid
        const grid = document.getElementById('mediaPickerGrid');

        // Remove empty state if exists
        const emptyState = grid.querySelector('div[style*="grid-column: 1 / -1"]');
        if (emptyState) emptyState.remove();

        uploadedMedia.forEach(media => {
            this.addMediaItemToGrid(media);
        });

        // Reset upload section
        document.getElementById('uploadProgressContainer').innerHTML = '';
        document.getElementById('mediaPickerFileInput').value = '';
        document.getElementById('uploadSection').style.display = 'none';

        // If single image upload, auto-select it (editor use case)
        if (uploadedMedia.length === 1 && uploadedMedia[0].file_type === 'image') {
            this.selectMedia({
                id: uploadedMedia[0].id,
                url: BASE + '/' + uploadedMedia[0].file_path,
                alt: uploadedMedia[0].title || uploadedMedia[0].filename
            });
        }
    }

    addMediaItemToGrid(media) {
        const grid = document.getElementById('mediaPickerGrid');

        // Only show images in picker grid (but we allow uploading any file type)
        if (media.file_type !== 'image') return;

        const mediaItem = document.createElement('div');
        mediaItem.className = 'media-item picker-item';
        mediaItem.innerHTML = `
            <div class="media-thumbnail">
                <img src="${BASE}/${media.file_path}" alt="${media.title}">
                <div class="media-overlay">
                    <button type="button" class="btn btn-primary btn-sm select-media-btn" data-id="${media.id}" data-url="${BASE}/${media.file_path}" data-alt="${media.title || ''}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Select
                    </button>
                </div>
            </div>
            <div class="media-info">
                <div class="media-name">${media.filename}</div>
            </div>
        `;

        const selectBtn = mediaItem.querySelector('.select-media-btn');
        selectBtn.addEventListener('click', () => {
            this.selectMedia({
                id: selectBtn.dataset.id,
                url: selectBtn.dataset.url,
                alt: selectBtn.dataset.alt
            });
        });

        grid.insertAdjacentElement('afterbegin', mediaItem);
    }

    selectMedia(url) {
        if (this.callback) {
            this.callback(url);
        }
        this.close();
    }

    open() {
        this.modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    close() {
        this.modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Export for use in editor
window.MediaPicker = MediaPicker;
