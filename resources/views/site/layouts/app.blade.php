<!DOCTYPE html>
<html lang="es">
<head>
    @php($siteBase = rtrim($baseUrl ?? '', '/'))
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Montepio Antiguedades')</title>
    <link rel="icon" type="image/x-icon" href="{{ $siteBase }}/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ $siteBase }}/assets/site/shared.css">
    @stack('styles')
</head>
<body class="@yield('body_class')">
    @include('site.partials.header', [
        'siteBase' => $siteBase,
        'navCategories' => $navCategories ?? [],
        'activeNavParentSlug' => $activeNavParentSlug ?? null,
        'activeNavChildSlug' => $activeNavChildSlug ?? null,
        'searchQuery' => $searchQuery ?? '',
    ])

    @yield('content')

    @include('site.partials.footer', [
        'footerBackUrl' => $footerBackUrl ?? ($siteBase . '/'),
        'footerBackLabel' => $footerBackLabel ?? 'Volver al inicio',
    ])

    @if (($showFloatingWhatsapp ?? true) !== false)
        <a href="{{ $floatingWhatsappUrl ?? 'https://wa.me/5491165714568' }}" class="wa-float" target="_blank" rel="noopener" aria-label="WhatsApp">
            <svg width="50" height="50" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
        </a>
    @endif

    @if (!empty($sitePopup))
        <div
            class="site-popup"
            data-site-popup
            data-frequency="{{ $sitePopup['frequency'] }}"
            data-version="{{ $sitePopup['version'] }}"
            aria-hidden="true"
        >
            <div class="site-popup-backdrop" data-site-popup-close></div>
            <div class="site-popup-dialog {{ !empty($sitePopup['image_url']) ? 'has-image' : '' }}" role="dialog" aria-modal="true" aria-labelledby="site-popup-title">
                <button type="button" class="site-popup-close" data-site-popup-close aria-label="Cerrar popup">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                </button>
                @if (!empty($sitePopup['image_url']))
                    <div class="site-popup-media" style="background-image:url('{{ $sitePopup['image_url'] }}')"></div>
                @endif
                <div class="site-popup-content">
                    <p class="site-popup-kicker">Montepio</p>
                    @if (!empty($sitePopup['title']))
                        <h2 id="site-popup-title">{{ $sitePopup['title'] }}</h2>
                    @endif
                    @if (!empty($sitePopup['description']))
                        <p>{{ $sitePopup['description'] }}</p>
                    @endif
                    @if (!empty($sitePopup['cta_text']) && !empty($sitePopup['cta_link']))
                        <a href="{{ $sitePopup['cta_link'] }}" class="site-popup-cta">{{ $sitePopup['cta_text'] }}</a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @stack('scripts')
    @if (!empty($sitePopup))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var popup = document.querySelector('[data-site-popup]');
                if (!popup) return;

                var frequency = popup.getAttribute('data-frequency') || 'daily';
                var version = popup.getAttribute('data-version') || 'default';
                var key = 'montepio-popup-' + version;
                var now = Date.now();
                var intervals = {
                    daily: 24 * 60 * 60 * 1000,
                    weekly: 7 * 24 * 60 * 60 * 1000,
                    once: 3650 * 24 * 60 * 60 * 1000
                };

                function shouldShow() {
                    if (frequency === 'always') return true;
                    if (frequency === 'session') return sessionStorage.getItem(key) !== '1';

                    var lastShown = Number(localStorage.getItem(key) || 0);
                    return !lastShown || (now - lastShown) > (intervals[frequency] || intervals.daily);
                }

                function markShown() {
                    if (frequency === 'always') return;
                    if (frequency === 'session') {
                        sessionStorage.setItem(key, '1');
                        return;
                    }
                    localStorage.setItem(key, String(now));
                }

                function closePopup() {
                    popup.classList.remove('is-visible');
                    popup.setAttribute('aria-hidden', 'true');
                }

                if (!shouldShow()) return;

                window.setTimeout(function () {
                    popup.classList.add('is-visible');
                    popup.setAttribute('aria-hidden', 'false');
                    markShown();
                }, 650);

                popup.querySelectorAll('[data-site-popup-close]').forEach(function (button) {
                    button.addEventListener('click', closePopup);
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') closePopup();
                });
            });
        </script>
    @endif
</body>
</html>
