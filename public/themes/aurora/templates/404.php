@extends('aurora/templates/layout')

@section('main-content')
    <!-- Main Content -->
    <main class="site-content">
        <div class="content-area" style="grid-column: 1 / -1;">

            <!-- 404 Error -->
            <div class="error-404">
                <h1>404</h1>
                <h2>Page Not Found</h2>
                <p>Sorry, the page you're looking for doesn't exist or has been moved.</p>
                <a href="{{ Url::base() }}" class="btn">Return to Home</a>

                <!-- Search Section -->
                <div style="max-width: 500px; margin: 3rem auto;">
                    <h3 style="font-size: 1.25rem; margin-bottom: 1rem;">Try searching for what you need:</h3>
                    <form action="#" method="get">
                        <input type="search" placeholder="Search..." style="width: 100%; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 1rem;">
                    </form>
                </div>

                <!-- Helpful Links -->
                <div style="max-width: 600px; margin: 3rem auto; text-align: left;">
                    <h3 style="font-size: 1.25rem; margin-bottom: 1rem; text-align: center;">You might be interested in:</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        @if(!empty($recent_posts))
                            <div>
                                <h4 style="margin-bottom: 0.75rem;">Recent Posts</h4>
                                <ul style="list-style: none; padding: 0;">
                                    @foreach($recent_posts as $recent)
                                        <li style="margin-bottom: 0.5rem;"><a href="{{ Url::link($recent['slug']) }}">{{ $recent['title'] }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if(!empty($site_taxonomies['categories']))
                            <div>
                                <h4 style="margin-bottom: 0.75rem;">Categories</h4>
                                <ul style="list-style: none; padding: 0;">
                                    @foreach($site_taxonomies['categories'] as $category)
                                        <li style="margin-bottom: 0.5rem;"><a href="{{ Url::link('category', $category['slug']) }}">{{ $category['name'] }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </main>
@endsection