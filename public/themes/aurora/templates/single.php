@extends('aurora/templates/layout')

@section('main-content')
    <!-- Main Content -->
    <main class="site-content">
        <div class="content-area">

            <!-- Single Post -->
            <article class="single-post">

                <header class="post-header">
                    <div class="post-meta">
                        <span class="post-date">{{ Date::format($post['published_at'], 'F j, Y') }}</span>
                        @if($post['author_name'])
                            <span class="post-author">by {{ $post['author_name'] }}</span>
                        @endif
                        @if(!empty($post_categories))
                            <span class="post-category">
                                @foreach($post_categories as $index => $category)
                                    <a href="{{ Url::link('category', $category['slug']) }}">{{ $category['name'] }}</a>{{ $index < count($post_categories) - 1 ? ', ' : '' }}
                                @endforeach
                            </span>
                        @endif
                    </div>
                    <h1 class="post-title">{{ $post['title'] }}</h1>
                </header>

                @if($post['featured_image'])
                    <div class="post-thumbnail">
                        <img src="{{ Url::assets($post['featured_image']) }}" alt="{{ $post['featured_image_alt'] or $post['title'] }}">
                    </div> 
                @endif

                <div class="post-content">
                    {{{ $post['content'] }}}
                </div>

                @if(!empty($post_categories))
                    <footer class="post-footer" style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <strong>Categories:</strong>
                            @foreach($post_categories as $category)
                                <a href="{{ Url::link('category', $category['slug']) }}" style="padding: 0.25rem 0.75rem; background: #f0f0f0; border-radius: 20px; font-size: 0.875rem;">{{ $category['name'] }}</a>
                            @endforeach
                        </div>
                    </footer>
                @endif

            </article>

            <!-- Post Navigation -->
            <nav class="post-navigation" style="display: flex; justify-content: space-between; margin-top: 3rem; padding: 2rem 0; border-top: 1px solid #e0e0e0;">
                <div class="nav-previous">
                    <a href="{{Url::base()}}">&larr; Previous Post</a>
                    <p style="color: #666; font-size: 0.875rem; margin-top: 0.5rem;">Digital Minimalism Guide</p>
                </div>
                <div class="nav-next" style="text-align: right;">
                    <a href="{{Url::base()}}">Next Post &rarr;</a>
                    <p style="color: #666; font-size: 0.875rem; margin-top: 0.5rem;">Modern CSS Grid Layouts</p>
                </div>
            </nav>

            <!-- Related Posts -->
            @include('aurora/templates/partials/related')

        </div>

        <!-- Sidebar -->
        <aside class="sidebar">

            @if($post['author_name'])
                <!-- Author Widget -->
                <div class="widget widget-author">
                    <h3 class="widget-title">About the Author</h3>
                    <div style="text-align: center;">
                        @if($post['author_avatar'])
                            <img src="{{ $post['author_avatar'] }}" alt="{{ $post['author_name'] }}" style="border-radius: 50%; margin: 0 auto 1rem; max-width: 120px;">
                        @else
                            <img src="https://via.placeholder.com/120/95a5a6/ffffff?text={{ substr($post['author_name'], 0, 2) }}" alt="{{ $post['author_name'] }}" style="border-radius: 50%; margin: 0 auto 1rem;">
                        @endif
                        <h4 style="margin-bottom: 0.5rem;">{{ $post['author_name'] }}</h4>
                        @if($post['author_email'])
                            <p style="color: #666; font-size: 0.875rem;">{{ $post['author_email'] }}</p>
                        @endif
                    </div>
                </div>
            @endif

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

            @if(!empty($categories_with_count))
                <!-- Categories Widget -->
                <div class="widget widget-categories">
                    <h3 class="widget-title">Post Categories</h3>
                    <ul>
                        @foreach($categories_with_count as $category)
                            <li><a href="{{ Url::link('category', $category['slug']) }}">{{ $category['name'] }} ({{ $category['post_count'] ?? 0 }})</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </aside>
    </main>
@endsection
