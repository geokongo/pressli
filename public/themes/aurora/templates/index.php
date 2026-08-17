@extends('aurora/templates/layout')

@section('main-content')
    <!-- Main Content -->
    <main class="site-content">
        <div class="content-area">

            <!-- Posts List -->
            <div class="post-list">

                @if(isset($post))
                    <!-- Featured Post -->
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
                        <h2 class="post-title">
                            <a href="{{ Url::link($post['slug']) }}">{{ $post['title'] }}</a>
                        </h2>
                        <div class="post-excerpt">
                            <p>{{ $post['excerpt'] or strip_tags(substr($post['content'], 0, 200)) . '...' }}</p>
                        </div>
                        <a href="{{ Url::link($post['slug']) }}" class="read-more">Read More &rarr;</a>
                    </article>
                @endif

                @if(!empty($recent_posts))
                    @foreach($recent_posts as $recent_post)
                        <article class="post-card">
                            @if($recent_post['featured_image'])
                                <div class="post-thumbnail">
                                    <a href="{{ Url::link($recent_post['slug']) }}">
                                        <img src="{{ Url::assets($recent_post['featured_image']) }}" alt="{{ $recent_post['featured_image_alt'] or $recent_post['title'] }}">
                                    </a>
                                </div>
                            @endif
                            <div class="post-meta">
                                <span class="post-date">{{ Date::format($recent_post['published_at'], 'F j, Y') }}</span>
                            </div>
                            <h2 class="post-title">
                                <a href="{{ Url::link($recent_post['slug']) }}">{{ $recent_post['title'] }}</a>
                            </h2>
                            <div class="post-excerpt">
                                <p>{{ $recent_post['excerpt'] or strip_tags(substr($recent_post['content'] ?? '', 0, 200)) . '...' }}</p>
                            </div>
                            <a href="{{ Url::link($recent_post['slug']) }}" class="read-more">Read More &rarr;</a>
                        </article>
                    @endforeach
                @endif

            </div>

            <!-- Pagination -->
            <nav class="pagination">
                <span class="current">1</span>
                <a href="#">2</a>
                <a href="#">3</a>
                <a href="#">Next &rarr;</a>
            </nav>

        </div>

        <!-- Sidebar -->
        @include('aurora/templates/partials/sidebar')
        
    </main>
@endsection
