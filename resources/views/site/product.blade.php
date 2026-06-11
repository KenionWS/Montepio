@extends('site.layouts.app')

@section('title', $product['title'] . ' | Montepio Antiguedades')

@php
    $siteBase = rtrim($baseUrl, '/');
    $catalogUrl = $siteBase . '/catalogo';
    $parentCategory = $product['parent_category'];
    $primaryCategory = $product['primary_category'];
    $categoryPath = $product['category_path'] ?? [];
    $galleryImages = $product['images'] ?? [];
    $mainImage = $product['main_image'];
    $isRentalOnly = !empty($product['rental_only']);
    $topBadgeUrl = $primaryCategory['url'] ?? $catalogUrl;
    $topBadgeText = !empty($categoryPath)
        ? implode(' > ', array_column($categoryPath, 'name'))
        : ($primaryCategory['name'] ?? 'Catalogo');
    $activeNavParentSlug = $parentCategory['slug'] ?? ($primaryCategory['slug'] ?? null);
    $activeNavChildSlug = $parentCategory ? ($primaryCategory['slug'] ?? null) : null;
    $footerBackUrl = $topBadgeUrl;
    $footerBackLabel = 'Volver a la categoria';
    $previousProductUrl = $previousProduct['url'] ?? null;
    $previousProductTitle = $previousProduct['title'] ?? null;
    $nextProductUrl = $nextProduct['url'] ?? null;
    $nextProductTitle = $nextProduct['title'] ?? null;
    $whatsAppNumber = '5491165714568';
    $productUrl = url('/producto/' . rawurlencode((string) $product['slug']));
    $productLinkLine = "\n" . $productUrl;
    $consultMessage = rawurlencode('Hola! Quiero consultar por la pieza "' . $product['title'] . '".' . $productLinkLine);
    $saleMessage = rawurlencode('Hola! Vi la pieza "' . $product['title'] . '" y me interesa conocer disponibilidad de venta.' . $productLinkLine);
    $rentMessage = rawurlencode('Hola! Vi la pieza "' . $product['title'] . '" y me interesa conocer disponibilidad de alquiler.' . $productLinkLine);
    $generalMessage = rawurlencode('Hola! Quiero hacer una consulta general por la pieza "' . $product['title'] . '".' . $productLinkLine);
    $floatingWhatsappUrl = 'https://wa.me/' . $whatsAppNumber . '?text=' . $consultMessage;

    $styleBits = array_values(array_filter([
        $product['era'] ?: null,
        $product['style'] ?: null,
        $product['condition'] ?: null,
    ]));

    $specCards = array_values(array_filter([
        $product['material'] ? ['Material', $product['material']] : null,
        $product['era'] ? ['Epoca', $product['era']] : null,
        $product['dimensions'] ? ['Dimensiones', $product['dimensions']] : null,
        $product['condition'] ? ['Estado', $product['condition']] : null,
        $product['origin'] ? ['Origen', $product['origin']] : null,
    ]));

    $detailCards = array_values(array_filter([
        $product['dimensions'] ? ['Dimensiones', $product['dimensions']] : null,
        $product['material'] ? ['Materiales', $product['material']] : null,
        $product['origin'] ? ['Origen', $product['origin']] : null,
        $product['condition'] ? ['Estado y conservacion', $product['condition']] : null,
        $product['style'] ? ['Estilo', $product['style']] : null,
        $product['era'] ? ['Epoca', $product['era']] : null,
    ]));

    $tabs = [];
    if (!empty($detailCards)) {
        $tabs[] = ['id' => 'tab-ficha', 'label' => 'Ficha tecnica'];
    }
    if (!empty($product['shipping_options'])) {
        $tabs[] = ['id' => 'tab-envio', 'label' => 'Entrega'];
    }
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ $siteBase }}/assets/site/product.css">
@endpush

@push('scripts')
    <script src="{{ $siteBase }}/assets/site/product.js"></script>
@endpush

