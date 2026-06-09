<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (!extension_loaded('pdo_sqlite')) {
        die('<b>Error:</b> pdo_sqlite no está habilitado en php.ini');
    }

    try {
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        db_migrate($pdo);
    } catch (Exception $e) {
        die('<b>Error de base de datos:</b> ' . htmlspecialchars($e->getMessage()));
    }

    return $pdo;
}

function db_migrate(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT    NOT NULL,
            slug       TEXT    NOT NULL UNIQUE,
            icon       TEXT,
            cover_path TEXT,
            description TEXT,
            show_in_menu INTEGER NOT NULL DEFAULT 0,
            parent_id  INTEGER REFERENCES categories(id) ON DELETE SET NULL,
            position   INTEGER NOT NULL DEFAULT 0,
            created_at TEXT    DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS products (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            sku           TEXT    UNIQUE,
            title         TEXT    NOT NULL,
            slug          TEXT    NOT NULL UNIQUE,
            description   TEXT,
            history       TEXT,
            category_id   INTEGER REFERENCES categories(id) ON DELETE SET NULL,
            type          TEXT    NOT NULL DEFAULT 'venta'
                          CHECK (type IN ('venta','alquiler')),
            price         REAL,
            price_visible INTEGER NOT NULL DEFAULT 1,
            status        TEXT    NOT NULL DEFAULT 'activo'
                          CHECK (status IN ('activo','reservado','vendido')),
            is_featured   INTEGER NOT NULL DEFAULT 0,
            rental_only   INTEGER NOT NULL DEFAULT 0,
            style         TEXT,
            era           TEXT,
            material      TEXT,
            dimensions    TEXT,
            origin        TEXT,
            condition_val TEXT,
            pickup_available INTEGER NOT NULL DEFAULT 0,
            shipping_transport INTEGER NOT NULL DEFAULT 0,
            shipping_flete INTEGER NOT NULL DEFAULT 0,
            shipping_encomienda INTEGER NOT NULL DEFAULT 0,
            created_at    TEXT    DEFAULT (datetime('now')),
            updated_at    TEXT    DEFAULT (datetime('now'))
        );

        CREATE INDEX IF NOT EXISTS idx_products_cat    ON products(category_id, status);
        CREATE INDEX IF NOT EXISTS idx_products_status ON products(status);

        CREATE TABLE IF NOT EXISTS product_images (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id  INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            hash        TEXT    NOT NULL,
            path_thumb  TEXT,
            path_medium TEXT,
            path_full   TEXT,
            position    INTEGER NOT NULL DEFAULT 0,
            is_cover    INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT    DEFAULT (datetime('now'))
        );
        CREATE INDEX IF NOT EXISTS idx_images_product ON product_images(product_id, is_cover, position);

        CREATE TABLE IF NOT EXISTS upload_temp (
            token       TEXT PRIMARY KEY,
            session_id  TEXT NOT NULL,
            path_orig   TEXT NOT NULL,
            path_thumb  TEXT,
            created_at  TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS product_categories (
            product_id  INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
            position    INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (product_id, category_id)
        );

        CREATE TABLE IF NOT EXISTS site_settings (
            setting_key   TEXT PRIMARY KEY,
            setting_value TEXT,
            updated_at    TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS home_hero_slides (
            id                         INTEGER PRIMARY KEY AUTOINCREMENT,
            image_path                 TEXT NOT NULL,
            link_url                   TEXT,
            tag                        TEXT,
            title                      TEXT NOT NULL,
            description                TEXT,
            button_primary_text        TEXT,
            button_primary_link        TEXT,
            button_secondary_text      TEXT,
            button_secondary_link      TEXT,
            position                   INTEGER NOT NULL DEFAULT 0,
            is_active                  INTEGER NOT NULL DEFAULT 1,
            created_at                 TEXT DEFAULT (datetime('now')),
            updated_at                 TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS home_service_blocks (
            id                         INTEGER PRIMARY KEY AUTOINCREMENT,
            image_path                 TEXT NOT NULL,
            link_url                   TEXT,
            title                      TEXT NOT NULL,
            description                TEXT,
            style_key                  TEXT NOT NULL DEFAULT 'default',
            position                   INTEGER NOT NULL DEFAULT 0,
            is_active                  INTEGER NOT NULL DEFAULT 1,
            created_at                 TEXT DEFAULT (datetime('now')),
            updated_at                 TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS site_popup (
            id                         INTEGER PRIMARY KEY CHECK (id = 1),
            is_active                  INTEGER NOT NULL DEFAULT 0,
            frequency                  TEXT NOT NULL DEFAULT 'daily',
            image_path                 TEXT,
            title                      TEXT,
            description                TEXT,
            cta_text                   TEXT,
            cta_link                   TEXT,
            created_at                 TEXT DEFAULT (datetime('now')),
            updated_at                 TEXT DEFAULT (datetime('now'))
        );
    ");

    db_add_column_if_missing($pdo, 'categories', 'icon', 'TEXT');
    db_add_column_if_missing($pdo, 'categories', 'cover_path', 'TEXT');
    db_add_column_if_missing($pdo, 'categories', 'description', 'TEXT');
    db_add_column_if_missing($pdo, 'categories', 'show_in_menu', 'INTEGER NOT NULL DEFAULT 0');
    db_migrate_products_schema($pdo);
    db_add_column_if_missing($pdo, 'products', 'rental_only', 'INTEGER NOT NULL DEFAULT 0');
    db_add_column_if_missing($pdo, 'products', 'pickup_available', 'INTEGER NOT NULL DEFAULT 0');
    db_add_column_if_missing($pdo, 'products', 'shipping_transport', 'INTEGER NOT NULL DEFAULT 0');
    db_add_column_if_missing($pdo, 'products', 'shipping_flete', 'INTEGER NOT NULL DEFAULT 0');
    db_add_column_if_missing($pdo, 'products', 'shipping_encomienda', 'INTEGER NOT NULL DEFAULT 0');
    db_add_column_if_missing($pdo, 'product_categories', 'position', 'INTEGER NOT NULL DEFAULT 0');
    db_add_column_if_missing($pdo, 'home_service_blocks', 'link_url', 'TEXT');

    // Seed categorías por defecto si la tabla está vacía
    $count = $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ((int)$count === 0) {
        $cats = [
            ['Dormitorio','dormitorio'],['Comedor','comedor'],
            ['Escritorio','escritorio'],['Living','living'],
            ['Iluminación','iluminacion'],['Juegos y Deportes','juegos-deportes'],
            ['Decoración','decoracion'],['Exterior','exterior'],
            ['Mid Century','mid-century'],['Cuadros y Arte','cuadros-arte'],
            ['Art. Barbería','art-barberia'],['Carteles','carteles'],
            ['Oficios y Herrería','oficios-herreria'],['Varios','varios'],
        ];
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, position) VALUES (?, ?, ?)");
        foreach ($cats as $i => [$name, $slug]) {
            $stmt->execute([$name, $slug, $i]);
        }
    }

    $serviceCount = (int)$pdo->query('SELECT COUNT(*) FROM home_service_blocks')->fetchColumn();
    if ($serviceCount === 0) {
        $services = [
            ['assets/site/alquileres.png', '/alquileres', 'Alquileres', 'Muebles y objetos para producciones, eventos, vidrieras y ambientaciones.', 'rent', 0],
            ['assets/site/venta.png', '/compra-venta', 'Compra y venta', 'Tasacion sin cargo y piezas seleccionadas para sumar caracter a cada ambiente.', 'buy', 1],
            ['assets/site/restauraciones.png', '/restauraciones', 'Restauraciones', 'Lustre, retapizados, esterillados y trabajos artesanales con criterio de epoca.', 'restore', 2],
        ];
        $stmt = $pdo->prepare("
            INSERT INTO home_service_blocks (
                image_path, link_url, title, description, style_key, position, is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, datetime('now'), datetime('now'))
        ");
        foreach ($services as $service) {
            $stmt->execute($service);
        }
    }

    $serviceLinkDefaults = [
        'rent' => '/alquileres',
        'buy' => '/compra-venta',
        'restore' => '/restauraciones',
    ];
    $stmt = $pdo->prepare("UPDATE home_service_blocks SET link_url = ? WHERE style_key = ? AND (link_url IS NULL OR link_url = '')");
    foreach ($serviceLinkDefaults as $styleKey => $linkUrl) {
        $stmt->execute([$linkUrl, $styleKey]);
    }

    $popupCount = (int)$pdo->query('SELECT COUNT(*) FROM site_popup WHERE id = 1')->fetchColumn();
    if ($popupCount === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO site_popup (
                id, is_active, frequency, title, description, cta_text, cta_link, created_at, updated_at
            ) VALUES (1, 0, 'daily', '', '', '', '', datetime('now'), datetime('now'))
        ");
        $stmt->execute();
    }
}

function db_migrate_products_schema(PDO $pdo): void
{
    $columns = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($columns)) {
        return;
    }

    $skuColumn = null;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'sku') {
            $skuColumn = $column;
            break;
        }
    }

    if (!$skuColumn) {
        return;
    }

    if ((int)($skuColumn['notnull'] ?? 0) === 0) {
        return;
    }

    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->beginTransaction();

    try {
        $pdo->exec("
            CREATE TABLE products_new (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                sku           TEXT    UNIQUE,
                title         TEXT    NOT NULL,
                slug          TEXT    NOT NULL UNIQUE,
                description   TEXT,
                history       TEXT,
                category_id   INTEGER REFERENCES categories(id) ON DELETE SET NULL,
                type          TEXT    NOT NULL DEFAULT 'venta'
                              CHECK (type IN ('venta','alquiler')),
                price         REAL,
                price_visible INTEGER NOT NULL DEFAULT 1,
                status        TEXT    NOT NULL DEFAULT 'activo'
                              CHECK (status IN ('activo','reservado','vendido')),
                is_featured   INTEGER NOT NULL DEFAULT 0,
                style         TEXT,
                era           TEXT,
                material      TEXT,
                dimensions    TEXT,
                origin        TEXT,
                condition_val TEXT,
                created_at    TEXT    DEFAULT (datetime('now')),
                updated_at    TEXT    DEFAULT (datetime('now'))
            );
        ");

        $pdo->exec("
            INSERT INTO products_new (
                id, sku, title, slug, description, history, category_id,
                type, price, price_visible, status, is_featured,
                style, era, material, dimensions, origin, condition_val,
                created_at, updated_at
            )
            SELECT
                id,
                NULLIF(sku, ''),
                title,
                slug,
                description,
                history,
                category_id,
                type,
                price,
                price_visible,
                status,
                is_featured,
                style,
                era,
                material,
                dimensions,
                origin,
                condition_val,
                created_at,
                updated_at
            FROM products
        ");

        $pdo->exec("DROP TABLE products");
        $pdo->exec("ALTER TABLE products_new RENAME TO products");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_cat ON products(category_id, status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_status ON products(status)");

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $pdo->exec('PRAGMA foreign_keys = ON');
        throw $e;
    }

    $pdo->exec('PRAGMA foreign_keys = ON');
}

function db_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    $columns = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        if (($col['name'] ?? null) === $column) {
            return;
        }
    }

    $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
}
