    <!-- Header -->
    <header class="site-header">
        <div class="header-container">
            <div class="site-branding">
                <a href="{{ Url::base() }}">
                    @if($site['logo'])
                        <img src="{{ $site['logo'] }}" alt="{{ $site['name'] }}" class="site-logo">
                    @else
                        <h1>{{ $site['name'] }}</h1>
                    @endif
                </a>
            </div>
            <nav class="site-navigation">
                @if(!empty($site_menus['primary_menu']))
                    <ul>
                        @foreach($site_menus['primary_menu'] as $item)
                            <li>
                                <a href="{{ Url::link($item['url']) }}">{{ $item['title'] }}</a>
                                @if(!empty($item['children']))
                                    <ul>
                                        @foreach($item['children'] as $child)
                                            <li><a href="{{ Url::link($child['url']) }}">{{ $child['title'] }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </nav>
        </div>
    </header>