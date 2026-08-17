@extends('aurora/templates/layout')

@section('main-content')
    <!-- Main Content (Full Width) -->
    <main class="site-content">
        <div class="content-area" style="max-width: 1200px; margin: 0 auto;">

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
    </main>
@endsection
