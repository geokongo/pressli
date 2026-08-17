@extends('aurora/templates/layout')
 
@section('main-content')
    <!-- Main Content -->
    <main class="site-content">
        <div class="content-area">

            <!-- Static Page -->
            <article class="single-post">

                <header class="post-header">
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

            </article>

        </div>

        <!-- Sidebar -->
        <aside class="sidebar">

            <!-- Contact Info Widget -->
            <div class="widget">
                <h3 class="widget-title">Contact Info</h3>
                <ul style="list-style: none;">
                    <li style="margin-bottom: 1rem; border: none; padding: 0;">
                        <strong>Email:</strong><br>
                        <a href="mailto:hello@aurora.com">hello@aurora.com</a>
                    </li>
                    <li style="margin-bottom: 1rem; border: none; padding: 0;">
                        <strong>Location:</strong><br>
                        San Francisco, CA
                    </li>
                    <li style="margin-bottom: 0; border: none; padding: 0;">
                        <strong>Hours:</strong><br>
                        Mon-Fri: 9AM - 5PM PST
                    </li>
                </ul>
            </div>

            <!-- Social Widget -->
            <div class="widget">
                <h3 class="widget-title">Follow Us</h3>
                <ul style="list-style: none;">
                    <li style="border: none; padding: 0.5rem 0;"><a href="#">Twitter</a></li>
                    <li style="border: none; padding: 0.5rem 0;"><a href="#">GitHub</a></li>
                    <li style="border: none; padding: 0.5rem 0;"><a href="#">Dribbble</a></li>
                    <li style="border: none; padding: 0.5rem 0;"><a href="#">LinkedIn</a></li>
                </ul>
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

        </aside>
    </main>
@endsection

