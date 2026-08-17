            @if(!empty($related_posts))
                <!-- Related Posts -->
                <section class="related-posts">
                    <h3 class="related-posts-title">Related Posts</h3>
                    <div class="related-posts-grid">
                        @foreach($related_posts as $related)
                            <article class="related-post-card">
                                @if(!empty($related['featured_image']))
                                    <a href="{{ Url::link($related['slug']) }}" class="related-post-thumbnail">
                                        <img src="{{ Url::assets($related['featured_image']) }}" alt="{{ $related['featured_image_alt'] or $related['title'] }}">
                                    </a>
                                @else
                                    <a href="{{ Url::link($related['slug']) }}" class="related-post-thumbnail">
                                        <img src="https://via.placeholder.com/400x250/95a5a6/ffffff?text=Post" alt="{{ $related['title'] }}">
                                    </a>
                                @endif
                                <div class="related-post-content">
                                    <div class="post-meta">
                                        <span class="post-date">{{ Date::format($related['published_at'], 'F j, Y') }}</span>
                                    </div>
                                    <h4 class="related-post-title">
                                        <a href="{{ Url::link($related['slug']) }}">{{ $related['title'] }}</a>
                                    </h4>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
