<!DOCTYPE html>
<html lang="en"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($post) ? $post['title'] . ' - ' . $site['name'] : $site['name'] }}</title>
    <meta name="description" content="{{ isset($post) && $post['excerpt'] ? $post['excerpt'] : $site['tagline'] }}">
    @if($site['favicon'])
        <link rel="icon" type="image/x-icon" href="{{ $site['favicon'] }}">
    @endif
    @section('styles')
        <link rel="stylesheet" href="{{Url::assets('themes/aurora/assets/css/style.css')}}">
        <!--@@if($customCSS)
                @{{{ $customCSS }}}
            @@endif -->
    @endsection
</head>
<body>

    <!-- Header -->
    @section('header')
        @include('aurora/templates/partials/header')
    @endsection

    <!-- Main Content -->
    @yield('main-content')

    <!-- Footer -->
    @section('footer')
        @include('aurora/templates/partials/footer')
    @endsection

    <!-- JS Scripts -->
    @section('scripts')
    <script src="assets/js/main.js"></script>
    @endsection
</body>
</html>
