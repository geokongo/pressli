@extends('admin/layout')

@section('content')

        <!-- Edit Page Content -->
        <main class="content">
            <div class="content-header">
                <div class="header-breadcrumb">
                    <a href="{{ Url::link('admin/pages') }}" class="breadcrumb-link">Pages</a>
                    <svg class="breadcrumb-separator" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                    <span class="breadcrumb-current">Edit Page</span>
                </div>
                <a href="{{ Url::link('admin/pages/new') }}" class="breadcrumb-link">+ Add New</a>
            </div>

            @if(Session::hasFlash('success'))
                <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                    {{ Session::flash('success') }}
                </div>
            @endif

            @if(Session::hasFlash('error'))
                <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                    {{ Session::flash('error') }}
                </div>
            @endif

            <form method="POST" action="{{ Url::link('admin/pages/edit/' . $page['id']) }}">
                {{{ Csrf::field() }}}
                <input type="hidden" name="type" value="page">

            <div class="editor-layout">
                <!-- Main Editor Area -->
                <div class="editor-main">
                    <!-- Title Input -->
                    <div class="editor-title-wrapper">
                        <input type="text" name="title" class="editor-title" placeholder="Add title" value="{{ $page['title'] }}" required autofocus>
                    </div>

                    <!-- Permalink -->
                    <div class="permalink-wrapper">
                        <span class="permalink-label">Permalink:</span>
                        <span class="permalink-url">{{ Url::base() }}</span>
                        @if($page['status'] === 'published')
                            <a href="{{ Url::link($page['slug']) }}" target="_blank" class="permalink-link" style="color: #3b82f6; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                                <span class="permalink-display">{{ $page['slug'] }}</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                            <input type="text" name="slug" class="permalink-input" value="{{ $page['slug'] }}" style="display: none;">
                            <button type="button" class="permalink-toggle-btn" style="margin-left: auto; font-size: 13px; color: #6b7280; text-decoration: underline; background: none; border: none; cursor: pointer; padding: 0;">Edit</button>
                        @else
                            <input type="text" name="slug" class="permalink-input" placeholder="auto-generated-from-title" value="{{ $page['slug'] }}">
                        @endif
                        @if($page['status'] !== 'published')
                            <p class="form-help" style="margin-top: 0.5rem;">Leave blank to auto-generate from title</p>
                        @endif
                    </div>

                    <!-- Content Editor -->
                    <div class="editor-content-wrapper">
                        <div id="editor-container" style="min-height: 400px;"></div>
                        <textarea name="content" id="content-input" style="display: none;">{{ $page['content'] or '' }}</textarea>
                    </div>

                    <!-- Excerpt -->
                    <div class="form-section">
                        <label class="form-label">Excerpt</label>
                        <textarea name="excerpt" class="form-textarea" rows="3" placeholder="Write a short excerpt for this page...">{{ $page['excerpt'] or '' }}</textarea>
                        <p class="form-help">The excerpt is used in search results and social media previews.</p>
                    </div>

                    <!-- SEO Settings -->
                    <div class="form-section">
                        <h3 class="section-title">SEO Settings</h3>

                        <div class="form-group">
                            <label class="form-label" for="meta_title">Meta Title</label>
                            <input type="text" id="meta_title" name="meta_title" class="text-input" placeholder="Leave blank to use page title" maxlength="60" value="{{ $page['meta_title'] or '' }}">
                            <p class="form-help">Recommended: 50-60 characters</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="meta_description">Meta Description</label>
                            <textarea id="meta_description" name="meta_description" class="form-textarea" rows="2" placeholder="Brief description for search engines" maxlength="160">{{ $page['meta_description'] or '' }}</textarea>
                            <p class="form-help">Recommended: 150-160 characters</p>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="editor-sidebar">
                    <!-- Publish Options -->
                    <div class="sidebar-panel">
                        <div class="panel-header">
                            <h3 class="panel-title">Publish</h3>
                        </div>
                        <div class="panel-body">
                            <div class="publish-options">
                                <div class="publish-option">
                                    <span class="option-label">Status:</span>
                                    <span name="status" class="option-value" style="border: none; background: transparent; padding: 0; font-size: inherit;">
                                        {{ $page['status'] }}                                        
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="panel-footer">
                            <input type="hidden" name="status" value="" id="page-status">
                            <button type="submit" class="btn btn-secondary btn-block" id="submit-draft">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Save Draft
                            </button>
                            <button type="submit" class="btn btn-primary btn-block" id="submit-publish">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                Publish
                            </button>
                        </div>
                    </div>

                    <!-- Page Attributes -->
                    <div class="sidebar-panel">
                        <div class="panel-header">
                            <h3 class="panel-title">Page Attributes</h3>
                        </div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label class="form-label" for="parent_id">Parent Page</label>
                                <select name="parent_id" id="parent_id" class="form-select">
                                    <option value="">None (Top Level)</option>
                                    @foreach($pages as $parentPage)
                                        <option value="{{ $parentPage['id'] }}" {{ $page['parent_id'] == $parentPage['id'] ? 'selected' : '' }}>
                                            {{ $parentPage['display_title'] or $parentPage['title'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="template">Template</label>
                                <select name="template" id="template" class="form-select">
                                    @foreach($pageTemplates as $slug => $config)
                                        <option value="{{ $slug }}" {{ $page['template'] === $slug ? 'selected' : '' }}>
                                            {{ $config['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Featured Image -->
                    <div class="sidebar-panel">
                        <div class="panel-header">
                            <h3 class="panel-title">Featured Image</h3>
                        </div>
                        <div class="panel-body">
                            <div class="featured-image-placeholder">
                                @if($page['featured_image_id'])
                                    <div class="featured-image-preview">
                                        <img src="{{ Url::assets($page['featured_image']) }}" alt="{{ $page['featured_image_alt'] or $page['featured_image_title'] }}" style="width: 100%; height: auto; border-radius: 4px; margin-bottom: 0.75rem;">
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button type="button" class="btn btn-secondary btn-sm" id="changeFeaturedImage" style="flex: 1;">Change Image</button>
                                            <button type="button" class="btn btn-danger btn-sm" id="removeFeaturedImage" style="flex: 1;">Remove Image</button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="featured-image-id" id="featured-image-id" value="{{ $page['featured_image_id'] }}">
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                    <button type="button" class="btn btn-secondary btn-block">Set Featured Image</button>
                                    <input type="hidden" name="featured-image-id" id="featured-image-id" value="">
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </form>
        </main>

        <script src="{{ Url::assets('admin/scripts/media.js') }}"></script>
        <script src="{{ Url::assets('admin/scripts/media-picker.js') }}"></script>
        <script>
        // Initialize Quill editor
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            placeholder: 'Start writing your page...',
            modules: {
                toolbar: {
                    container: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        ['link', 'image'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'align': [] }],
                        ['clean']
                    ],
                    handlers: {
                        image: imageHandler
                    }
                }
            }
        });

        // Load existing content into Quill
        var existingContent = document.getElementById('content-input').value;
        if (existingContent) {
            quill.root.innerHTML = existingContent;
        }

        // Custom image handler - opens Pressli media picker
        function imageHandler() {
            const picker = new MediaPicker((media) => {
                const range = quill.getSelection(true);
                quill.insertEmbed(range.index, 'image', media.url, 'user');
                quill.setSelection(range.index + 1);
            });
            picker.open();
        }

        // Featured image handler
        const featuredImageContainer = document.querySelector('.featured-image-placeholder');

        function attachFeaturedImageHandlers() {
            const setBtn = featuredImageContainer.querySelector('.btn-secondary, .btn-block');
            const changeBtn = document.getElementById('changeFeaturedImage');
            const removeBtn = document.getElementById('removeFeaturedImage');

            function openMediaPicker() {
                const picker = new MediaPicker((media) => {
                    // Replace placeholder with preview
                    featuredImageContainer.innerHTML = `
                        <div class="featured-image-preview">
                            <img src="${media.url}" alt="${media.alt}" style="width: 100%; height: auto; border-radius: 4px; margin-bottom: 0.75rem;">
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="button" class="btn btn-secondary btn-sm" id="changeFeaturedImage" style="flex: 1;">Change Image</button>
                                <button type="button" class="btn btn-danger btn-sm" id="removeFeaturedImage" style="flex: 1;">Remove Image</button>
                            </div>
                        </div>
                        <input type="hidden" name="featured-image-id" id="featured-image-id" value="${media.id}">
                    `;

                    attachFeaturedImageHandlers();
                });
                picker.open();
            }

            if (setBtn) {
                setBtn.addEventListener('click', openMediaPicker);
            }

            if (changeBtn) {
                changeBtn.addEventListener('click', openMediaPicker);
            }

            if (removeBtn) {
                removeBtn.addEventListener('click', () => {
                    featuredImageContainer.innerHTML = `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        <button type="button" class="btn btn-secondary btn-block">Set Featured Image</button>
                        <input type="hidden" name="featured-image-id" id="featured-image-id" value="">
                    `;
                    attachFeaturedImageHandlers();
                });
            }
        }

        attachFeaturedImageHandlers();

        // Ajax form submission with button loading state
        const form = document.querySelector('form');
        const submitButtons = form.querySelectorAll('button[type="submit"]');

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Sync Quill content to hidden textarea
            const html = quill.root.innerHTML;
            document.getElementById('content-input').value = html;

            // Disable buttons and show loading state
            submitButtons.forEach(btn => {
                btn.disabled = true;
                btn.dataset.originalText = btn.innerHTML;
                btn.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 0.5rem;"><svg style="animation: spin 1s linear infinite; width: 16px; height: 16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"></circle><path d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Saving...</span>';
            });

            // Prepare form data
            const formData = new FormData(form);

            // Send Ajax request
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Re-enable buttons
                submitButtons.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = btn.dataset.originalText;
                });

                if (data.success) {
                    // Show success toast
                    showToast(data.message, 'success');
                } else {
                    // Show error toast
                    showToast(data.message || 'An error occurred', 'error');
                }
            })
            .catch(error => {
                // Re-enable buttons
                submitButtons.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = btn.dataset.originalText;
                });

                // Show error toast
                showToast('Failed to save page. Please try again.', 'error');
                console.error('Error:', error);
            });
        });

        // Toast notification function
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#10b981' : '#ef4444'};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 0.5rem;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                z-index: 10000;
                animation: slideIn 0.3s ease-out;
                max-width: 400px;
            `;
            toast.textContent = message;

            // Add slide-in animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);

            document.body.appendChild(toast);

            // Auto remove after 3 seconds
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        var setDraft = document.getElementById('submit-draft');
        setDraft.addEventListener('click', function(){
            document.getElementById('page-status').value = 'draft';
        });

        var setPublish = document.getElementById('submit-publish');
        setPublish.addEventListener('click', function(){
            document.getElementById('page-status').value = 'published';
        });

        // Permalink editing for published pages
        const permalinkToggleBtn = document.querySelector('.permalink-toggle-btn');
        const permalinkLink = document.querySelector('.permalink-link');
        const permalinkDisplay = document.querySelector('.permalink-display');
        const permalinkInput = document.querySelector('.permalink-wrapper input[name="slug"]');

        if (permalinkToggleBtn) {
            let isEditing = false;

            permalinkToggleBtn.addEventListener('click', function() {
                if (!isEditing) {
                    // Switch to edit mode
                    permalinkLink.style.display = 'none';
                    permalinkInput.style.display = 'inline';
                    permalinkInput.focus();
                    permalinkInput.select();
                    this.textContent = 'OK';
                    this.style.color = '#3b82f6';
                    isEditing = true;
                }
                else {
                    // Switch back to display mode
                    const newSlug = permalinkInput.value;
                    permalinkDisplay.textContent = newSlug;
                    permalinkLink.href = '{{ Url::base() }}' + newSlug;
                    permalinkLink.style.display = 'inline-flex';
                    permalinkInput.style.display = 'none';
                    this.textContent = 'Edit';
                    this.style.color = '#6b7280';
                    isEditing = false;
                }
            });
        }
        </script>
@endsection