@section('content')
    <div class="breadcrumb-bar">
        <div class="breadcrumb-inner">
            <a href="{{ $siteBase }}/">Inicio</a>
            <span>></span>
            <a href="{{ $catalogUrl }}">Catalogo</a>
            <span>></span>
            @foreach ($categoryPath as $pathCategory)
                <a href="{{ $pathCategory['url'] }}">{{ $pathCategory['name'] }}</a>
                <span>></span>
            @endforeach

            <span class="current">{{ $product['title'] }}</span>
        </div>
    </div>

    <div class="product-nav-shell">
        <div class="product-nav-bar">
            <button
                type="button"
                class="product-back-btn"
                onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = {{ Js::from($footerBackUrl) }}; }"
            >
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
                Volver
            </button>

            @if ($previousProductUrl || $nextProductUrl)
                <div class="product-sequence-nav">
                    @if ($previousProductUrl)
                        <a href="{{ $previousProductUrl }}" class="sequence-btn prev" aria-label="Producto anterior: {{ $previousProductTitle }}">
                            <span class="sequence-direction">Anterior</span>
                            <span class="sequence-title">{{ $previousProductTitle }}</span>
                        </a>
                    @endif

                    @if ($nextProductUrl)
                        <a href="{{ $nextProductUrl }}" class="sequence-btn next" aria-label="Producto siguiente: {{ $nextProductTitle }}">
                            <span class="sequence-direction">Siguiente</span>
                            <span class="sequence-title">{{ $nextProductTitle }}</span>
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <section class="product-main">
        <div class="gallery">
            <div class="gallery-main">
                @if ($mainImage && ($mainImage['full_url'] || $mainImage['medium_url'] || $mainImage['thumb_url']))
                    <img id="galleryCurrentImage" src="{{ $mainImage['full_url'] ?: ($mainImage['medium_url'] ?: $mainImage['thumb_url']) }}" alt="{{ $product['title'] }}">
                @else
                    <span>{{ $defaultProductIcon }}</span>
                @endif

                @if (count($galleryImages) > 1)
                    <button type="button" class="gallery-nav-btn prev" data-gallery-direction="-1" aria-label="Imagen anterior">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    </button>
                    <button type="button" class="gallery-nav-btn next" data-gallery-direction="1" aria-label="Imagen siguiente">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                @endif
            </div>

            @if (count($galleryImages) > 1)
                <div class="gallery-thumbs">
                    @foreach ($galleryImages as $image)
                        @php
                            $thumbSrc = $image['thumb_url'] ?: ($image['medium_url'] ?: $image['full_url']);
                            $fullSrc = $image['full_url'] ?: ($image['medium_url'] ?: $image['thumb_url']);
                        @endphp
                        @if ($thumbSrc)
                            <div class="gallery-thumb {{ $loop->first ? 'active' : '' }}" data-full="{{ $fullSrc }}">
                                <img src="{{ $thumbSrc }}" alt="{{ $product['title'] }} miniatura {{ $loop->iteration }}">
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <div class="product-info-panel">
            <div class="prod-top-badges">
                <a href="{{ $topBadgeUrl }}" class="badge-cat">{{ $topBadgeText }}</a>
                <span class="badge-status {{ $isRentalOnly ? 'alquiler' : 'mixto' }}">{{ $product['availability_label'] }}</span>
            </div>

            <h1 class="prod-name">{{ $product['title'] }}</h1>

            @if (!empty($styleBits))
                <p class="prod-style">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    {{ implode(' · ', $styleBits) }}
                </p>
            @endif

            @if (!empty($specCards))
                <div class="prod-specs">
                    @foreach ($specCards as [$label, $value])
                        <div class="spec-item">
                            <span class="spec-label">{{ $label }}</span>
                            <span class="spec-value">{!! nl2br(e($value)) !!}</span>
                        </div>
                    @endforeach
                </div>
                <div class="prod-divider"></div>
            @endif

            @if (trim((string) $product['description']) !== '')
                <p class="prod-desc">{!! nl2br(e($product['description'])) !!}</p>
            @endif

            @if ($isRentalOnly)
                <div class="rent-block">
                    <div class="rent-header">
                        <div class="rent-icon">A</div>
                        <div class="rent-header-text">
                            <h4>Disponible solo para alquiler</h4>
                            <p>Ideal para producciones, eventos y ambientaciones.</p>
                        </div>
                    </div>
                    <div class="rent-tags">
                        <span class="rent-tag">Filmaciones</span>
                        <span class="rent-tag">Producciones</span>
                        <span class="rent-tag">Eventos</span>
                        <span class="rent-tag">Fotografia</span>
                    </div>
                    <a href="https://wa.me/{{ $whatsAppNumber }}?text={{ $rentMessage }}" target="_blank" rel="noopener" class="btn-wa-rent">Consultar alquiler por WhatsApp</a>
                </div>
            @else
                <div class="buy-block">
                    <div class="price-row">
                        <span class="price-main">{{ $product['price_label'] }}</span>
                        <span class="price-note">{{ $product['price_note'] }}</span>
                    </div>
                    <p class="price-disclaimer">La pieza puede consultarse tanto para alquiler como para venta, segun disponibilidad.</p>
                    <a href="https://wa.me/{{ $whatsAppNumber }}?text={{ $saleMessage }}" target="_blank" rel="noopener" class="btn-wa-pago">Consultar venta por WhatsApp</a>
                    <a href="https://wa.me/{{ $whatsAppNumber }}?text={{ $rentMessage }}" target="_blank" rel="noopener" class="btn-wa-consulta">Consultar alquiler</a>
                </div>
            @endif

            <div class="prod-actions">
                <a href="https://wa.me/{{ $whatsAppNumber }}?text={{ $consultMessage }}" target="_blank" rel="noopener" class="action-btn">Consultar</a>
                <a href="{{ $topBadgeUrl }}" class="action-btn">Ver categoria</a>
                <a href="https://wa.me/{{ $whatsAppNumber }}?text={{ $generalMessage }}" target="_blank" rel="noopener" class="action-btn">Consulta general</a>
            </div>
        </div>
    </section>

    @if (!empty($tabs))
        <section class="prod-tabs">
            <div class="tab-nav">
                @foreach ($tabs as $tab)
                    <button type="button" class="tab-btn {{ $loop->first ? 'active' : '' }}" data-tab="{{ $tab['id'] }}">{{ $tab['label'] }}</button>
                @endforeach
            </div>

            @if (!empty($detailCards))
                <div id="tab-ficha" class="tab-content {{ $tabs[0]['id'] === 'tab-ficha' ? 'active' : '' }}">
                    <div class="detail-grid">
                        @foreach ($detailCards as [$label, $value])
                            <div class="detail-card">
                                <h5>{{ $label }}</h5>
                                <p>{!! nl2br(e($value)) !!}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (!empty($product['shipping_options']))
                @php($shippingTabStartsActive = $tabs[0]['id'] === 'tab-envio')
                <div id="tab-envio" class="tab-content {{ $shippingTabStartsActive ? 'active' : '' }}">
                    <div class="detail-grid">
                        @foreach ($product['shipping_options'] as $shippingOption)
                            <div class="detail-card">
                                <h5>{{ $shippingOption['title'] }}</h5>
                                <p>{{ $shippingOption['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif

    <section class="related-section">
        <p class="section-label">Misma linea</p>
        <h2 class="section-title">Tambien te puede interesar</h2>

        <div class="related-grid">
            @forelse ($relatedProducts as $related)
                <a href="{{ $related['url'] }}" class="related-card">
                    <div class="related-media">
                        @if ($related['cover_url'])
                            <img src="{{ $related['cover_url'] }}" alt="{{ $related['title'] }}">
                        @else
                            <span>{{ $related['category_icon'] ?: $defaultProductIcon }}</span>
                        @endif

                        @if ($related['is_featured'])
                            <div class="product-badge-sm">Destacado</div>
                        @elseif (!empty($related['rental_only']))
                            <div class="product-badge-sm rent">Solo alquiler</div>
                        @endif
                    </div>
                    <div class="product-info-sm">
                        <span class="sub">{{ $related['category_name'] ?: 'Catalogo' }}</span>
                        <span class="name">{{ $related['title'] }}</span>
                        <span class="price {{ strpos((string) ($related['price_label'] ?? ''), 'Consultar') !== false ? 'consultar' : '' }}">{{ $related['price_label'] }}</span>
                    </div>
                </a>
            @empty
                <div class="empty-state">Cuando haya mas piezas cargadas en esta categoria, se van a mostrar aca automaticamente.</div>
            @endforelse
        </div>
    </section>
@endsection
