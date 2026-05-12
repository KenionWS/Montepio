@extends('site.layouts.app')

@section('title', 'Montepio Antiguedades')
@section('body_class', 'home-page')

@php
    $siteBase = rtrim($baseUrl, '/');
    $catalogUrl = $siteBase . '/catalogo';
    $activeNavParentSlug = null;
    $activeNavChildSlug = null;
    $footerBackUrl = $siteBase . '/';
    $footerBackLabel = 'Volver al inicio';
    $heroSlides = !empty($homeHeroSlides) ? $homeHeroSlides : [];
    $heroImageAbout = $baseUrl . '/assets/brand/fachada-montepio.jpg';
    $heroCategories = !empty($navCategories) ? $navCategories : $categories;
    $instagramUrl = 'https://www.instagram.com/';
    $serviceCards = !empty($homeServiceBlocks) ? $homeServiceBlocks : [];
    $contactBlock = $homeContact ?? [];
    $contactItems = $contactBlock['items'] ?? [];
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ $siteBase }}/assets/site/home.css">
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const hero = document.querySelector('[data-hero-slider]');
            if (!hero) return;

            const slides = Array.from(hero.querySelectorAll('[data-hero-slide]'));
            const dots = Array.from(hero.querySelectorAll('[data-hero-dot]'));
            const prev = hero.querySelector('[data-hero-prev]');
            const next = hero.querySelector('[data-hero-next]');
            let current = Math.max(0, slides.findIndex(slide => slide.classList.contains('is-active')));
            if (current < 0) current = 0;
            let timer = null;

            const paint = (index) => {
                current = (index + slides.length) % slides.length;
                slides.forEach((slide, i) => slide.classList.toggle('is-active', i === current));
                dots.forEach((dot, i) => dot.classList.toggle('is-active', i === current));
            };

            const restart = () => {
                if (timer) window.clearInterval(timer);
                if (slides.length < 2) return;
                timer = window.setInterval(() => paint(current + 1), 6000);
            };

            prev?.addEventListener('click', () => {
                paint(current - 1);
                restart();
            });

            next?.addEventListener('click', () => {
                paint(current + 1);
                restart();
            });

            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    paint(index);
                    restart();
                });
            });

            paint(current);
            restart();
        });
    </script>
@endpush

