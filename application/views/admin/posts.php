@extends('admin/layout')

@section('content')

        <!-- Posts Content -->
        <main class="content">
            <div class="content-header">
                <h1 class="page-title">Posts</h1>
                <a href="{{ Url::link('admin/posts/new') }}" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    New Post
                </a>
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

            <!-- Filters & Actions Bar -->
            <div class="card">
                <div class="table-toolbar">
                    <div class="table-tabs">
                        <a href="{{ Url::link('admin/posts') }}" class="tab-btn {{ !Input::get('status') ? 'active' : '' }}">
                            All <span class="tab-count">{{ $statusCounts['all'] }}</span>
                        </a>
                        <a href="{{ Url::link('admin/posts?status=published') }}" class="tab-btn {{ Input::get('status') === 'published' ? 'active' : '' }}">
                            Published <span class="tab-count">{{ $statusCounts['published'] }}</span>
                        </a>
                        <a href="{{ Url::link('admin/posts?status=draft') }}" class="tab-btn {{ Input::get('status') === 'draft' ? 'active' : '' }}">
                            Draft <span class="tab-count">{{ $statusCounts['draft'] }}</span>
                        </a>
                        <a href="{{ Url::link('admin/posts?status=trash') }}" class="tab-btn {{ Input::get('status') === 'trash' ? 'active' : '' }}">
                            Trash <span class="tab-count">{{ $statusCounts['trash'] }}</span>
                        </a>
                    </div>

                    <div class="table-actions">
                        <div class="bulk-actions">
                            <select class="select-input" disabled id="bulkAction">
                                <option value="">Bulk Actions</option>
                                <option value="publish">Publish</option>
                                <option value="draft">Move to Draft</option>
                                <option value="trash">Move to Trash</option>
                                <option value="delete">Delete Permanently</option>
                            </select>
                            <button class="btn btn-secondary" disabled id="applyBulk">Apply</button>
                            @if($filter == 'trash')
                            <a href="{{ Url::link('admin/posts/trash') }}" class="btn btn-danger">Empty Trash</a>
                            @endif
                        </div>

                        <div class="search-box">
                            <input type="search" placeholder="Search posts..." class="search-input">
                            <svg class="search-box-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Posts Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="col-check">
                                    <input type="checkbox" id="selectAll" class="checkbox">
                                </th>
                                <th class="col-title">Title</th>
                                <th class="col-author">Author</th>
                                <th class="col-category">Category</th>
                                <th class="col-status">Status</th>
                                <th class="col-date">Date</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @loopelse($posts as $post)
                                <tr>
                                    <td class="col-check">
                                        <input type="checkbox" class="checkbox row-check">
                                    </td>
                                    <td class="col-title">
                                        <div class="title-cell">
                                            <a href="{{ Url::link('admin/posts/edit/' . $post['id']) }}" class="post-title">{{ $post['title'] }}</a>
                                            <div class="row-actions">
                                                <a href="{{ Url::link('admin/posts/edit/' . $post['id']) }}">Edit Post</a>
                                                <span class="separator">|</span>
                                                @if($post['status'] === 'published')
                                                    <a href="{{ Url::link($post['slug']) }}" target="_blank">View Post</a>
                                                @else
                                                    <a href="#" title="Preview (not yet implemented)">Preview</a>
                                                @endif
                                                <span class="separator">|</span>
                                                <a href="{{ Url::link('admin/posts/delete/' . $post['id']) }}" class="danger" onclick="return confirm('Move this post to trash?')">Trash</a>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="col-author">
                                        <div class="author-cell">
                                            <img src="https://ui-avatars.com/api/?name={{ urlencode($post['first_name'] . ' ' . $post['last_name']) }}&background=4f46e5&color=fff" alt="Author" class="author-avatar">
                                            <span>{{ $post['first_name'] }} {{ $post['last_name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="col-category">
                                        @if(empty($post['categories']))
                                            <span class="category-tag">Uncategorized</span>
                                        @elseif(count($post['categories']) === 1)
                                            <span class="category-tag">{{ $post['categories'][0] }}</span>
                                        @else
                                            <span class="category-tag">{{ $post['categories'][0] }} +{{ count($post['categories']) - 1 }} more</span>
                                        @endif
                                    </td>
                                    <td class="col-status">
                                        <span class="status-badge status-{{ $post['status'] }}">{{ ucfirst($post['status']) }}</span>
                                    </td>
                                    <td class="col-date">
                                        <div class="date-cell">
                                            @if($post['status'] === 'published' && $post['published_at'])
                                                {{ Date::format($post['published_at'], 'M j, Y') }} <span class="date-time">{{ Date::format($post['published_at'], 'g:i A') }}</span>
                                            @elseif($post['status'] === 'scheduled' && $post['published_at'])
                                                {{ Date::format($post['published_at'], 'M j, Y') }} <span class="date-time">{{ Date::format($post['published_at'], 'g:i A') }}</span>
                                            @elseif($post['updated_at'])
                                                {{ Date::format($post['updated_at'], 'M j, Y') }} <span class="date-time">Modified</span>
                                            @elseif($post['created_at'])
                                                {{ Date::format($post['created_at'], 'M j, Y') }} <span class="date-time">Created</span>
                                            @else
                                                <span class="date-time">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="col-actions">
                                        <div class="action-buttons">
                                            <a href="{{ Url::link('admin/posts/edit/' . $post['id']) }}" class="btn-icon" title="Edit">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </a>
                                            <button class="btn-icon" title="More options">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="1"></circle>
                                                    <circle cx="12" cy="5" r="1"></circle>
                                                    <circle cx="12" cy="19" r="1"></circle>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 3rem;">
                                        <p style="color: #6b7280; margin-bottom: 1rem;">No posts found</p>
                                        <a href="{{ Url::link('admin/posts/new') }}" class="btn btn-primary">Create Your First Post</a>
                                    </td>
                                </tr>
                            @endloop
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="table-footer">
                    <div class="table-info">
                        Showing {{ count($posts) }} of {{ $statusCounts['all'] }} posts
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
                        <span class="page-dots">...</span>
                        <button class="page-btn">25</button>
                        <button class="page-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </main>
@endsection

<!-- JS Scripts -->
@section('scripts')
    @parent
    <script src="{{Url::assets('js/posts.js')}}"></script>
@endsection
