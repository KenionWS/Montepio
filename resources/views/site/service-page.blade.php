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
    $restorationWorks = [
        [
            'title' => 'Mesa extensible',
            'before' => 'mesa-antes.jpg',
            'after' => 'mesa-despues.jpeg',
            'description' => 'Recuperacion de lustre y terminacion de tapa extensible.',
        ],
        [
            'title' => 'Sillas de roble',
            'before' => 'sillas-roble-antes.jpeg',
            'after' => 'sillas-roble-despues.jpeg',
            'description' => 'Lavado de madera y retapizado a nuevo.',
        ],
        [
            'title' => 'Sillon',
            'before' => 'sillon-antes.jpeg',
            'after' => 'sillon-despues.jpeg',
            'description' => 'Tapizado a nuevo, relleno del asiento y encolado.',
        ],
        [
            'title' => 'Comoda con marmol',
            'before' => 'comoda-marmol-antes.jpg',
            'after' => 'comoda-marmol-despues.jpeg',
            'description' => 'Lustre, herrajes y realce de veta en madera.',
        ],
        [
            'title' => 'Aparador tallado',
            'before' => 'aparador-antes.jpg',
            'after' => 'aparador-despues.jpeg',
            'description' => 'Restauracion de superficie, puertas y detalles tallados.',
        ],
        [
            'title' => 'Consola de marmol',
            'before' => 'consola-antes.jpg',
            'after' => 'consola-despues.jpeg',
            'description' => 'Trabajo de acabado, molduras y limpieza de bronces.',
        ],
    ];
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

    @if (($servicePage['slug'] ?? '') === 'restauraciones')
        <section class="restoration-before-after" aria-labelledby="restoration-before-after-title">
            <div class="restoration-before-after-head">
                <p class="about-kicker">Antes y despues</p>
                <h2 id="restoration-before-after-title">Piezas recuperadas en nuestro taller</h2>
                <p>Una seleccion de trabajos de lustre, retapizado y restauracion artesanal realizados sobre muebles con historia.</p>
            </div>

            <div class="restoration-work-grid">
                @foreach ($restorationWorks as $work)
                    <article class="restoration-work-card">
                        <div class="restoration-work-compare" data-before-after style="--position: 50%;">
                            <div class="restoration-compare-image restoration-compare-before">
                                <span>Antes</span>
                                <img
                                    src="{{ $siteBase }}/assets/site/restauraciones/{{ $work['before'] }}"
                                    alt="{{ $work['title'] }} antes de la restauracion"
                                    loading="lazy"
                                >
                            </div>
                            <div class="restoration-compare-image restoration-compare-after">
                                <span>Despues</span>
                                <img
                                    src="{{ $siteBase }}/assets/site/restauraciones/{{ $work['after'] }}"
                                    alt="{{ $work['title'] }} despues de la restauracion"
                                    loading="lazy"
                                >
                            </div>
                            <input
                                class="restoration-compare-range"
                                type="range"
                                min="0"
                                max="100"
                                value="50"
                                aria-label="Comparar antes y despues de {{ $work['title'] }}"
                            >
                            <span class="restoration-compare-handle" aria-hidden="true">
                                <span></span>
                            </span>
                        </div>
                        <div class="restoration-work-copy">
                            <h3>{{ $work['title'] }}</h3>
                            <p>{{ $work['description'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection

@push('scripts')
    @if (($servicePage['slug'] ?? '') === 'restauraciones')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-before-after]').forEach(function (compare) {
                    var range = compare.querySelector('.restoration-compare-range');
                    if (!range) return;

                    function update(value) {
                        compare.style.setProperty('--position', value + '%');
                    }

                    range.addEventListener('input', function () {
                        update(range.value);
                    });

                    update(range.value);
                });
            });
        </script>
    @endif
@endpush
