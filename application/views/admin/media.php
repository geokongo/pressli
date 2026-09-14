@extends('admin/layout')

@section('content')

        <!-- Media Library Content -->
        <main class="content">
            <div class="content-header">
                <h1 class="page-title">Media Library</h1>
                <button class="btn btn-primary" id="uploadBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    Upload Files
                </button>
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

            <!-- Filters & Actions -->
            <div class="card">
                <div class="table-toolbar">
                    <div class="table-tabs">
                        <a href="{{ Url::link('admin/media') }}" class="tab-btn {{ !Input::get('type') ? 'active' : '' }}">
                            All <span class="tab-count">{{ $typeCounts['all'] }}</span>
                        </a>
                        <a href="{{ Url::link('admin/media?type=image') }}" class="tab-btn {{ Input::get('type') === 'image' ? 'active' : '' }}">
                            Images <span class="tab-count">{{ $typeCounts['image'] }}</span>
                        </a>
                        <a href="{{ Url::link('admin/media?type=document') }}" class="tab-btn {{ Input::get('type') === 'document' ? 'active' : '' }}">
                            Documents <span class="tab-count">{{ $typeCounts['document'] }}</span>
                        </a>
                        <a href="{{ Url::link('admin/media?type=video') }}" class="tab-btn {{ Input::get('type') === 'video' ? 'active' : '' }}">
                            Videos <span class="tab-count">{{ $typeCounts['video'] }}</span>
                        </a>
                        <a href="{{ Url::link('admin/media?type=audio') }}" class="tab-btn {{ Input::get('type') === 'audio' ? 'active' : '' }}">
                            Audio <span class="tab-count">{{ $typeCounts['audio'] }}</span>
                        </a>
                        <a href="{{ Url::link('admin/media/trash?type=trash') }}" class="tab-btn {{ Input::get('type') === 'trash' ? 'active' : '' }}">
                            Empty Trash <span class="tab-count">{{ $typeCounts['trash'] }}</span>
                        </a>
                    </div>

                    <div class="table-actions">
                        <div class="bulk-actions">
                            <input type="checkbox" class="checkbox" id="selectAllMedia">
                            <label for="selectAllMedia" style="margin-right: 8px; font-size: 13px; color: var(--text-secondary);">Select All</label>
                            <select class="select-input" disabled id="bulkActionMedia">
                                <option value="">Bulk Actions</option>
                                <option value="delete">Delete</option>
                            </select>
                            <button class="btn btn-secondary" disabled id="applyBulkMedia">Apply</button>
                        </div>

                        <div class="search-box">
                            <input type="search" placeholder="Search media..." class="search-input">
                            <svg class="search-box-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Media Grid -->
                <div class="media-grid">
                    @loopelse($media as $item)
                    <div class="media-item" data-id="{{ $item['id'] }}">
                        <input type="checkbox" class="media-checkbox checkbox row-check">
                        <div class="media-thumbnail{{ $item['file_type'] !== 'image' ? ' media-' . $item['file_type'] : '' }}">
                            @if($item['file_type'] === 'image')
                                <img src="{{ Url::base() . $item['file_path'] }}" alt="{{ $item['alt_text'] or $item['title'] }}">
                            @else
                                @if($item['file_type'] === 'video')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                                </svg>
                                @elseif($item['file_type'] === 'document')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                                @elseif($item['file_type'] === 'audio')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 18V5l12-2v13"></path>
                                    <circle cx="6" cy="18" r="3"></circle>
                                    <circle cx="18" cy="16" r="3"></circle>
                                </svg>
                                @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                    <polyline points="13 2 13 9 20 9"></polyline>
                                </svg>
                                @endif
                                <span class="file-ext">{{ strtoupper(pathinfo($item['filename'], PATHINFO_EXTENSION)) }}</span>
                            @endif
                            <div class="media-overlay">
                                <a href="{{ Url::link('admin/media/edit/' . $item['id']) }}" class="media-action-btn view-media-btn" title="Edit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </a>
                                <a href="{{ Url::base() . $item['file_path'] }}" class="media-action-btn" title="Download" download>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="7 10 12 15 17 10"></polyline>
                                        <line x1="12" y1="15" x2="12" y2="3"></line>
                                    </svg>
                                </a>
                                <a href="{{ Url::link('admin/media/delete/' . $item['id']) }}" class="media-action-btn" title="Delete" onclick="return confirm('Delete this media file?')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                        <div class="media-info">
                            <div class="media-name">{{ $item['filename'] }}</div>
                            <div class="media-meta">
                                @if($item['width'] && $item['height'])
                                    {{ $item['width'] }}×{{ $item['height'] }} •
                                @endif
                                {{ round($item['file_size'] / 1024, 1) }} KB
                            </div>
                        </div>
                    </div>
                    @empty
                    <div style="text-align: center; padding: 3rem; grid-column: 1 / -1;">
                        <p style="color: #6b7280;">No media files yet. Upload your first file to get started.</p>
                    </div>
                    @endloop
                </div>

                <!-- Pagination -->
                <div class="table-footer">
                    <div class="table-info">
                        Showing {{ count($media) }} {{ count($media) === 1 ? 'item' : 'items' }}
                    </div>
                    <div class="pagination">
                        <button class="page-btn" disabled>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <button class="page-btn">4</button>
                        <button class="page-btn">5</button>
                        <button class="page-btn">6</button>
                        <button class="page-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Upload Modal -->
            <div class="modal" id="uploadModal">
                <div class="modal-overlay"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Upload Files</h2>
                        <button class="modal-close" id="closeUploadModal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{ Url::link('admin/media/upload') }}" enctype="multipart/form-data" id="uploadForm">
                            {{{ Csrf::field() }}}

                            <!-- Dropzone -->
                            <div class="upload-dropzone" id="uploadDropzone" style="border: 2px dashed #d1d5db; border-radius: 8px; padding: 3rem; text-align: center; background: #f9fafb; cursor: pointer; margin-bottom: 1.5rem;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 48px; height: 48px; margin: 0 auto 1rem; color: #9ca3af;">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                <h3 style="margin-bottom: 0.5rem;">Drop files here or click to browse</h3>
                                <p style="color: #6b7280; margin-bottom: 1rem;">JPG, PNG, GIF, WEBP, PDF, DOC, DOCX, MP4, MP3, ZIP up to 10MB</p>
                                <input type="file" name="file" id="fileInput" style="display: none;" multiple>
                                <button type="button" class="btn btn-primary" id="chooseFilesBtn">Choose Files</button>
                            </div>

                            <!-- File Preview Grid -->
                            <div id="filePreviewGrid" class="media-grid" style="display: none; margin-bottom: 1.5rem; max-height: 400px; overflow-y: auto;"></div>

                            <!-- Progress Container -->
                            <div id="uploadProgressContainer"></div>

                            <!-- Upload Button -->
                            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                                <button type="button" class="btn btn-secondary" id="cancelUploadBtn" style="display: none;">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="uploadFilesBtn" style="display: none;">Upload Files</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- View Media Modal -->
            <div class="modal" id="viewModal">
                <div class="modal-overlay"></div>
                <div class="modal-content modal-large">
                    <div class="modal-header">
                        <h2 class="modal-title">Media Details</h2>
                        <button class="modal-close" id="closeViewModal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="media-detail-layout">
                            <div class="media-preview">
                                <img src="https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800&h=600&fit=crop" alt="Media Preview" id="mediaPreviewImg">
                            </div>
                            <div class="media-details">
                                <div class="detail-section">
                                    <label class="detail-label">Filename</label>
                                    <div class="detail-value" id="mediaFilename">hero-image.jpg</div>
                                </div>
                                <div class="detail-section">
                                    <label class="detail-label">File Type</label>
                                    <div class="detail-value" id="mediaType">Image (JPEG)</div>
                                </div>
                                <div class="detail-section">
                                    <label class="detail-label">Dimensions</label>
                                    <div class="detail-value" id="mediaDimensions">1920 × 1080 pixels</div>
                                </div>
                                <div class="detail-section">
                                    <label class="detail-label">File Size</label>
                                    <div class="detail-value" id="mediaSize">245 KB</div>
                                </div>
                                <div class="detail-section">
                                    <label class="detail-label">Uploaded</label>
                                    <div class="detail-value" id="mediaUploaded">Jan 14, 2026 at 2:30 PM</div>
                                </div>
                                <div class="detail-section">
                                    <label class="detail-label">URL</label>
                                    <div class="detail-value detail-url">
                                        <input type="text" class="form-input" value="https://yoursite.com/uploads/hero-image.jpg" readonly id="mediaUrl">
                                        <button class="btn-icon" title="Copy URL">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="modal-actions">
                                    <button class="btn btn-secondary" id="viewLiveBtn">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                            <polyline points="15 3 21 3 21 9"></polyline>
                                            <line x1="10" y1="14" x2="21" y2="3"></line>
                                        </svg>
                                        View Live
                                    </button>
                                    <button class="btn btn-secondary">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="7 10 12 15 17 10"></polyline>
                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                        </svg>
                                        Download
                                    </button>
                                    <button class="btn btn-secondary danger-btn">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
@endsection

<!-- JS Scripts -->
@section('scripts')
    @parent
    <script src="{{Url::assets('admin/js/media.js')}}"></script>
@endsection
