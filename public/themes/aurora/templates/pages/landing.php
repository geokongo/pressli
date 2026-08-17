@extends('aurora/templates/layout')

@section('main-content')
    <!-- Landing Page Content -->
    <main class="site-content" style="padding: 0;">
        <div style="max-width: 100%; margin: 0; padding: 0;">

            <!-- Landing Page Article -->
            <article style="padding: 0; border: none; box-shadow: none;">

                @if($post['featured_image'])
                    <div style="width: 100%; margin: 0;">
                        <img src="{{ Url::assets($post['featured_image']) }}" alt="{{ $post['featured_image_alt'] or $post['title'] }}" style="width: 100%; height: auto; display: block;">
                    </div>
                @endif

                <div style="max-width: 1200px; margin: 0 auto; padding: 3rem 1.5rem;">
                    <header style="text-align: center; margin-bottom: 3rem;">
                        <h1 style="font-size: 3rem; margin-bottom: 1rem;">{{ $post['title'] }}</h1>
                        @if($post['excerpt'])
                            <p style="font-size: 1.25rem; color: #666;">{{ $post['excerpt'] }}</p>
                        @endif
                    </header>

                    <div class="post-content">
                        {{{ $post['content'] }}}
                    </div>
                </div>

            </article>

        </div>
    </main>
@endsection
