@extends('site.layouts.app')

@section('title', $currentCategory['name'] . ' | Montepio Antiguedades')

@php
    $siteBase = rtrim($baseUrl, '/');
    $catalogUrl = $siteBase . '/catalogo';
    $activeNavParentSlug = $parentCategory['slug'];
    $activeNavChildSlug = $activeChild['slug'] ?? null;
    $categoryTrail = $breadcrumbs ?? [$parentCategory, $currentCategory];
    $footerBackUrl = $siteBase . '/';
    $footerBackLabel = 'Volver al inicio';
    $heroCategory = $activeChild ?? $currentCategory;
    $heroCover = $heroCategory['cover_url'] ?? null;
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\SiteCatalog::assetUrl('assets/site/category.css', $siteBase) }}">
@endpush

@section('content')
    <div class="breadcrumb-bar">
        <div class="breadcrumb-inner">
            <a href="{{ $siteBase }}/">Inicio</a>
            <span>></span>
            <a href="{{ $catalogUrl }}">Catalogo</a>
            @foreach ($categoryTrail as $trailCategory)
                <span>></span>
                @if ($loop->last)
                    <span class="current">{{ $trailCategory['name'] }}</span>
                @else
                    <a href="{{ $trailCategory['url'] }}">{{ $trailCategory['name'] }}</a>
                @endif
            @endforeach
        </div>
    </div>

    <section class="cat-hero" @if($heroCover) style="background-image:url('{{ $heroCover }}')" @endif>
        <div class="cat-hero-overlay"></div>
        <div class="cat-hero-inner">
            <div class="cat-hero-text">
                <div class="cat-hero-tag">{{ $activeChild ? 'Subcategoria' : 'Categoria' }}</div>
                <h1>{{ $currentCategory['name'] }}</h1>
                @if ($currentCategory['description'] !== '')
                    <p>{{ $currentCategory['description'] }}</p>
                @endif
                <p>{{ $productsCount }} pieza(s) publicadas en esta seccion del catalogo.</p>
            </div>

            @if (!empty($children))
                <div class="cat-hero-subcats">
                    <a href="{{ $currentCategory['url'] }}" class="subcat-pill active">Todo</a>
                    @foreach ($children as $child)
                        <a href="{{ $child['url'] }}" class="subcat-pill">
                            {{ $child['name'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <div class="category-page-body">
        <aside class="sidebar">
            <div class="filter-block">
                <div class="filter-header">
                    <h4>Subcategorias</h4>
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
                <div class="filter-body">
                    <a href="{{ $currentCategory['url'] }}" class="filter-option active">
                        <span>Todo</span>
                        <span class="filter-count">{{ $productsCount }}</span>
                    </a>

                    @forelse ($children as $child)
                        <a href="{{ $child['url'] }}" class="filter-option">
                            <span>{{ $child['name'] }}</span>
                            <span class="filter-count">{{ $child['product_count'] }}</span>
                        </a>
                    @empty
                        <div class="filter-option">
                            <span>Sin subcategorias</span>
                            <span class="filter-count">0</span>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="sidebar-clear">
                <a href="{{ $currentCategory['url'] }}">Limpiar filtros</a>
            </div>
        </aside>

        <section class="products-area">
            <div class="products-toolbar">
                <p class="products-count"><strong>{{ $productsCount }} pieza(s)</strong> en {{ $currentCategory['name'] }}</p>
                <div class="toolbar-right">
                    <select class="sort-select" aria-label="Ordenar productos">
                        <option>Orden del catalogo</option>
                    </select>
                    <div class="view-toggle">
                        <span class="view-btn" aria-hidden="true">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                <rect x="1" y="1" width="6" height="6" rx="1"></rect>
                                <rect x="9" y="1" width="6" height="6" rx="1"></rect>
                                <rect x="1" y="9" width="6" height="6" rx="1"></rect>
                                <rect x="9" y="9" width="6" height="6" rx="1"></rect>
                            </svg>
                        </span>
                    </div>
                </div>
            </div>

            <div class="products-grid">
                @forelse ($products as $product)
                    <a href="{{ $product['url'] }}" class="product-card">
                        <div class="product-img">
                            @if ($product['cover_url'])
                                <img src="{{ $product['cover_url'] }}" alt="{{ $product['title'] }}">
                            @else
                                <span>{{ $product['category_icon'] ?: $defaultCategoryIcon }}</span>
                            @endif

                            @if ($product['is_featured'])
                                <div class="product-badge">Destacado</div>
                            @elseif (!empty($product['rental_only']))
                                <div class="product-badge">Solo alquiler</div>
                            @endif
                        </div>

                        <div class="product-info">
                            <span class="product-subcat">{{ $product['category_name'] ?? $currentCategory['name'] }}</span>
                            <span class="product-name">{{ $product['title'] }}</span>
                            <span class="product-desc">
                                {{ $product['description'] !== '' ? \Illuminate\Support\Str::limit($product['description'], 100) : 'Producto cargado desde el administrador.' }}
                            </span>
                            <div class="product-footer">
                                <div class="product-price{{ \Illuminate\Support\Str::startsWith((string) ($product['list_cta_label'] ?? ''), '$') ? '' : ' is-link' }}">
                                    {{ $product['list_cta_label'] ?? $product['price_label'] }}
                                    <small>{{ $product['list_cta_hint'] ?? $product['price_hint'] }}</small>
                                </div>
                                <div class="product-btn">
                                    <span aria-hidden="true">+</span>
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">No hay productos publicados para esta seleccion todavia.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
