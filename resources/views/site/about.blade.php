@extends('site.layouts.app')

@section('title', ($aboutPage['title'] ?? 'Quienes somos') . ' | Montepio Antiguedades')
@section('body_class', 'about-page')

@php
    $siteBase = rtrim($baseUrl, '/');
    $activeNavParentSlug = null;
    $activeNavChildSlug = null;
    $footerBackUrl = $siteBase . '/';
    $footerBackLabel = 'Volver al inicio';
    $coverUrl = $aboutPage['cover_url'] ?? null;
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\SiteCatalog::assetUrl('assets/site/about.css', $siteBase) }}">
@endpush

@section('content')
    <section class="about-hero">
        @if ($coverUrl)
            <div class="about-hero-image" style="background-image:url('{{ $coverUrl }}')"></div>
        @endif
        <div class="about-hero-overlay"></div>
        <div class="about-hero-content">
            <p class="about-kicker">Montepio Antiguedades</p>
            <h1>{{ $aboutPage['title'] }}</h1>
            @if (!empty($aboutPage['intro']))
                <p>{{ $aboutPage['intro'] }}</p>
            @endif
        </div>
    </section>

    <main class="about-page-shell" id="quienes-somos">
        <article class="about-article">
            {!! $aboutPage['content_html'] !!}
        </article>

        <aside class="about-side">
            <div class="about-side-block">
                <span>{{ $aboutPage['side_kicker'] ?? 'Desde' }}</span>
                <strong>{{ $aboutPage['side_value'] ?? '1985' }}</strong>
                <p>{{ $aboutPage['side_text'] ?? 'Una casa dedicada a antiguedades, restauracion, alquileres y piezas con historia.' }}</p>
            </div>
            @if (!empty($aboutPage['cta_text']) && !empty($aboutPage['cta_link']))
                <a href="{{ $aboutPage['cta_link'] }}" target="_blank" rel="noopener" class="about-contact-btn">{{ $aboutPage['cta_text'] }}</a>
            @endif
        </aside>
    </main>
@endsection
