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
    <link rel="stylesheet" href="{{ $siteBase }}/assets/site/about.css">
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
                <span>Desde</span>
                <strong>1985</strong>
                <p>Una casa dedicada a antiguedades, restauracion, alquileres y piezas con historia.</p>
            </div>
            <a href="https://wa.me/5491165714568" target="_blank" rel="noopener" class="about-contact-btn">Hablar por WhatsApp</a>
        </aside>
    </main>
@endsection
