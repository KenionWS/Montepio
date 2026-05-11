@extends('site.layouts.app')

@section('title', 'Catalogo | Montepio Antiguedades')

@php
    $siteBase = rtrim($baseUrl, '/');
    $activeNavParentSlug = null;
    $activeNavChildSlug = null;
    $footerBackUrl = $siteBase . '/';
    $footerBackLabel = 'Volver al inicio';
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ $siteBase }}/assets/site/catalog.css">
@endpush

@section('content')
    <main class="catalog-shell">
        <div class="catalog-layout">
            <aside class="catalog-panel">
                <p class="catalog-eyebrow">Categorias</p>
                <h1 class="catalog-title">Catalogo real</h1>
                <p class="catalog-copy">Este listado usa los productos y categorias cargados en el administrador y refleja lo que este activo para mostrar en el sitio.</p>

                @if (empty($categories))
                    <div class="empty-state" style="margin-top:20px;">No hay categorias disponibles todavia.</div>
                @else
                    <div class="category-list">
                        @foreach ($categories as $category)
                            <a class="category-chip" href="{{ $category['url'] ?? ($siteBase . '/catalogo') }}">
                                <span class="category-icon">{{ $category['icon'] ?: $defaultCategoryIcon }}</span>
                                <div>
                                    <strong>{{ $category['name'] }}</strong>
                                    <span>{{ $category['product_count'] }} producto(s)</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </aside>

            <section>
                <div class="toolbar">
                    <div>
                        <p class="catalog-eyebrow">Listado</p>
                        <h2 class="catalog-title" style="font-size:2rem;">
                            {{ $searchQuery !== '' ? 'Resultados para "' . e($searchQuery) . '"' : 'Productos publicados desde el back office' }}
                        </h2>
                        <p class="toolbar-copy">
                            {{ $searchQuery !== '' ? 'Se filtran productos y categorias por nombre, descripcion y categoria.' : 'Se muestran primero los destacados y luego el resto de las piezas activas, usando imagen real cuando existe y un bloque de respaldo cuando no hay foto.' }}
                        </p>
                    </div>
                    <strong class="count">{{ count($products) }} producto(s)</strong>
                </div>

                @if (empty($products))
                    <div class="empty-state">Todavia no hay productos activos para mostrar en el catalogo.</div>
                @else
                    <div class="catalog-grid">
                        @foreach ($products as $product)
                            <a class="catalog-card" href="{{ $product['url'] }}">
                                <div class="catalog-media">
                                    @if ($product['cover_url'])
                                        <img src="{{ $product['cover_url'] }}" alt="{{ $product['title'] }}">
                                    @else
                                        <div class="catalog-fallback">{{ $product['category_icon'] ?: $defaultProductIcon }}</div>
                                    @endif
                                </div>
                                <div class="catalog-body">
                                    <div class="catalog-topline">
                                        <span>{{ $product['category_name'] ?: 'Catalogo' }}</span>
                                        <span>{{ $product['availability_label'] }}</span>
                                    </div>
                                    <h3 class="catalog-name">{{ $product['title'] }}</h3>
                                    <p class="catalog-desc">{{ $product['description'] !== '' ? \Illuminate\Support\Str::limit($product['description'], 120) : 'Montepio Antiguedades desde 1985.' }}</p>
                                    <div class="catalog-tags">
                                        <span class="catalog-tag">{{ $product['price_label'] }}</span>
                                        @if ($product['is_featured'])
                                            <span class="catalog-tag">Destacado</span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </main>
@endsection
