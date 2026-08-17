@extends('admin/layout')

@section('styles')
    @parent
    <link rel="stylesheet" href="{{ Url::assets('admin/css/quill.snow.css') }}">
    <link rel="stylesheet" href="{{ Url::assets('admin/css/quill-custom.css') }}">
@endsection

@section('content')

        <!-- Add New Post Content -->
        <main class="content">
            <div class="content-header">
                <div class="header-breadcrumb">
                    <a href="{{ Url::link('admin/posts') }}" class="breadcrumb-link">Posts</a>
                    <svg class="breadcrumb-separator" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                    <span class="breadcrumb-current">Add New Post</span>
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

            <form class="post-create" method="POST" action="{{ Url::link('admin/posts/new') }}">
                {{{ Csrf::field() }}}
                <input type="hidden" name="type" value="post">

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
                        <!-- <div id="editor-container" style="min-height: 400px;"></div> -->
                        <div class="text-editor-div"></div>
                        <textarea name="content" id="content-input" style="display: none;"></textarea>

                    </div>

                    <!-- Excerpt -->
                    <div class="form-section">
                        <label class="form-label">Excerpt</label>
                        <textarea name="excerpt" class="form-textarea" rows="3" placeholder="Write a short excerpt for this post..."></textarea>
                        <p class="form-help">The excerpt is used in search results and social media previews.</p>
                    </div>

                    <!-- SEO Settings -->
                    <div class="form-section">
                        <h3 class="section-title">SEO Settings</h3>

                        <div class="form-group">
                            <label class="form-label" for="meta-title">Meta Title</label>
                            <input type="text" id="meta-title" name="meta-title" class="text-input" placeholder="Leave blank to use post title" maxlength="60">
                            <p class="form-help">Recommended: 50-60 characters</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="meta-description">Meta Description</label>
                            <textarea id="meta-description" name="meta-description" class="form-textarea" rows="2" placeholder="Brief description for search engines" maxlength="160"></textarea>
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
                                    <select name="status" id="status" class="option-value" style="border: none; background: transparent; padding: 0; font-size: inherit;">
                                        <option value="draft" selected>Draft</option>
                                        <option value="published">Published</option>
                                        <option value="scheduled">Scheduled</option>
                                    </select>
                                </div>
                                <div class="publish-option">
                                    <span class="option-label">Visibility:</span>
                                    <select name="visibility" class="option-value" style="border: none; background: transparent; padding: 0; font-size: inherit;">
                                        <option value="public" selected>Public</option>
                                        <option value="private">Private</option>
                                        <option value="password">Protected</option>
                                    </select>
                                </div>
                                <div class="publish-option" id="publish-immediately">
                                    <span class="option-label">Publish:</span>
                                    <span class="option-value">Immediately</span>
                                </div>
                                <div class="publish-option" id="scheduled-date-group" style="display: none;">
                                    <span class="option-label">Publish:</span>
                                    <input type="datetime-local" name="published-at" id="published-at" class="option-value" style="border: none; background: transparent; padding: 0; font-size: inherit;">
                                </div>
                                <div class="publish-option">
                                    <span class="option-label">Comments:</span>
                                    <label class="option-value" style="margin: 0;">
                                        <input type="checkbox" name="allow-comments" checked style="margin-right: 4px;">
                                        <span>Allow</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="panel-footer">
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

                    <!-- Categories -->
                    <div class="sidebar-panel">
                        <div class="panel-header">
                            <h3 class="panel-title">Categories</h3>
                        </div>
                        <div class="panel-body">
                            <div class="checkbox-list">
                                @loopelse($categories as $category)
                                    <label class="checkbox-label">
                                        <input type="checkbox" class="checkbox" name="categories[]" value="{{ $category['id'] }}">
                                        <span>{{ $category['name'] }}</span>
                                    </label>
                                @empty
                                    <p class="text-muted">No categories available</p>
                                @endloop
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

                    <!-- Tags -->
                    <div class="sidebar-panel">
                        <div class="panel-header">
                            <h3 class="panel-title">Tags</h3>
                        </div>
                        <div class="panel-body">
                            <input type="text" name="tags" class="form-input" placeholder="Add tags separated by commas">
                            <p class="form-help">Separate tags with commas</p>
                        </div>
                    </div>
                </div>
            </div>
            </form>
        </main>

        <script src="{{ Url::assets('admin/js/quill.min.js') }}"></script>
        <script src="{{ Url::assets('admin/js/media.js') }}"></script>
        <script src="{{ Url::assets('admin/js/media-picker.js') }}"></script>

        <script>
            // Initialize text editor functionality once document is ready
            document.addEventListener('DOMContentLoaded', () => {
                new TextEditor({
                    uploadUrl: "http://localhost/pressli/admin/media/upload",
                    CSRF: CSRF
                });
            });
        </script>
        <script>
        // Show/hide scheduled date field based on status
        document.getElementById('status').addEventListener('change', function() {
            const scheduledGroup = document.getElementById('scheduled-date-group');
            const immediatelyGroup = document.getElementById('publish-immediately');
            if (this.value === 'scheduled') {
                scheduledGroup.style.display = 'flex';
                immediatelyGroup.style.display = 'none';
            }
            else {
                scheduledGroup.style.display = 'none';
                immediatelyGroup.style.display = 'flex';
            }
        });

        // Initialize Quill editor
        // var quill = new Quill('#editor-container', {
        //     theme: 'snow',
        //     placeholder: 'Start writing your post...',
        //     modules: {
        //         toolbar: {
        //             container: [
        //                 [{ 'header': [1, 2, 3, false] }],
        //                 ['bold', 'italic', 'underline', 'strike'],
        //                 ['link', 'image'],
        //                 [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        //                 [{ 'align': [] }],
        //                 ['clean']
        //             ],
        //             handlers: {
        //                 image: imageHandler
        //             }
        //         }
        //     }
        // });

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
        const featuredImageInput = document.getElementById('featured-image-id');
        const setFeaturedImageBtn = featuredImageContainer.querySelector('.btn');

        setFeaturedImageBtn.addEventListener('click', () => {
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

                // Attach change handler
                document.getElementById('changeFeaturedImage').addEventListener('click', () => {
                    setFeaturedImageBtn.click();
                });

                // Attach remove handler
                document.getElementById('removeFeaturedImage').addEventListener('click', () => {
                    featuredImageContainer.innerHTML = `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        <button type="button" class="btn btn-secondary btn-block">Set Featured Image</button>
                        <input type="hidden" name="featured-image-id" id="featured-image-id" value="">
                    `;
                    // Reattach click handler to new button
                    featuredImageContainer.querySelector('.btn').addEventListener('click', () => setFeaturedImageBtn.click());
                });
            });
            picker.open();
        });

        // Sync Quill content to hidden textarea on form submit
        const form = document.querySelector('form.post-create');
        form.addEventListener('submit', function(event) {
            // const html = quill.root.innerHTML;
            const html = document.querySelector('.text-editor-div').innerHTML;
            document.getElementById('content-input').value = html;
        });

        var setDraft = document.getElementById('submit-draft');
        setDraft.addEventListener('click', function(){
            document.getElementById('status').value = 'draft';
        });

        var setPublish = document.getElementById('submit-publish');
        setPublish.addEventListener('click', function(){
            document.getElementById('status').value = 'published';
        });
        </script>
@endsection