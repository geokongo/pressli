@extends('aurora/templates/layout')

@section('main-content')
    <!-- Main Content -->
    <main class="site-content">
        <div class="content-area">

            <!-- Archive Header -->
            <header class="archive-header" style="margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 2px solid #e0e0e0;">
                <h1 class="archive-title" style="font-size: 2.5rem; margin-bottom: 0.5rem;">{{ $taxonomy or 'All Posts' }}</h1>
            </header>

            <!-- Posts List -->
            <div class="post-list">

                @loopelse($posts as $post)
                    <!-- Post -->
                    <article class="post-card">
                        @if($post['featured_image'])
                            <div class="post-thumbnail">
                                <a href="{{ Url::link($post['slug']) }}">
                                    <img src="{{ Url::assets($post['featured_image']) }}" alt="{{ $post['featured_image_alt'] or $post['title'] }}">
                                </a>
                            </div>
                        @endif
                        <div class="post-meta">
                            <span class="post-date">{{ Date::format($post['published_at'], 'F j, Y') }}</span>
                        </div>
                        <h2 class="post-title">
                            <a href="{{ Url::link($post['slug']) }}">{{ $post['title'] }}</a>
                        </h2>
                        @if($post['excerpt'])
                            <div class="post-excerpt">
                                <p>{{ $post['excerpt'] }}</p>
                            </div>
                        @endif
                        <a href="{{ Url::link($post['slug']) }}" class="read-more">Read More &rarr;</a>
                    </article>
                @empty
                    <p>No posts found.</p>
                @endloop

            </div>

            <!-- Pagination -->
            @if($pagination && $pagination['last_page'] > 1)
                <nav class="pagination">
                    @if($pagination['current_page'] > 1)
                        <a href="?page={{ $pagination['current_page'] - 1 }}">&larr; Previous</a>
                    @endif

                    @for($i = 1; $i <= $pagination['last_page']; $i++)
                        @if($i === $pagination['current_page'])
                            <span class="current">{{ $i }}</span>
                        @else
                            <a href="?page={{ $i }}">{{ $i }}</a>
                        @endif
                    @endfor

                    @if($pagination['current_page'] < $pagination['last_page'])
                        <a href="?page={{ $pagination['current_page'] + 1 }}">Next &rarr;</a>
                    @endif
                </nav>
            @endif

        </div>

        <!-- Sidebar -->
        <aside class="sidebar">

            <!-- All Categories Widget -->
            @if(!empty($site_taxonomies['categories']))
                <div class="widget widget-categories">
                    <h3 class="widget-title">All Categories</h3>
                    <ul>
                        @foreach($site_taxonomies['categories'] as $category)
                            <li><a href="{{ Url::link('category', $category['slug']) }}">{{ $category['name'] }} ({{ $category['post_count'] ?? 0 }})</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Recent Posts Widget -->
            @if(!empty($recent_posts))
                <div class="widget widget-recent-posts">
                    <h3 class="widget-title">Recent Posts</h3>
                    <ul>
                        @foreach($recent_posts as $recent_post)
                            <li><a href="{{ Url::link($recent_post['slug']) }}">{{ $recent_post['title'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </aside>
    </main>
@endsection
