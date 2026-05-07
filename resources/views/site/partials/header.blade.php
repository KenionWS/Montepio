<div class="topbar">
    <div class="topbar-inner">
        <div class="topbar-group">
            <span class="topbar-item topbar-icon-item"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.8 19.8 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.8 19.8 0 012.18 4.18 2 2 0 014.16 2h3a2 2 0 012 1.72c.12.9.33 1.77.64 2.6a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.48-1.26a2 2 0 012.11-.45c.83.31 1.7.52 2.6.64A2 2 0 0122 16.92z"></path></svg><a href="tel:4612-1221" target="_blank" rel="noopener">4612-1221</a> / <a href="tel:4612-8787" target="_blank" rel="noopener">4612-8787</a></span>
            <span class="topbar-item topbar-icon-item"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.8 19.8 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.8 19.8 0 012.18 4.18 2 2 0 014.16 2h3a2 2 0 012 1.72c.12.9.33 1.77.64 2.6a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.48-1.26a2 2 0 012.11-.45c.83.31 1.7.52 2.6.64A2 2 0 0122 16.92z"></path></svg><a href="tel:11 6571 4568" target="_blank" rel="noopener">11 6571 4568</a></span>
        </div>
        <div class="topbar-group">
            <span class="topbar-item topbar-icon-item"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle></svg><a href="https://maps.app.goo.gl/7YhnpWUrzZuzrprr9" target="_blank">Av. Rivadavia 7701, CABA</a></span>
            <a href="mailto:montepioantiguedades@gmail.com" class="topbar-item topbar-icon-item"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>montepioantiguedades@gmail.com</a>
            <a href="https://www.instagram.com/montepioantiguedades" class="topbar-item topbar-icon-item" target="_blank" rel="noopener"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="1"></circle></svg>/montepioantiguedades</a>
        </div>
    </div>
</div>

@if (!empty($navCategories))
    <input type="checkbox" id="mobile-menu-toggle" class="mobile-menu-checkbox" aria-hidden="true">
@endif
<input type="checkbox" id="mobile-search-toggle" class="mobile-search-checkbox" aria-hidden="true">

<header class="site-header">
    <div class="header-inner">
        <a href="{{ $siteBase }}/" class="site-logo" aria-label="Montepio Antiguedades">
            <img src="{{ $siteBase }}/assets/brand/montepio-logo.jpg" alt="Montepio Antiguedades" class="site-logo-image">
        </a>

        <form action="{{ $siteBase }}/catalogo" method="get" class="search-bar">
            <label for="mobile-search-toggle" class="mobile-search-button" aria-label="Abrir buscador">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="m20 20-3.5-3.5"></path>
                </svg>
            </label>
            <div class="search-field">
                <span class="search-icon" aria-hidden="true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m20 20-3.5-3.5"></path>
                    </svg>
                </span>
                <input type="search" name="q" value="{{ $searchQuery ?? '' }}" placeholder="Buscar en el catalogo...">
            </div>
        </form>

        <a href="https://wa.me/5491165714568" class="header-cta" target="_blank" rel="noopener" aria-label="Consultanos por WhatsApp">
            <svg class="header-cta-icon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            <span>Consultanos</span>
        </a>

        @if (!empty($navCategories))
            <label for="mobile-menu-toggle" class="mobile-menu-button" aria-label="Abrir menu">
                <span></span>
                <span></span>
                <span></span>
            </label>
        @endif
    </div>
</header>

@if (!empty($navCategories))
    <nav class="site-nav">
        <div class="mobile-menu-topbar">
            <div class="topbar-group">
                <span class="topbar-item mobile-topbar-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.8 19.8 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.8 19.8 0 012.18 4.18 2 2 0 014.16 2h3a2 2 0 012 1.72c.12.9.33 1.77.64 2.6a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.48-1.26a2 2 0 012.11-.45c.83.31 1.7.52 2.6.64A2 2 0 0122 16.92z"></path></svg>
                    4612-1221 / 4612-8787
                </span>
                <span class="topbar-item mobile-topbar-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.8 19.8 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.8 19.8 0 012.18 4.18 2 2 0 014.16 2h3a2 2 0 012 1.72c.12.9.33 1.77.64 2.6a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.48-1.26a2 2 0 012.11-.45c.83.31 1.7.52 2.6.64A2 2 0 0122 16.92z"></path></svg>
                    116571-4568
                </span>
            </div>
            <div class="topbar-group">
                <span class="topbar-item mobile-topbar-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    Av. Rivadavia 7701, CABA
                </span>
                <a href="mailto:montepioantiguedades@gmail.com" class="topbar-item mobile-topbar-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    montepioantiguedades@gmail.com
                </a>
                <a href="https://www.instagram.com/montepioantiguedades" class="topbar-item mobile-topbar-item" target="_blank" rel="noopener">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="1"></circle></svg>
                    /montepioantiguedades
                </a>
            </div>
        </div>
        <div class="nav-inner nav-inner-wrap">
            @foreach ($navCategories as $category)
                @php
                    $children = $category['children'] ?? [];
                    $isParentActive = ($activeNavParentSlug ?? null) === $category['slug'];
                @endphp
                @if (!empty($children))
                    <div class="nav-item">
                        <a href="{{ $category['url'] }}" class="{{ $isParentActive ? 'active' : '' }}">
                            {{ $category['name'] }}
                            <svg class="nav-chevron" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </a>
                        <div class="submenu">
                            <div class="submenu-aside">
                                <div>
                                    <h4>{{ $category['name'] }}</h4>
                                    <p>{{ $category['description'] !== '' ? $category['description'] : 'Piezas cargadas en esta categoria y sus subcategorias.' }}</p>
                                </div>
                                <a href="{{ $category['url'] }}" class="submenu-aside-link">Ver todo</a>
                            </div>
                            <div class="submenu-body">
                                <h5>Subcategorias</h5>
                                <div class="submenu-grid submenu-grid-full">
                                    @foreach ($children as $child)
                                        <a href="{{ $child['url'] }}" class="submenu-link {{ ($activeNavChildSlug ?? null) === $child['slug'] ? 'active' : '' }}">
                                            <span class="sub-dot"></span>
                                            <span class="submenu-link-text">{{ $child['name'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ $category['url'] }}" class="{{ $isParentActive ? 'active' : '' }}">{{ $category['name'] }}</a>
                @endif
            @endforeach
        </div>
    </nav>
@endif

@if (!empty($navCategories))
    <script>
        document.querySelectorAll('.site-nav .nav-item > a').forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (!window.matchMedia('(max-width: 640px)').matches) {
                    return;
                }

                var item = link.closest('.nav-item');
                if (!item || !item.querySelector('.submenu')) {
                    return;
                }

                event.preventDefault();
                item.classList.toggle('mobile-open');
            });
        });
    </script>
@endif
