@extends('admin/layout')

@section('content')

        <!-- Add New Page Content -->
        <main class="content">
            <div class="content-header">
                <div class="header-breadcrumb">
                    <a href="{{ Url::link('admin/pages') }}" class="breadcrumb-link">Pages</a>
                    <svg class="breadcrumb-separator" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                    <span class="breadcrumb-current">Add New Page</span>
                </div>
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

            <form method="POST" action="{{ Url::link('admin/pages/new') }}">
                {{{ Csrf::field() }}}
                <input type="hidden" name="type" value="page">

            <div class="editor-layout">
                <!-- Main Editor Area -->
                <div class="editor-main">
                    <!-- Title Input -->
                    <div class="editor-title-wrapper">
                        <input type="text" name="title" class="editor-title" placeholder="Add title" required autofocus>
                    </div>

                    <!-- Permalink -->
                    <div class="permalink-wrapper">
                        <span class="permalink-label">Permalink:</span>
                        <span class="permalink-url">{{ Url::base() }}</span>
                        <input type="text" name="slug" class="permalink-input" placeholder="auto-generated-from-title">
                        <p class="form-help" style="margin-top: 0.5rem;">Leave blank to auto-generate from title</p>
                    </div>

                    <!-- Content Editor -->
                    <div class="editor-content-wrapper">
                        <div id="editor-container" style="min-height: 400px;"></div>
                        <textarea name="content" id="content-input" style="display: none;"></textarea>
                    </div>

                    <!-- Excerpt -->
                    <div class="form-section">
                        <label class="form-label">Excerpt</label>
                        <textarea name="excerpt" class="form-textarea" rows="3" placeholder="Write a short excerpt for this page..."></textarea>
                        <p class="form-help">The excerpt is used in search results and social media previews.</p>
                    </div> 

                    <!-- SEO Settings -->
                    <div class="form-section">
                        <h3 class="section-title">SEO Settings</h3>

                        <div class="form-group">
                            <label class="form-label" for="meta_title">Meta Title</label>
                            <input type="text" id="meta_title" name="meta_title" class="text-input" placeholder="Leave blank to use page title" maxlength="60">
                            <p class="form-help">Recommended: 50-60 characters</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="meta_description">Meta Description</label>
                            <textarea id="meta_description" name="meta_description" class="form-textarea" rows="2" placeholder="Brief description for search engines" maxlength="160"></textarea>
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
                                    <path d="M22 2L11 13"></path>
                                    <path d="M22 2l-7 20-4-9-9-4 20-7z"></path>
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
                                        <option value="{{ $parentPage['id'] }}">
                                            {{ $parentPage['display_title'] or $parentPage['title'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="template">Template</label>
                                <select name="template" id="template" class="form-select">
                                    @foreach($pageTemplates as $slug => $config)
                                        <option value="{{ $slug }}" {{ $slug === 'default' ? 'selected' : '' }}>
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
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                <button type="button" class="btn btn-secondary btn-block">Set Featured Image</button>
                                <input type="hidden" name="featured-image-id" id="featured-image-id" value="">
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

        // Sync Quill content to hidden textarea on form submit
        const form = document.querySelector('form');
        form.addEventListener('submit', function() {
            const html = quill.root.innerHTML;
            document.getElementById('content-input').value = html;
        });

        var setDraft = document.getElementById('submit-draft');
        setDraft.addEventListener('click', function(){
            document.getElementById('page-status').value = 'draft';
        });

        var setPublish = document.getElementById('submit-publish');
        setPublish.addEventListener('click', function(){
            document.getElementById('page-status').value = 'published';
        });
        </script>
@endsection
