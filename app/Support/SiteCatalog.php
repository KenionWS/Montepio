<?php

namespace App\Support;

use PDO;

class SiteCatalog
{
    private const DEFAULT_CATEGORY_ICON = "🏛️";
    private const DEFAULT_PRODUCT_ICON = "🏛️";

    public static function homeViewData(): array
    {
        $db = self::db();
        $homeHeroSlides = self::fetchHomeHeroSlides();
        $homeServiceBlocks = self::fetchHomeServiceBlocks();

        $stats = $db->query("
            SELECT
                COUNT(*) AS total_products,
                SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) AS featured_products
            FROM products
            WHERE status = 'activo'
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        $categories = $db->query("
            SELECT
                c.id,
                c.name,
                c.slug,
                c.icon,
                c.cover_path,
                c.description,
                c.show_in_menu,
                c.parent_id,
                parent.slug AS parent_slug,
                COUNT(DISTINCT p.id) AS product_count
            FROM categories c
            LEFT JOIN categories parent ON parent.id = c.parent_id
            LEFT JOIN product_categories pc ON pc.category_id = c.id
            LEFT JOIN products p ON p.id = pc.product_id AND p.status = 'activo'
            WHERE c.parent_id IS NULL
            GROUP BY c.id
            ORDER BY product_count DESC, c.position, c.name
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'baseUrl' => self::baseUrl(),
            'defaultCategoryIcon' => self::DEFAULT_CATEGORY_ICON,
            'defaultProductIcon' => self::DEFAULT_PRODUCT_ICON,
            'homeHeroSlides' => $homeHeroSlides,
            'homeServiceBlocks' => $homeServiceBlocks,
            'sitePopup' => self::fetchSitePopup(),
            'stats' => [
                'total_products' => (int)($stats['total_products'] ?? 0),
                'featured_products' => (int)($stats['featured_products'] ?? 0),
                'total_categories' => count($categories),
            ],
            'categories' => array_map([self::class, 'mapCategory'], $categories),
            'navCategories' => self::fetchCategoryTree(),
            'featuredProducts' => self::fetchProducts("
                WHERE p.status = 'activo'
                ORDER BY p.is_featured DESC, p.created_at DESC, p.id DESC
                LIMIT 6
            "),
        ];
    }

    public static function catalogViewData(string $search = ''): array
    {
        $db = self::db();
        $search = trim($search);

        $categorySql = "
            SELECT
                c.id,
                c.name,
                c.slug,
                c.icon,
                c.cover_path,
                c.description,
                c.show_in_menu,
                c.parent_id,
                parent.slug AS parent_slug,
                COUNT(DISTINCT p.id) AS product_count
            FROM categories c
            LEFT JOIN categories parent ON parent.id = c.parent_id
            LEFT JOIN product_categories pc ON pc.category_id = c.id
            LEFT JOIN products p ON p.id = pc.product_id AND p.status = 'activo'
        ";

        $categoryParams = [];
        if ($search !== '') {
            $categorySql .= "
            WHERE (
                c.name LIKE ?
                OR COALESCE(c.description, '') LIKE ?
                OR EXISTS (
                    SELECT 1
                    FROM products px
                    LEFT JOIN product_categories pcx ON pcx.product_id = px.id
                    WHERE px.status = 'activo'
                      AND (
                        px.category_id = c.id
                        OR pcx.category_id = c.id
                      )
                      AND (
                        px.title LIKE ?
                        OR COALESCE(px.description, '') LIKE ?
                        OR COALESCE(px.sku, '') LIKE ?
                      )
                )
            )
            ";
            $like = '%' . $search . '%';
            $categoryParams = [$like, $like, $like, $like, $like];
        }

        $categorySql .= "
            GROUP BY c.id
            ORDER BY c.parent_id IS NOT NULL, c.position, c.name
        ";

        $categoryStmt = $db->prepare($categorySql);
        $categoryStmt->execute($categoryParams);
        $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'baseUrl' => self::baseUrl(),
            'defaultCategoryIcon' => self::DEFAULT_CATEGORY_ICON,
            'defaultProductIcon' => self::DEFAULT_PRODUCT_ICON,
            'navCategories' => self::fetchCategoryTree(),
            'sitePopup' => self::fetchSitePopup(),
            'categories' => array_map([self::class, 'mapCategory'], $categories),
            'products' => self::fetchProducts("
                WHERE p.status = 'activo'" . ($search !== '' ? "
                  AND (
                    p.title LIKE ?
                    OR COALESCE(p.description, '') LIKE ?
                    OR COALESCE(p.sku, '') LIKE ?
                    OR COALESCE(c.name, '') LIKE ?
                  )" : '') . "
                ORDER BY p.is_featured DESC, p.created_at DESC, p.id DESC
            ", $search !== '' ? array_fill(0, 4, '%' . $search . '%') : []),
            'searchQuery' => $search,
        ];
    }

    public static function categoryViewData(string $categorySlug, ?string $subCategorySlug = null): ?array
    {
        $db = self::db();

        $parentStmt = $db->prepare("
            SELECT
                c.id,
                c.name,
                c.slug,
                c.icon,
                c.cover_path,
                c.description,
                c.show_in_menu,
                c.parent_id,
                NULL AS parent_slug,
                COUNT(DISTINCT p.id) AS product_count
            FROM categories c
            LEFT JOIN product_categories pc ON pc.category_id = c.id
            LEFT JOIN products p ON p.id = pc.product_id AND p.status = 'activo'
            WHERE c.slug = ? AND c.parent_id IS NULL
            GROUP BY c.id
            LIMIT 1
        ");
        $parentStmt->execute([$categorySlug]);
        $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$parent) {
            return null;
        }

        $childrenStmt = $db->prepare("
            SELECT
                c.id,
                c.name,
                c.slug,
                c.icon,
                c.cover_path,
                c.description,
                c.show_in_menu,
                c.parent_id,
                parent.slug AS parent_slug,
                COUNT(DISTINCT p.id) AS product_count
            FROM categories c
            LEFT JOIN categories parent ON parent.id = c.parent_id
            LEFT JOIN product_categories pc ON pc.category_id = c.id
            LEFT JOIN products p ON p.id = pc.product_id AND p.status = 'activo'
            WHERE c.parent_id = ?
            GROUP BY c.id
            ORDER BY c.position, c.name
        ");
        $childrenStmt->execute([(int)$parent['id']]);
        $children = array_map([self::class, 'mapCategory'], $childrenStmt->fetchAll(PDO::FETCH_ASSOC));

        $parentCategory = self::mapCategory($parent);
        $currentCategory = $parentCategory;
        $activeChild = null;

        if ($subCategorySlug !== null) {
            foreach ($children as $child) {
                if ($child['slug'] === $subCategorySlug) {
                    $activeChild = $child;
                    $currentCategory = $child;
                    break;
                }
            }

            if (!$activeChild) {
                return null;
            }
        }

        $categoryIds = $activeChild
            ? [$activeChild['id']]
            : array_values(array_unique(array_merge([$parentCategory['id']], array_column($children, 'id'))));

        $products = self::fetchProductsForCategoryIds($categoryIds);

        return [
            'baseUrl' => self::baseUrl(),
            'defaultCategoryIcon' => self::DEFAULT_CATEGORY_ICON,
            'defaultProductIcon' => self::DEFAULT_PRODUCT_ICON,
            'navCategories' => self::fetchCategoryTree(),
            'sitePopup' => self::fetchSitePopup(),
            'parentCategory' => $parentCategory,
            'currentCategory' => $currentCategory,
            'activeChild' => $activeChild,
            'children' => $children,
            'products' => $products,
            'productsCount' => count($products),
        ];
    }

    public static function productViewData(string $slug): ?array
    {
        $db = self::db();

        $stmt = $db->prepare("
            SELECT
                p.id,
                p.title,
                p.slug,
                p.description,
                p.history,
                p.price,
                p.price_visible,
                p.type,
                p.rental_only,
                p.status,
                p.is_featured,
                p.created_at,
                p.sku,
                p.style,
                p.era,
                p.material,
                p.origin,
                p.dimensions,
                p.condition_val,
                p.pickup_available,
                p.shipping_transport,
                p.shipping_flete,
                p.shipping_encomienda,
                c.id AS category_id,
                c.name AS category_name,
                c.slug AS category_slug,
                c.icon AS category_icon,
                parent.id AS parent_category_id,
                parent.name AS parent_category_name,
                parent.slug AS parent_category_slug
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN categories parent ON parent.id = c.parent_id
            WHERE p.slug = ? AND p.status = 'activo'
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return null;
        }

        $imagesStmt = $db->prepare("
            SELECT path_thumb, path_medium, path_full, is_cover, position
            FROM product_images
            WHERE product_id = ?
            ORDER BY is_cover DESC, position ASC, id ASC
        ");
        $imagesStmt->execute([(int)$product['id']]);
        $images = array_map(function (array $image): array {
            $path = $image['path_full'] ?: ($image['path_medium'] ?: $image['path_thumb']);

            return [
                'thumb_url' => $image['path_thumb'] ? self::baseUrl() . '/' . ltrim($image['path_thumb'], '/') : null,
                'medium_url' => $image['path_medium'] ? self::baseUrl() . '/' . ltrim($image['path_medium'], '/') : null,
                'full_url' => $path ? self::baseUrl() . '/' . ltrim($path, '/') : null,
            ];
        }, $imagesStmt->fetchAll(PDO::FETCH_ASSOC));

        $relatedIds = [];
        if (!empty($product['category_id'])) {
            $relatedIds[] = (int)$product['category_id'];
        }
        if (!empty($product['parent_category_id'])) {
            $relatedIds[] = (int)$product['parent_category_id'];
        }

        $relatedProducts = array_values(array_filter(
            self::fetchProductsForCategoryIds($relatedIds),
            static fn(array $item): bool => $item['slug'] !== $slug
        ));
        $relatedProducts = array_slice($relatedProducts, 0, 4);

        $primaryCategory = null;
        if (!empty($product['category_id'])) {
            $primaryCategory = [
                'id' => (int)$product['category_id'],
                'name' => $product['category_name'],
                'slug' => $product['category_slug'],
                'url' => !empty($product['parent_category_slug'])
                    ? self::baseUrl() . '/catalogo/' . rawurlencode((string)$product['parent_category_slug']) . '/' . rawurlencode((string)$product['category_slug'])
                    : self::baseUrl() . '/catalogo/' . rawurlencode((string)$product['category_slug']),
            ];
        }

        $parentCategory = null;
        if (!empty($product['parent_category_id'])) {
            $parentCategory = [
                'id' => (int)$product['parent_category_id'],
                'name' => $product['parent_category_name'],
                'slug' => $product['parent_category_slug'],
                'url' => self::baseUrl() . '/catalogo/' . rawurlencode((string)$product['parent_category_slug']),
            ];
        }

        return [
            'baseUrl' => self::baseUrl(),
            'defaultCategoryIcon' => self::DEFAULT_CATEGORY_ICON,
            'defaultProductIcon' => self::DEFAULT_PRODUCT_ICON,
            'navCategories' => self::fetchCategoryTree(),
            'sitePopup' => self::fetchSitePopup(),
            'product' => self::mapProductDetail($product, $images, $primaryCategory, $parentCategory),
            'relatedProducts' => $relatedProducts,
        ];
    }

    private static function fetchProducts(string $suffixSql, array $params = []): array
    {
        $db = self::db();

        $sql = "
            SELECT
                p.id,
                p.title,
                p.slug,
                p.description,
                p.price,
                p.price_visible,
                p.type,
                p.rental_only,
                p.status,
                p.is_featured,
                p.created_at,
                c.name AS category_name,
                c.slug AS category_slug,
                c.icon AS category_icon,
                (
                    SELECT path_medium
                    FROM product_images
                    WHERE product_id = p.id
                    ORDER BY is_cover DESC, position ASC, id ASC
                    LIMIT 1
                ) AS cover_path
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            $suffixSql
        ";

        if ($params !== []) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        return array_map([self::class, 'mapProduct'], $rows);
    }

    private static function fetchProductsForCategoryIds(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $db = self::db();
        $ids = array_values(array_unique(array_map('intval', $categoryIds)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $db->prepare("
            SELECT DISTINCT
                p.id,
                p.title,
                p.slug,
                p.description,
                p.price,
                p.price_visible,
                p.type,
                p.rental_only,
                p.status,
                p.is_featured,
                p.created_at,
                c.name AS category_name,
                c.slug AS category_slug,
                c.icon AS category_icon,
                (
                    SELECT path_thumb
                    FROM product_images
                    WHERE product_id = p.id
                    ORDER BY is_cover DESC, position ASC, id ASC
                    LIMIT 1
                ) AS cover_path
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_categories pc ON pc.product_id = p.id
            WHERE p.status = 'activo'
              AND (p.category_id IN ($placeholders) OR pc.category_id IN ($placeholders))
            ORDER BY p.is_featured DESC, p.created_at DESC, p.id DESC
        ");
        $stmt->execute(array_merge($ids, $ids));

        return array_map([self::class, 'mapProductForCategory'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function mapProduct(array $product): array
    {
        $price = null;
        if ((int)($product['price_visible'] ?? 0) === 1 && $product['price'] !== null && $product['price'] !== '') {
            $price = '$ ' . number_format((float)$product['price'], 0, ',', '.');
        }
        $rentalOnly = (int)($product['rental_only'] ?? 0) === 1;

        return [
            'id' => (int)$product['id'],
            'title' => $product['title'],
            'slug' => $product['slug'],
            'description' => trim((string)($product['description'] ?? '')),
            'type' => $product['type'],
            'rental_only' => $rentalOnly,
            'status' => $product['status'],
            'is_featured' => (int)($product['is_featured'] ?? 0) === 1,
            'category_name' => $product['category_name'],
            'category_slug' => $product['category_slug'],
            'category_icon' => $product['category_icon'] ?: self::DEFAULT_CATEGORY_ICON,
            'cover_url' => $product['cover_path'] ? self::baseUrl() . '/' . ltrim($product['cover_path'], '/') : null,
            'availability_label' => $rentalOnly ? 'Solo alquiler' : 'Alquiler y venta',
            'price_label' => $price ?? ($rentalOnly ? 'Consultar alquiler' : 'Consultar'),
            'url' => self::baseUrl() . '/producto/' . rawurlencode((string)$product['slug']),
        ];
    }

    private static function mapProductForCategory(array $product): array
    {
        $mapped = self::mapProduct($product);
        $priceVisible = (int)($product['price_visible'] ?? 0) === 1 && $product['price'] !== null && $product['price'] !== '';
        $mapped['price_label'] = $priceVisible
            ? '$' . number_format((float)$product['price'], 0, ',', '.')
            : 'Consultar';
        $mapped['price_hint'] = $priceVisible
            ? 'Consultar'
            : (((int)($product['rental_only'] ?? 0) === 1) ? 'Solo alquiler' : 'Consultar');
        $mapped['list_cta_label'] = $priceVisible ? $mapped['price_label'] : 'Ver mas';
        $mapped['list_cta_hint'] = $mapped['price_hint'];

        return $mapped;
    }

    private static function mapProductDetail(array $product, array $images, ?array $primaryCategory, ?array $parentCategory): array
    {
        $priceVisible = (int)($product['price_visible'] ?? 0) === 1 && $product['price'] !== null && $product['price'] !== '';
        $priceLabel = $priceVisible ? '$' . number_format((float)$product['price'], 0, ',', '.') : 'Consultar';
        $description = trim((string)($product['description'] ?? ''));
        $rentalOnly = (int)($product['rental_only'] ?? 0) === 1;
        $shippingOptions = array_values(array_filter([
            !empty($product['pickup_available']) ? ['title' => 'Retiro en local', 'body' => 'Retiro por Av. Rivadavia 7701, CABA, coordinando previamente por WhatsApp.'] : null,
            !empty($product['shipping_transport']) ? ['title' => 'Envio por transporte', 'body' => 'Ideal para piezas grandes o delicadas. Se coordina segun destino y volumen.'] : null,
            !empty($product['shipping_flete']) ? ['title' => 'Envio con flete', 'body' => 'Coordinamos entrega con flete segun disponibilidad, distancia y acceso.'] : null,
            !empty($product['shipping_encomienda']) ? ['title' => 'Envio por encomienda', 'body' => 'Opcion disponible para piezas que se puedan despachar de forma segura.'] : null,
        ]));

        return [
            'id' => (int)$product['id'],
            'title' => $product['title'],
            'slug' => $product['slug'],
            'sku' => trim((string)($product['sku'] ?? '')),
            'type' => $product['type'],
            'rental_only' => $rentalOnly,
            'status' => $product['status'],
            'is_featured' => (int)($product['is_featured'] ?? 0) === 1,
            'style' => trim((string)($product['style'] ?? '')),
            'era' => trim((string)($product['era'] ?? '')),
            'material' => trim((string)($product['material'] ?? '')),
            'origin' => trim((string)($product['origin'] ?? '')),
            'dimensions' => trim((string)($product['dimensions'] ?? '')),
            'condition' => trim((string)($product['condition_val'] ?? '')),
            'description' => $description,
            'price_label' => $priceLabel,
            //'price_note' => $priceVisible ? 'Precio final' : ($rentalOnly ? 'Consultar disponibilidad' : 'Precio a consultar'),
            'price_note' => $priceVisible ? 'Consultar' : ($rentalOnly ? 'Consultar' : 'Consultar'),
            'availability_label' => $rentalOnly ? 'Solo alquiler' : 'Disponible para alquiler y venta',
            'has_spec_sheet' => trim((string)($product['style'] ?? '')) !== ''
                || trim((string)($product['era'] ?? '')) !== ''
                || trim((string)($product['material'] ?? '')) !== ''
                || trim((string)($product['origin'] ?? '')) !== ''
                || trim((string)($product['dimensions'] ?? '')) !== ''
                || trim((string)($product['condition_val'] ?? '')) !== '',
            'shipping_options' => $shippingOptions,
            'images' => $images,
            'main_image' => $images[0] ?? null,
            'primary_category' => $primaryCategory,
            'parent_category' => $parentCategory,
            'category_badge' => $parentCategory ? ($parentCategory['name'] . ' › ' . ($primaryCategory['name'] ?? '')) : ($primaryCategory['name'] ?? 'Catalogo'),
            'url' => self::baseUrl() . '/producto/' . rawurlencode((string)$product['slug']),
        ];
    }

    private static function mapCategory(array $category): array
    {
        $parentId = isset($category['parent_id']) && $category['parent_id'] !== null ? (int)$category['parent_id'] : null;
        $slug = $category['slug'];
        $url = $parentId && !empty($category['parent_slug'])
            ? self::baseUrl() . '/catalogo/' . rawurlencode((string)$category['parent_slug']) . '/' . rawurlencode($slug)
            : self::baseUrl() . '/catalogo/' . rawurlencode($slug);

        return [
            'id' => (int)$category['id'],
            'name' => $category['name'],
            'slug' => $slug,
            'icon' => $category['icon'] ?: self::DEFAULT_CATEGORY_ICON,
            'initials' => strtoupper(function_exists('mb_substr') ? mb_substr((string)$category['name'], 0, 2) : substr((string)$category['name'], 0, 2)),
            'cover_url' => !empty($category['cover_path']) ? self::baseUrl() . '/' . ltrim((string)$category['cover_path'], '/') : null,
            'description' => trim((string)($category['description'] ?? '')),
            'show_in_menu' => (int)($category['show_in_menu'] ?? 0) === 1,
            'product_count' => (int)($category['product_count'] ?? 0),
            'parent_id' => $parentId,
            'url' => $url,
        ];
    }

    private static function fetchCategoryTree(): array
    {
        $db = self::db();

        $rows = $db->query("
            SELECT
                c.id,
                c.name,
                c.slug,
                c.icon,
                c.cover_path,
                c.description,
                c.show_in_menu,
                c.parent_id,
                parent.slug AS parent_slug,
                c.position,
                COUNT(DISTINCT p.id) AS product_count
            FROM categories c
            LEFT JOIN categories parent ON parent.id = c.parent_id
            LEFT JOIN product_categories pc ON pc.category_id = c.id
            LEFT JOIN products p ON p.id = pc.product_id AND p.status = 'activo'
            GROUP BY c.id
            ORDER BY c.parent_id IS NOT NULL, c.position, c.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $parents = [];
        $childrenByParent = [];

        foreach ($rows as $row) {
            $mapped = self::mapCategory($row);

            if ($row['parent_id'] === null) {
                $mapped['children'] = [];
                $parents[(int)$row['id']] = $mapped;
            } else {
                $childrenByParent[(int)$row['parent_id']][] = $mapped;
            }
        }

        foreach ($childrenByParent as $parentId => $children) {
            if (isset($parents[$parentId])) {
                $parents[$parentId]['children'] = $children;
            }
        }

        return array_values($parents);
    }

    private static function fetchHomeHeroSlides(): array
    {
        $db = self::db();
        $slides = $db->query("
            SELECT *
            FROM home_hero_slides
            WHERE is_active = 1
            ORDER BY position ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($slides)) {
            return array_map(function (array $slide): array {
                $imagePath = trim((string)($slide['image_path'] ?? ''));

                return [
                    'id' => (int)($slide['id'] ?? 0),
                    'image_url' => self::baseUrl() . '/' . ltrim($imagePath, '/'),
                    'link_url' => trim((string)($slide['link_url'] ?? '')) ?: null,
                    'tag' => trim((string)($slide['tag'] ?? '')),
                    'title' => trim((string)($slide['title'] ?? '')),
                    'description' => trim((string)($slide['description'] ?? '')),
                    'button_primary_text' => trim((string)($slide['button_primary_text'] ?? '')),
                    'button_primary_link' => trim((string)($slide['button_primary_link'] ?? '')),
                    'button_secondary_text' => trim((string)($slide['button_secondary_text'] ?? '')),
                    'button_secondary_link' => trim((string)($slide['button_secondary_link'] ?? '')),
                ];
            }, $slides);
        }

        $rows = $db->query("
            SELECT setting_key, setting_value
            FROM site_settings
            WHERE setting_key IN (
                'home_hero_image_path',
                'home_hero_link',
                'home_hero_tag',
                'home_hero_title',
                'home_hero_description',
                'home_hero_button_primary_text',
                'home_hero_button_primary_link',
                'home_hero_button_secondary_text',
                'home_hero_button_secondary_link'
            )
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $imagePath = trim((string)($rows['home_hero_image_path'] ?? ''));
        if ($imagePath === '') {
            return [[
                'id' => 0,
                'image_url' => self::baseUrl() . '/assets/site/hero-principal.png',
                'link_url' => null,
                'tag' => 'Casa Montepio - CABA',
                'title' => 'Antiguedades con historia y estilo',
                'description' => 'Compra, venta, alquiler y restauracion de muebles y objetos unicos. Mas de 40 anos en Buenos Aires.',
                'button_primary_text' => 'Ver catalogo',
                'button_primary_link' => '#categorias',
                'button_secondary_text' => 'Como llegar',
                'button_secondary_link' => '#ubicacion',
            ]];
        }

        return [[
            'id' => 0,
            'image_url' => self::baseUrl() . '/' . ltrim($imagePath, '/'),
            'link_url' => trim((string)($rows['home_hero_link'] ?? '')) ?: null,
            'tag' => trim((string)($rows['home_hero_tag'] ?? 'Casa Montepio - CABA')),
            'title' => trim((string)($rows['home_hero_title'] ?? 'Antiguedades con historia y estilo')),
            'description' => trim((string)($rows['home_hero_description'] ?? 'Compra, venta, alquiler y restauracion de muebles y objetos unicos. Mas de 40 anos en Buenos Aires.')),
            'button_primary_text' => trim((string)($rows['home_hero_button_primary_text'] ?? 'Ver catalogo')),
            'button_primary_link' => trim((string)($rows['home_hero_button_primary_link'] ?? '#categorias')),
            'button_secondary_text' => trim((string)($rows['home_hero_button_secondary_text'] ?? 'Como llegar')),
            'button_secondary_link' => trim((string)($rows['home_hero_button_secondary_link'] ?? '#ubicacion')),
        ]];
    }

    private static function fetchHomeServiceBlocks(): array
    {
        $db = self::db();
        $blocks = $db->query("
            SELECT *
            FROM home_service_blocks
            WHERE is_active = 1
            ORDER BY position ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($blocks)) {
            $blocks = [
                [
                    'id' => 0,
                    'image_path' => 'assets/site/alquileres.png',
                    'title' => 'Alquileres',
                    'description' => 'Muebles y objetos para producciones, eventos, vidrieras y ambientaciones.',
                    'style_key' => 'rent',
                ],
                [
                    'id' => 0,
                    'image_path' => 'assets/site/venta.png',
                    'title' => 'Compra y venta',
                    'description' => 'Tasacion sin cargo y piezas seleccionadas para sumar caracter a cada ambiente.',
                    'style_key' => 'buy',
                ],
                [
                    'id' => 0,
                    'image_path' => 'assets/site/restauraciones.png',
                    'title' => 'Restauraciones',
                    'description' => 'Lustre, retapizados, esterillados y trabajos artesanales con criterio de epoca.',
                    'style_key' => 'restore',
                ],
            ];
        }

        return array_map(function (array $block): array {
            $styleKey = trim((string)($block['style_key'] ?? 'default'));
            $allowedStyles = ['rent', 'buy', 'restore', 'default'];
            if (!in_array($styleKey, $allowedStyles, true)) {
                $styleKey = 'default';
            }

            return [
                'id' => (int)($block['id'] ?? 0),
                'image_url' => self::baseUrl() . '/' . ltrim(trim((string)($block['image_path'] ?? '')), '/'),
                'title' => trim((string)($block['title'] ?? '')),
                'description' => trim((string)($block['description'] ?? '')),
                'class' => $styleKey === 'default' ? 'service-card-default' : 'service-card-' . $styleKey,
            ];
        }, $blocks);
    }

    private static function fetchSitePopup(): ?array
    {
        $db = self::db();
        $stmt = $db->prepare("
            SELECT *
            FROM site_popup
            WHERE id = 1 AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute();
        $popup = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$popup) {
            return null;
        }

        $title = trim((string)($popup['title'] ?? ''));
        $description = trim((string)($popup['description'] ?? ''));
        $imagePath = trim((string)($popup['image_path'] ?? ''));
        $ctaText = trim((string)($popup['cta_text'] ?? ''));
        $ctaLink = trim((string)($popup['cta_link'] ?? ''));

        if ($title === '' && $description === '' && $imagePath === '') {
            return null;
        }

        $frequency = trim((string)($popup['frequency'] ?? 'daily'));
        if (!in_array($frequency, ['always', 'session', 'daily', 'weekly', 'once'], true)) {
            $frequency = 'daily';
        }

        return [
            'id' => (int)($popup['id'] ?? 1),
            'frequency' => $frequency,
            'image_url' => $imagePath !== '' ? self::baseUrl() . '/' . ltrim($imagePath, '/') : '',
            'title' => $title,
            'description' => $description,
            'cta_text' => $ctaText,
            'cta_link' => $ctaLink,
            'version' => substr(sha1((string)($popup['updated_at'] ?? '') . '|' . $title . '|' . $description . '|' . $imagePath . '|' . $ctaText . '|' . $ctaLink), 0, 12),
        ];
    }

    private static function db(): PDO
    {
        require_once base_path('admin/lib/config.php');
        require_once base_path('admin/lib/db.php');

        return db();
    }

    private static function baseUrl(): string
    {
        require_once base_path('admin/lib/config.php');

        return defined('BASE_URL') ? BASE_URL : '';
    }
}
