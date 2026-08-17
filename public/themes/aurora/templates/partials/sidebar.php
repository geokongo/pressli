        <!-- Sidebar -->
        <aside class="sidebar">

            <!-- Search Widget -->
            <div class="widget widget-search">
                <h3 class="widget-title">Search</h3>
                <form action="{{ Url::link('search') }}" method="get">
                    <input type="search" name="q" placeholder="Search..." value="{{ Input::get('q') }}" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 4px;">
                </form>
            </div>

            @if(!empty($recent_posts))
                <!-- Recent Posts Widget -->
                <div class="widget widget-recent-posts">
                    <h3 class="widget-title">Recent Posts</h3>
                    <ul>
                        @foreach($recent_posts as $recent)
                            <li><a href="{{ Url::link($recent['slug']) }}">{{ $recent['title'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($site_taxonomies['categories']))
                <!-- Categories Widget -->
                <div class="widget widget-categories">
                    <h3 class="widget-title">Categories</h3>
                    <ul>
                        @foreach($site_taxonomies['categories'] as $category)
                            <li><a href="{{ Url::link('category', $category['slug']) }}">{{ $category['name'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($site_taxonomies['tags']))
                <!-- Tags Widget -->
                <div class="widget widget-tags">
                    <h3 class="widget-title">Tags</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        @foreach($site_taxonomies['tags'] as $tag)
                            <a href="{{ Url::link('tag', $tag['slug']) }}" style="padding: 0.25rem 0.75rem; background: #f0f0f0; border-radius: 20px; font-size: 0.875rem;">{{ $tag['name'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>
