    <!-- Footer -->
    <footer class="site-footer">
        <!-- Footer Widgets -->
        <div class="footer-widgets">
            <!-- About Widget -->
            <div class="footer-widget">
                <h3>About {{ $site['name'] }}</h3>
                <p>{{ $footer_about ?? $site['tagline'] ?? 'A minimalist blog theme that celebrates simplicity and elegance in web design.' }}</p>

                @if(!empty($social_links))
                    <div class="social-links">
                        @if(!empty($social_links['twitter']))
                            <a href="{{ $social_links['twitter'] }}" title="Twitter" target="_blank" rel="noopener">𝕏</a>
                        @endif
                        @if(!empty($social_links['facebook']))
                            <a href="{{ $social_links['facebook'] }}" title="Facebook" target="_blank" rel="noopener">f</a>
                        @endif
                        @if(!empty($social_links['instagram']))
                            <a href="{{ $social_links['instagram'] }}" title="Instagram" target="_blank" rel="noopener">📷</a>
                        @endif
                        @if(!empty($social_links['github']))
                            <a href="{{ $social_links['github'] }}" title="GitHub" target="_blank" rel="noopener">⚙</a>
                        @endif
                        @if(!empty($social_links['linkedin']))
                            <a href="{{ $social_links['linkedin'] }}" title="LinkedIn" target="_blank" rel="noopener">in</a>
                        @endif
                        @if(!empty($social_links['youtube']))
                            <a href="{{ $social_links['youtube'] }}" title="YouTube" target="_blank" rel="noopener">▶</a>
                        @endif
                    </div>
                @endif
            </div>

            @if(!empty($site_menus['footer_menu']))
                <!-- Quick Links Widget -->
                <div class="footer-widget">
                    <h3>Quick Links</h3>
                    <ul>
                        @foreach($site_menus['footer_menu'] as $item)
                            <li><a href="{{ Url::link($item['url']) }}">{{ $item['title'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($site_menus['categories_menu']))
                <!-- Categories Widget -->
                <div class="footer-widget">
                    <h3>Categories</h3>
                    <ul>
                        @foreach($site_menus['categories_menu'] as $item)
                            <li>
                                <a href="{{ Url::link($item['url']) }}">
                                    {{ $item['title'] }}
                                    @if(isset($item['count']) && $item['count'] > 0)
                                        ({{ $item['count'] }})
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Newsletter Widget -->
            <div class="footer-widget">
                <h3>Newsletter</h3>
                <p>Subscribe to get our latest content by email.</p>
                <form class="newsletter-form" action="{{ Url::link('newsletter/subscribe') }}" method="post">
                    {{{ Csrf::field() }}}
                    <input type="email" name="email" placeholder="Your email" required>
                    <button type="submit">Join</button>
                </form>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="footer-bottom-container">
                <p class="footer-info">
                    &copy; {{ date('Y') }} {{ $site['name'] }}. All rights reserved.
                </p>
                @if(!empty($site_menus['footer_links']))
                    <ul class="footer-menu">
                        @foreach($site_menus['footer_links'] as $item)
                            <li><a href="{{ Url::link($item['url']) }}">{{ $item['title'] }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </footer>
