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

        $categoryTree = self::fetchCategoryTree();
        $categories = $categoryTree;
        usort($categories, static function (array $a, array $b): int {
            return strcasecmp((string)$a['name'], (string)$b['name']);
        });
        $categories = array_slice($categories, 0, 6);

        return [
            'baseUrl' => self::baseUrl(),
            'defaultCategoryIcon' => self::DEFAULT_CATEGORY_ICON,
            'defaultProductIcon' => self::DEFAULT_PRODUCT_ICON,
            'homeHeroSlides' => $homeHeroSlides,
            'homeServiceBlocks' => $homeServiceBlocks,
            'homeAbout' => self::fetchHomeAbout(),
            'homeContact' => self::fetchHomeContact(),
            'sitePopup' => self::fetchSitePopup(),
            'stats' => [
                'total_products' => (int)($stats['total_products'] ?? 0),
                'featured_products' => (int)($stats['featured_products'] ?? 0),
                'total_categories' => count($categories),
            ],
            'categories' => $categories,
            'navCategories' => $categoryTree,
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
                      )
                )
            )
            ";
            $like = '%' . $search . '%';
            $categoryParams = [$like, $like, $like, $like];
        }

        $categorySql .= "
            GROUP BY c.id
            ORDER BY LOWER(c.name), c.name
        ";

        $categoryStmt = $db->prepare($categorySql);
        $categoryStmt->execute($categoryParams);
        $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
        $categories = self::flattenCategories(self::fetchCategoryTree());
        if ($search !== '') {
            $needle = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
            $categories = array_values(array_filter($categories, static function (array $category) use ($needle): bool {
                $haystack = (string)$category['name'] . ' ' . (string)($category['description'] ?? '');
                $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);

                return self::contains($haystack, $needle);
            }));
        }

        return [
            'baseUrl' => self::baseUrl(),
            'defaultCategoryIcon' => self::DEFAULT_CATEGORY_ICON,
            'defaultProductIcon' => self::DEFAULT_PRODUCT_ICON,
            'navCategories' => self::fetchCategoryTree(),
            'sitePopup' => self::fetchSitePopup(),
            'categories' => $categories,
            'products' => self::fetchProducts("
                WHERE p.status = 'activo'" . ($search !== '' ? "
                  AND (
                    p.title LIKE ?
                    OR COALESCE(p.description, '') LIKE ?
                    OR COALESCE(c.name, '') LIKE ?
                  )" : '') . "
                ORDER BY p.is_featured DESC, p.created_at DESC, p.id DESC
            ", $search !== '' ? array_fill(0, 3, '%' . $search . '%') : []),
            'searchQuery' => $search,
        ];
    }

    public static function categoryViewData(string $categorySlug, ?string $subCategorySlug = null): ?array
    {
        $segments = array_values(array_filter(explode('/', trim($categorySlug, '/')), static function (string $part): bool {
            return $part !== '';
        }));
        if ($subCategorySlug !== null && $subCategorySlug !== '') {
            $segments[] = $subCategorySlug;
        }

        $breadcrumbs = self::findCategoryPath(self::fetchCategoryTree(), $segments);
        if ($breadcrumbs === null || empty($breadcrumbs)) {
            return null;
        }

        $parentCategory = $breadcrumbs[0];
        $currentCategory = $breadcrumbs[count($breadcrumbs) - 1];
        $activeChild = count($breadcrumbs) > 1 ? $currentCategory : null;
        $children = $currentCategory['children'] ?? [];
        $categoryIds = self::collectCategoryIds($currentCategory);

        $products = self::fetchProductsForCategoryIds($categoryIds, (int)$currentCategory['id']);

        return [
            'baseUrl' => self::baseUrl(),
            'defaultCategoryIcon' => self::DEFAULT_CATEGORY_ICON,
            'defaultProductIcon' => self::DEFAULT_PRODUCT_ICON,
            'navCategories' => self::fetchCategoryTree(),
            'sitePopup' => self::fetchSitePopup(),
            'parentCategory' => $parentCategory,
            'currentCategory' => $currentCategory,
            'activeChild' => $activeChild,
            'breadcrumbs' => $breadcrumbs,
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

        $categoryPath = !empty($product['category_id']) ? self::findCategoryPathById((int)$product['category_id']) : [];
        $primaryCategory = !empty($categoryPath) ? $categoryPath[count($categoryPath) - 1] : null;
        $parentCategory = count($categoryPath) > 1 ? $categoryPath[0] : null;
        $relatedIds = array_column($categoryPath, 'id');

        $relatedProducts = array_values(array_filter(
            self::fetchProductsForCategoryIds($relatedIds, !empty($primaryCategory['id']) ? (int)$primaryCategory['id'] : null),
            static function (array $item) use ($slug): bool {
                return $item['slug'] !== $slug;
            }
        ));
        $relatedProducts = array_slice($relatedProducts, 0, 4);

        return [
            'baseUrl' => self::baseUrl(),
            'defaultCategoryIcon' => self::DEFAULT_CATEGORY_ICON,
            'defaultProductIcon' => self::DEFAULT_PRODUCT_ICON,
            'navCategories' => self::fetchCategoryTree(),
            'sitePopup' => self::fetchSitePopup(),
            'product' => self::mapProductDetail($product, $images, $primaryCategory, $parentCategory, $categoryPath),
            'relatedProducts' => $relatedProducts,
        ];
    }

    public static function aboutViewData(): array
    {
        return [
            'baseUrl' => self::baseUrl(),
            'defaultCategoryIcon' => self::DEFAULT_CATEGORY_ICON,
            'defaultProductIcon' => self::DEFAULT_PRODUCT_ICON,
            'navCategories' => self::fetchCategoryTree(),
            'sitePopup' => self::fetchSitePopup(),
            'aboutPage' => self::fetchAboutPage(),
        ];
    }

    public static function servicePageViewData(string $slug): ?array
    {
        $page = self::fetchServicePage($slug);
        if ($page === null) {
            return null;
        }

        return [
            'baseUrl' => self::baseUrl(),
            'defaultCategoryIcon' => self::DEFAULT_CATEGORY_ICON,
            'defaultProductIcon' => self::DEFAULT_PRODUCT_ICON,
            'navCategories' => self::fetchCategoryTree(),
            'sitePopup' => self::fetchSitePopup(),
            'servicePage' => $page,
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

    private static function fetchProductsForCategoryIds(array $categoryIds, ?int $activeCategoryId = null): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $db = self::db();
        $ids = array_values(array_unique(array_map('intval', $categoryIds)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $priorityCategoryId = $activeCategoryId !== null && in_array($activeCategoryId, $ids, true)
            ? $activeCategoryId
            : $ids[0];

        $stmt = $db->prepare("
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
                MIN(
                    CASE
                        WHEN p.category_id = ? OR pc.category_id = ? THEN 0
                        WHEN p.category_id IN ($placeholders) OR pc.category_id IN ($placeholders) THEN 1
                        ELSE 2
                    END
                ) AS category_match_priority,
                MIN(
                    CASE
                        WHEN pc.category_id = ? THEN pc.position
                        WHEN pc.category_id IN ($placeholders) THEN pc.position
                        ELSE NULL
                    END
                ) AS category_position,
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
            GROUP BY p.id
            ORDER BY category_match_priority ASC,
                     CASE WHEN category_position IS NULL THEN 1 ELSE 0 END ASC,
                     category_position ASC,
                     p.is_featured DESC,
                     p.created_at DESC,
                     p.id DESC
        ");
        $stmt->execute(array_merge(
            [$priorityCategoryId, $priorityCategoryId],
            $ids,
            $ids,
            [$priorityCategoryId],
            $ids,
            $ids,
            $ids
        ));

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

    private static function mapProductDetail(array $product, array $images, ?array $primaryCategory, ?array $parentCategory, array $categoryPath = []): array
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
            'category_path' => $categoryPath,
            'category_badge' => $parentCategory ? ($parentCategory['name'] . ' › ' . ($primaryCategory['name'] ?? '')) : ($primaryCategory['name'] ?? 'Catalogo'),
            'url' => self::baseUrl() . '/producto/' . rawurlencode((string)$product['slug']),
        ];
    }

    private static function mapCategory(array $category): array
    {
        $parentId = isset($category['parent_id']) && $category['parent_id'] !== null ? (int)$category['parent_id'] : null;
        $slug = $category['slug'];
        $pathSlugs = $category['path_slugs'] ?? [$slug];
        $url = self::baseUrl() . '/catalogo/' . implode('/', array_map(
            static function (string $pathSlug): string {
                return rawurlencode($pathSlug);
            },
            $pathSlugs
        ));

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
            'depth' => (int)($category['depth'] ?? max(0, count($pathSlugs) - 1)),
            'path_slugs' => $pathSlugs,
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
            ORDER BY LOWER(c.name), c.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        return self::buildCategoryTree($rows);
    }

    private static function buildCategoryTree(array $rows): array
    {
        $nodes = [];
        $childrenByParent = [];

        foreach ($rows as $row) {
            $nodes[(int)$row['id']] = $row;
            $childrenByParent[(int)($row['parent_id'] ?? 0)][] = (int)$row['id'];
        }

        $build = function (int $id, array $pathSlugs = [], int $depth = 0) use (&$build, &$nodes, &$childrenByParent): array {
            $row = $nodes[$id];
            $pathSlugs[] = (string)$row['slug'];
            $row['path_slugs'] = $pathSlugs;
            $row['depth'] = $depth;

            $mapped = self::mapCategory($row);
            $mapped['children'] = [];

            foreach ($childrenByParent[$id] ?? [] as $childId) {
                $mapped['children'][] = $build($childId, $pathSlugs, $depth + 1);
            }

            $mapped['product_count'] += array_sum(array_column($mapped['children'], 'product_count'));

            return $mapped;
        };

        $tree = [];
        foreach ($childrenByParent[0] ?? [] as $rootId) {
            $tree[] = $build($rootId);
        }

        return $tree;
    }

    private static function findCategoryPath(array $categories, array $segments): ?array
    {
        if (empty($segments)) {
            return null;
        }

        $path = [];
        $level = $categories;
        foreach ($segments as $segment) {
            $match = null;
            foreach ($level as $category) {
                if ($category['slug'] === $segment) {
                    $match = $category;
                    break;
                }
            }

            if ($match === null) {
                return null;
            }

            $path[] = $match;
            $level = $match['children'] ?? [];
        }

        return $path;
    }

    private static function flattenCategories(array $categories): array
    {
        $flat = [];
        foreach ($categories as $category) {
            $flat[] = $category;
            $flat = array_merge($flat, self::flattenCategories($category['children'] ?? []));
        }

        return $flat;
    }

    private static function findCategoryPathById(int $categoryId): array
    {
        $walk = function (array $categories, array $path = []) use (&$walk, $categoryId): ?array {
            foreach ($categories as $category) {
                $nextPath = array_merge($path, [$category]);
                if ((int)$category['id'] === $categoryId) {
                    return $nextPath;
                }

                $found = $walk($category['children'] ?? [], $nextPath);
                if ($found !== null) {
                    return $found;
                }
            }

            return null;
        };

        return $walk(self::fetchCategoryTree()) ?? [];
    }

    private static function collectCategoryIds(array $category): array
    {
        $ids = [(int)$category['id']];
        foreach ($category['children'] ?? [] as $child) {
            $ids = array_merge($ids, self::collectCategoryIds($child));
        }

        return array_values(array_unique($ids));
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
                    'link_url' => '/alquileres',
                    'title' => 'Alquileres',
                    'description' => 'Muebles y objetos para producciones, eventos, vidrieras y ambientaciones.',
                    'style_key' => 'rent',
                ],
                [
                    'id' => 0,
                    'image_path' => 'assets/site/venta.png',
                    'link_url' => '/compra-venta',
                    'title' => 'Compra y venta',
                    'description' => 'Tasacion sin cargo y piezas seleccionadas para sumar caracter a cada ambiente.',
                    'style_key' => 'buy',
                ],
                [
                    'id' => 0,
                    'image_path' => 'assets/site/restauraciones.png',
                    'link_url' => '/restauraciones',
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
                'link_url' => self::normalizeServiceLink(trim((string)($block['link_url'] ?? '')), $styleKey),
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

    private static function homeContactDefaults(): array
    {
        return [
            'title' => 'Visitanos',
            'items' => [
                'address' => [
                    'label' => 'Direccion',
                    'value' => "Av. Rivadavia 7701, Flores\nCiudad de Buenos Aires",
                    'link' => 'https://maps.app.goo.gl/7YhnpWUrzZuzrprr9',
                ],
                'hours' => [
                    'label' => 'Horarios de atencion',
                    'value' => "Lunes a viernes de 9 a 18\nSabados de 9 a 17",
                    'link' => '',
                ],
                'phones' => [
                    'label' => 'Telefonos',
                    'value' => '4612-1221 / 4612-8787',
                    'link' => 'tel:46121221',
                ],
                'whatsapp' => [
                    'label' => 'WhatsApp',
                    'value' => '116571-4568',
                    'link' => 'https://wa.me/5491165714568',
                ],
                'email' => [
                    'label' => 'Email',
                    'value' => 'montepioantiguedades@gmail.com',
                    'link' => 'mailto:montepioantiguedades@gmail.com',
                ],
                'instagram' => [
                    'label' => 'Instagram',
                    'value' => 'Seguinos en Instagram',
                    'link' => 'https://www.instagram.com/',
                ],
            ],
        ];
    }

    private static function fetchHomeContact(): array
    {
        $defaults = self::homeContactDefaults();
        $keys = ['home_contact_title'];
        foreach (array_keys($defaults['items']) as $key) {
            $keys[] = 'home_contact_' . $key . '_label';
            $keys[] = 'home_contact_' . $key . '_value';
            $keys[] = 'home_contact_' . $key . '_link';
        }

        $db = self::db();
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $db->prepare("
            SELECT setting_key, setting_value
            FROM site_settings
            WHERE setting_key IN ($placeholders)
        ");
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $items = [];
        foreach ($defaults['items'] as $key => $item) {
            $link = trim((string)($rows['home_contact_' . $key . '_link'] ?? $item['link']));
            if ($link !== '' && self::startsWith($link, '/')) {
                $link = self::baseUrl() . $link;
            }

            $items[$key] = [
                'label' => trim((string)($rows['home_contact_' . $key . '_label'] ?? $item['label'])),
                'value' => trim((string)($rows['home_contact_' . $key . '_value'] ?? $item['value'])),
                'link' => $link,
            ];
        }

        return [
            'title' => trim((string)($rows['home_contact_title'] ?? $defaults['title'])),
            'items' => $items,
        ];
    }

    private static function fetchAboutPage(): array
    {
        $db = self::db();
        $rows = $db->query("
            SELECT setting_key, setting_value
            FROM site_settings
            WHERE setting_key IN (
                'about_title',
                'about_intro',
                'about_cover_path',
                'about_content_html',
                'about_side_kicker',
                'about_side_value',
                'about_side_text',
                'about_cta_text',
                'about_cta_link'
            )
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $defaultContent = '<h2>Una casa con historia en Buenos Aires</h2><p>Desde 1985 nos especializamos en la compra, venta, alquiler y restauracion de antiguedades y muebles unicos en el corazon de Flores, CABA.</p><p>Montepio es una casa familiar donde cada pieza se elige, se conserva y se ofrece con criterio de oficio. Trabajamos con muebles, objetos decorativos, luminarias, arte, piezas para ambientaciones y restauraciones a medida.</p><p>Nuestro recorrido combina experiencia, taller propio y atencion cercana para acompanar a quienes buscan una pieza especial, necesitan tasar un objeto o quieren recuperar el valor de un mueble con historia.</p>';
        $coverPath = trim((string)($rows['about_cover_path'] ?? 'assets/brand/fachada-montepio.jpg'));

        return [
            'title' => trim((string)($rows['about_title'] ?? 'Quienes somos')),
            'intro' => trim((string)($rows['about_intro'] ?? 'Una version extendida de nuestra historia, el oficio y la forma en que trabajamos cada pieza.')),
            'cover_url' => $coverPath !== '' ? self::baseUrl() . '/' . ltrim($coverPath, '/') : null,
            'content_html' => trim((string)($rows['about_content_html'] ?? $defaultContent)) ?: $defaultContent,
            'side_kicker' => trim((string)($rows['about_side_kicker'] ?? 'Desde')),
            'side_value' => trim((string)($rows['about_side_value'] ?? '1985')),
            'side_text' => trim((string)($rows['about_side_text'] ?? 'Una casa dedicada a antiguedades, restauracion, alquileres y piezas con historia.')),
            'cta_text' => trim((string)($rows['about_cta_text'] ?? 'Hablar por WhatsApp')),
            'cta_link' => trim((string)($rows['about_cta_link'] ?? 'https://wa.me/5491165714568')),
        ];
    }

    private static function fetchHomeAbout(): array
    {
        $defaults = [
            'image_path' => 'assets/brand/fachada-montepio.jpg',
            'kicker' => 'Quienes somos',
            'title' => 'Una casa con historia en Buenos Aires',
            'description' => 'Desde 1985 nos especializamos en la compra, venta, alquiler y restauracion de antiguedades y muebles unicos en el corazon de Flores, CABA.',
            'list' => "Mas de 40 anos en el rubro\nTasacion de piezas sin cargo\nTaller propio de restauracion\nAlquiler para filmaciones y eventos\nVentas presenciales y online",
            'button_text' => 'Contactanos',
            'button_link' => '/quienes-somos',
            'badge_value' => '40+',
            'badge_text' => "anos de\nhistoria",
        ];

        $keys = array_map(static function (string $key): string {
            return 'home_about_' . $key;
        }, array_keys($defaults));
        $db = self::db();
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($placeholders)");
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $data = [];
        foreach ($defaults as $key => $value) {
            $data[$key] = trim((string)($rows['home_about_' . $key] ?? $value));
        }

        $buttonLink = $data['button_link'];
        if ($buttonLink !== '' && self::startsWith($buttonLink, '/')) {
            $buttonLink = self::baseUrl() . $buttonLink;
        }

        return [
            'image_url' => $data['image_path'] !== '' ? self::baseUrl() . '/' . ltrim($data['image_path'], '/') : null,
            'kicker' => $data['kicker'],
            'title' => $data['title'],
            'description' => $data['description'],
            'list_items' => array_values(array_filter(array_map('trim', preg_split('/\R/', $data['list']) ?: []))),
            'button_text' => $data['button_text'],
            'button_link' => $buttonLink,
            'badge_value' => $data['badge_value'],
            'badge_text' => $data['badge_text'],
        ];
    }

    private static function servicePageDefaults(): array
    {
        return [
            'alquileres' => [
                'title' => 'Alquileres',
                'intro' => 'Muebles y objetos para producciones, eventos, vidrieras y ambientaciones.',
                'cover_path' => 'assets/site/alquileres.png',
                'content_html' => '<h2>Alquileres para producciones y eventos</h2><p>Contamos con muebles, objetos decorativos, luminarias y piezas especiales para ambientaciones, filmaciones, vidrieras, sesiones fotograficas y eventos.</p><p>Podemos asesorarte para elegir piezas con caracter, coordinar disponibilidad y preparar cada objeto para su retiro o traslado.</p>',
            ],
            'compra-venta' => [
                'title' => 'Compra y venta',
                'intro' => 'Tasacion sin cargo y piezas seleccionadas para sumar caracter a cada ambiente.',
                'cover_path' => 'assets/site/venta.png',
                'content_html' => '<h2>Compra y venta de antiguedades</h2><p>Seleccionamos muebles, objetos y piezas con historia para quienes buscan incorporar caracter, oficio y materiales nobles a sus espacios.</p><p>Tambien recibimos consultas por tasaciones y compra de piezas. Evaluamos cada objeto con criterio y acompanamos el proceso de forma clara.</p>',
            ],
            'restauraciones' => [
                'title' => 'Restauraciones',
                'intro' => 'Lustre, retapizados, esterillados y trabajos artesanales con criterio de epoca.',
                'cover_path' => 'assets/site/restauraciones.png',
                'content_html' => '<h2>Restauraciones a medida</h2><p>Trabajamos en la recuperacion de muebles y objetos respetando su identidad, materiales y epoca. Realizamos lustres, retapizados, esterillados y reparaciones artesanales.</p><p>Cada trabajo se evalua segun el estado de la pieza y el resultado buscado, con una mirada puesta en conservar su valor y funcionalidad.</p>',
            ],
        ];
    }

    private static function fetchServicePage(string $slug): ?array
    {
        $defaults = self::servicePageDefaults();
        if (!isset($defaults[$slug])) {
            return null;
        }

        $db = self::db();
        $prefix = 'service_' . str_replace('-', '_', $slug) . '_';
        $keys = [
            $prefix . 'title',
            $prefix . 'intro',
            $prefix . 'cover_path',
            $prefix . 'content_html',
        ];

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $db->prepare("
            SELECT setting_key, setting_value
            FROM site_settings
            WHERE setting_key IN ($placeholders)
        ");
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $pageDefaults = $defaults[$slug];
        $coverPath = trim((string)($rows[$prefix . 'cover_path'] ?? $pageDefaults['cover_path']));

        return [
            'slug' => $slug,
            'title' => trim((string)($rows[$prefix . 'title'] ?? $pageDefaults['title'])),
            'intro' => trim((string)($rows[$prefix . 'intro'] ?? $pageDefaults['intro'])),
            'cover_url' => $coverPath !== '' ? self::baseUrl() . '/' . ltrim($coverPath, '/') : null,
            'content_html' => trim((string)($rows[$prefix . 'content_html'] ?? $pageDefaults['content_html'])) ?: $pageDefaults['content_html'],
        ];
    }

    private static function normalizeServiceLink(string $linkUrl, string $styleKey): string
    {
        if ($linkUrl === '') {
            switch ($styleKey) {
                case 'rent':
                    $linkUrl = '/alquileres';
                    break;
                case 'buy':
                    $linkUrl = '/compra-venta';
                    break;
                case 'restore':
                    $linkUrl = '/restauraciones';
                    break;
                default:
                    $linkUrl = '';
                    break;
            }
        }

        if ($linkUrl !== '' && self::startsWith($linkUrl, '/')) {
            return self::baseUrl() . $linkUrl;
        }

        return $linkUrl;
    }

    private static function contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }

    private static function startsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
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