@section('content')
    <section class="hero hero-slider{{ count($heroSlides) > 1 ? ' has-multiple' : '' }}" data-hero-slider>
        @foreach ($heroSlides as $index => $slide)
            <article class="hero-slide{{ $index === 0 ? ' is-active' : '' }}" data-hero-slide>
                @if (!empty($slide['link_url']))
                    <a href="{{ $slide['link_url'] }}" class="hero-link-overlay" target="{{ str_starts_with($slide['link_url'], 'http') ? '_blank' : '_self' }}" rel="noopener" aria-label="Abrir enlace destacado"></a>
                @endif
                <div class="hero-img" style="background-image:url('{{ $slide['image_url'] }}')"></div>
                <div class="hero-bg"></div>
                <div class="hero-content">
                    @if (!empty($slide['tag']))
                        <div class="hero-tag">{{ $slide['tag'] }}</div>
                    @endif
                    <h1>{{ $slide['title'] }}</h1>
                    @if (!empty($slide['description']))
                        <p>{{ $slide['description'] }}</p>
                    @endif
                    <div class="hero-actions">
                        @if (!empty($slide['button_primary_text']) && !empty($slide['button_primary_link']))
                            <a href="{{ $slide['button_primary_link'] }}" class="btn-primary">{{ $slide['button_primary_text'] }}</a>
                        @endif
                        @if (!empty($slide['button_secondary_text']) && !empty($slide['button_secondary_link']))
                            <a href="{{ $slide['button_secondary_link'] }}" class="btn-outline">{{ $slide['button_secondary_text'] }}</a>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach

        @if (count($heroSlides) > 1)
            <button type="button" class="hero-nav hero-prev" data-hero-prev aria-label="Slide anterior">&lsaquo;</button>
            <button type="button" class="hero-nav hero-next" data-hero-next aria-label="Slide siguiente">&rsaquo;</button>
            <div class="hero-dots">
                @foreach ($heroSlides as $index => $slide)
                    <button type="button" class="hero-dot{{ $index === 0 ? ' is-active' : '' }}" data-hero-dot aria-label="Ir al slide {{ $index + 1 }}"></button>
                @endforeach
            </div>
        @endif
    </section>

    <section class="services-showcase">
        <div class="section-inner">
            <div class="services-grid-lg">
                @foreach ($serviceCards as $serviceCard)
                    <a href="{{ $serviceCard['link_url'] ?: '#' }}" class="service-feature {{ $serviceCard['class'] }}" @if(!empty($serviceCard['image_url'])) style="background-image:url('{{ $serviceCard['image_url'] }}')" @endif>
                        <div class="service-feature-overlay"></div>
                        <div class="service-feature-content">
                            <p class="service-kicker">Montepio</p>
                            <h2>{{ $serviceCard['title'] }}</h2>
                            <p>{{ $serviceCard['description'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="categories-section" id="categorias">
        <div class="section-inner">
            <p class="section-label">Explora el stock</p>
            <h2 class="section-title">Categorias</h2>
            <p class="section-desc">Cada pieza es unica. Encontra muebles, objetos de decoracion y arte para cada ambiente.</p>

            @if (empty($heroCategories))
                <div class="empty-state">Todavia no hay categorias publicadas para mostrar en la home.</div>
            @else
                <div class="categories-grid">
                    @foreach ($heroCategories as $category)
                        @php
                            $categoryHasCover = !empty($category['cover_url']);
                            $categoryCardImage = $categoryHasCover ? $category['cover_url'] : $siteBase . '/assets/site/categoria-sin-imagen.png';
                        @endphp
                        <a href="{{ $category['url'] }}" class="cat-card cat-card-cover{{ $categoryHasCover ? '' : ' cat-card-fallback' }}" style="background-image:url('{{ $categoryCardImage }}')">
                            <span class="cat-card-overlay"></span>
                            <span class="cat-card-content">
                                <span class="cat-name">{{ $category['name'] }}</span>
                            </span>
                            <svg class="cat-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M5 12h14M12 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="about-section" id="nosotros">
        <div class="section-inner">
            <div class="about-grid">
                <div class="about-media">
                    <div class="about-image">
                        <img src="{{ $heroImageAbout }}" alt="Montepio Antiguedades">
                    </div>
                    <div class="about-badge">
                        <span class="years">40+</span>
                        <span class="of-exp">anos de<br>historia</span>
                    </div>
                </div>
                <div class="about-content">
                    <p class="section-label">Quienes somos</p>
                    <h2 class="section-title">Una casa con historia en Buenos Aires</h2>
                    <p class="section-desc">
                        Desde 1985 nos especializamos en la compra, venta, alquiler y restauracion de antiguedades y muebles unicos en el corazon de Flores, CABA.
                    </p>
                    <ul class="info-list">
                        <li><span class="dot"></span>Mas de 40 anos en el rubro</li>
                        <li><span class="dot"></span>Tasacion de piezas sin cargo</li>
                        <li><span class="dot"></span>Taller propio de restauracion</li>
                        <li><span class="dot"></span>Alquiler para filmaciones y eventos</li>
                        <li><span class="dot"></span>Ventas presenciales y online</li>
                    </ul>
                    <a href="{{ $siteBase }}/quienes-somos" class="btn-green">Contactanos</a>
                </div>
            </div>
        </div>
    </section>

    <div class="map-section" id="ubicacion">
        <div class="map-wrapper">
            <div class="map-info">
                <div>
                    <h2 class="section-title">{{ $contactBlock['title'] ?? 'Visitanos' }}</h2>
                </div>
                <div class="map-contact-item">
                    <div class="icon-box">
                        @if (!empty($contactItems['address']['link']))<a href="{{ $contactItems['address']['link'] }}" target="_blank" rel="noopener">@endif<svg width="18" height="18" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>@if (!empty($contactItems['address']['link']))</a>@endif
                    </div>
                    <div>@if (!empty($contactItems['address']['link']))<a href="{{ $contactItems['address']['link'] }}" target="_blank" rel="noopener">@endif<strong>{{ $contactItems['address']['label'] ?? 'Direccion' }}</strong>{!! nl2br(e($contactItems['address']['value'] ?? 'Av. Rivadavia 7701, Flores')) !!}@if (!empty($contactItems['address']['link']))</a>@endif</div>
                </div>
                <div class="map-contact-item">
                    <div class="icon-box">
                        <svg width="18" height="18" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </div>
                    <div>@if (!empty($contactItems['hours']['link']))<a href="{{ $contactItems['hours']['link'] }}" target="_blank" rel="noopener">@endif<strong>{{ $contactItems['hours']['label'] ?? 'Horarios de atencion' }}</strong>{!! nl2br(e($contactItems['hours']['value'] ?? 'Lunes a viernes de 9 a 18')) !!}@if (!empty($contactItems['hours']['link']))</a>@endif</div>
                </div>
                <div class="map-contact-item">
                    <div class="icon-box">
                        <svg width="18" height="18" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.77 9.11 19.79 19.79 0 01.7 0.5 2 2 0 012.68 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006.16 6.16l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                    </div>
                    <div><strong>{{ $contactItems['phones']['label'] ?? 'Telefonos' }}</strong>@if (!empty($contactItems['phones']['link']))<a href="{{ $contactItems['phones']['link'] }}">{!! nl2br(e($contactItems['phones']['value'] ?? '4612-1221 / 4612-8787')) !!}</a>@else{!! nl2br(e($contactItems['phones']['value'] ?? '4612-1221 / 4612-8787')) !!}@endif</div>
                </div>
                <div class="map-contact-item">
                    <div class="icon-box">
                        @if (!empty($contactItems['whatsapp']['link']))<a href="{{ $contactItems['whatsapp']['link'] }}" target="_blank" rel="noopener">@endif<svg width="18" height="18" fill="white" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>@if (!empty($contactItems['whatsapp']['link']))</a>@endif
                    </div>
                    <div><strong>{{ $contactItems['whatsapp']['label'] ?? 'WhatsApp' }}</strong>@if (!empty($contactItems['whatsapp']['link']))<a href="{{ $contactItems['whatsapp']['link'] }}" target="_blank" rel="noopener">{!! nl2br(e($contactItems['whatsapp']['value'] ?? '116571-4568')) !!}</a>@else{!! nl2br(e($contactItems['whatsapp']['value'] ?? '116571-4568')) !!}@endif</div>
                </div>
                <div class="map-contact-item">
                    <div class="icon-box">
                        @if (!empty($contactItems['email']['link']))<a href="{{ $contactItems['email']['link'] }}">@endif<svg width="18" height="18" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>@if (!empty($contactItems['email']['link']))</a>@endif
                    </div>
                    <div><strong>{{ $contactItems['email']['label'] ?? 'Email' }}</strong>@if (!empty($contactItems['email']['link']))<a href="{{ $contactItems['email']['link'] }}">{!! nl2br(e($contactItems['email']['value'] ?? 'montepioantiguedades@gmail.com')) !!}</a>@else{!! nl2br(e($contactItems['email']['value'] ?? 'montepioantiguedades@gmail.com')) !!}@endif</div>
                </div>
                <div class="map-contact-item">
                    <div class="icon-box">
                        @if (!empty($contactItems['instagram']['link']))<a href="{{ $contactItems['instagram']['link'] }}" target="_blank" rel="noopener">@endif<svg width="18" height="18" fill="white" viewBox="0 0 24 24"><path d="M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2m8.5 1.8h-8.5A3.95 3.95 0 0 0 3.8 7.75v8.5a3.95 3.95 0 0 0 3.95 3.95h8.5a3.95 3.95 0 0 0 3.95-3.95v-8.5a3.95 3.95 0 0 0-3.95-3.95M17.6 6.4a1.05 1.05 0 1 1 0 2.1 1.05 1.05 0 0 1 0-2.1M12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10m0 1.8A3.2 3.2 0 1 0 12 15.2 3.2 3.2 0 0 0 12 8.8"/></svg>@if (!empty($contactItems['instagram']['link']))</a>@endif
                    </div>
                    <div><strong>{{ $contactItems['instagram']['label'] ?? 'Instagram' }}</strong>@if (!empty($contactItems['instagram']['link']))<a href="{{ $contactItems['instagram']['link'] }}" target="_blank" rel="noopener">{!! nl2br(e($contactItems['instagram']['value'] ?? 'Seguinos en Instagram')) !!}</a>@else{!! nl2br(e($contactItems['instagram']['value'] ?? 'Seguinos en Instagram')) !!}@endif</div>
                </div>
            </div>
            <div class="map-embed">
                <iframe src="https://www.google.com/maps?q=Av.%20Rivadavia%207701,%20CABA&z=15&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </div>
@endsection
