@extends('site.layouts.app')

@section('title', ($servicePage['title'] ?? 'Servicios') . ' | Montepio Antiguedades')
@section('body_class', 'about-page service-page')

@php
    $siteBase = rtrim($baseUrl, '/');
    $activeNavParentSlug = null;
    $activeNavChildSlug = null;
    $footerBackUrl = $siteBase . '/';
    $footerBackLabel = 'Volver al inicio';
    $coverUrl = $servicePage['cover_url'] ?? null;
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
            <h1>{{ $servicePage['title'] }}</h1>
            @if (!empty($servicePage['intro']))
                <p>{{ $servicePage['intro'] }}</p>
            @endif
        </div>
    </section>

    <main class="about-page-shell" id="{{ $servicePage['slug'] }}">
        <article class="about-article">
            {!! $servicePage['content_html'] !!}
        </article>

        <aside class="about-side">
            <div class="about-side-block">
                <span>Consulta</span>
                <strong>Montepio</strong>
                <p>Contanos que pieza o servicio estas buscando y te asesoramos segun disponibilidad.</p>
            </div>
            <a href="https://wa.me/5491165714568" target="_blank" rel="noopener" class="about-contact-btn">Hablar por WhatsApp</a>
        </aside>
    </main>
@endsection
