<?php
// Actualizaciones de la base que se aplican solas al subir una versión nueva (no hace falta phpMyAdmin).
// Cada paso es idempotente: se puede volver a ejecutar sin romper nada. La versión queda en site_settings.
declare(strict_types=1);

const SCHEMA_VERSION = 4;

function ensure_schema(): void
{
    if ((int) (setting('schema_version') ?? 1) >= SCHEMA_VERSION) return;
    // Un solo proceso a la vez (dos visitas simultáneas no deben migrar dos veces)
    if ((int) val("SELECT GET_LOCK('acs_migrate', 10)") !== 1) return;
    try {
        if ((int) (settings(true)['schema_version'] ?? 1) < 2) migrate_v2();
        if ((int) (settings(true)['schema_version'] ?? 1) < 3) migrate_v3();
        if ((int) (settings(true)['schema_version'] ?? 1) < 4) migrate_v4();
        settings(true);
    } finally {
        q("SELECT RELEASE_LOCK('acs_migrate')");
    }
}

function column_exists(string $table, string $column): bool
{
    return (bool) val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
}

// v2 · Jumbo, rol de socio, stock en maples con movimientos, ventas de los socios y rendición de cuentas
function migrate_v2(): void
{
    $pdo = db();
    // Categoría Jumbo
    $pdo->exec("ALTER TABLE products MODIFY grade ENUM('jumbo','primera','segunda','tercera','cuarta','quinta') NOT NULL");
    if (!val("SELECT id FROM products WHERE slug = 'jumbo'")) {
        q("UPDATE products SET sort_order = sort_order + 1 WHERE deleted_at IS NULL");
        q("INSERT INTO products (slug, name, grade, description, badge, sort_order, status) VALUES ('jumbo', 'Jumbo', 'jumbo', 'Más de 75 g, doble yema.', 'Doble yema', 0, 'active')");
        $pid = (int) $pdo->lastInsertId();
        foreach ([['maple', 30, 'Maple · 30 huevos', 1], ['paquete', 300, 'Paquete · 10 maples (300 huevos)', 2]] as [$pres, $units, $label, $sort]) {
            q('INSERT INTO product_variants (product_id, presentation, units, label, sort_order) VALUES (?, ?, ?, ?, ?)', [$pid, $pres, $units, $label, $sort]);
            q("INSERT INTO inventory_status (variant_id, status) VALUES (?, 'por_confirmar')", [(int) $pdo->lastInsertId()]);
        }
    }

    // Rol de socio: vende desde su zona, registra sus retiros y ventas, y ve el resumen de todos
    q("INSERT IGNORE INTO roles (id, name, description) VALUES ('socio', 'Socio', 'Registra ingresos de stock, retiros y ventas de su zona, y ve el resumen del negocio.')");
    if (!column_exists('profiles', 'zone_id')) {
        $pdo->exec('ALTER TABLE profiles ADD zone_id INT UNSIGNED NULL AFTER role_id, ADD CONSTRAINT profiles_zone_fk FOREIGN KEY (zone_id) REFERENCES zones (id) ON DELETE SET NULL');
    }

    // Movimientos de stock en maples. holder_id NULL = almacén central (Zona Sur); si no, lo que tiene un socio para vender.
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_movements (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        product_id INT UNSIGNED NOT NULL,
        holder_id  INT UNSIGNED NULL,
        maples     INT NOT NULL,
        type       ENUM('ingreso','retiro','devolucion','venta','ajuste') NOT NULL,
        batch      CHAR(16) NULL,
        sale_id    INT UNSIGNED NULL,
        note       VARCHAR(200) NOT NULL DEFAULT '',
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY stock_product_holder (product_id, holder_id),
        KEY stock_created (created_at),
        KEY stock_batch (batch),
        CONSTRAINT stock_product_fk FOREIGN KEY (product_id) REFERENCES products (id),
        CONSTRAINT stock_holder_fk FOREIGN KEY (holder_id) REFERENCES profiles (id),
        CONSTRAINT stock_created_fk FOREIGN KEY (created_by) REFERENCES profiles (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Ventas que registra cada socio (en la calle, en su puesto o desde el almacén)
    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_sales (
        id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        seller_id     INT UNSIGNED NOT NULL,
        product_id    INT UNSIGNED NOT NULL,
        presentation  ENUM('maple','paquete') NOT NULL,
        quantity      INT UNSIGNED NOT NULL,
        maples        INT UNSIGNED NOT NULL,
        unit_price    DECIMAL(10,2) NOT NULL,
        total         DECIMAL(12,2) NOT NULL,
        paid          TINYINT(1) NOT NULL DEFAULT 1,
        from_central  TINYINT(1) NOT NULL DEFAULT 0,
        customer_name VARCHAR(80) NOT NULL DEFAULT '',
        note          VARCHAR(200) NOT NULL DEFAULT '',
        created_by    INT UNSIGNED NULL,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at    DATETIME NULL,
        KEY sales_seller (seller_id, created_at),
        KEY sales_created (created_at),
        CONSTRAINT sales_seller_fk FOREIGN KEY (seller_id) REFERENCES profiles (id),
        CONSTRAINT sales_product_fk FOREIGN KEY (product_id) REFERENCES products (id),
        CONSTRAINT sales_created_fk FOREIGN KEY (created_by) REFERENCES profiles (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Rendición de cuentas: dinero que cada socio entrega
    $pdo->exec("CREATE TABLE IF NOT EXISTS settlements (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        seller_id  INT UNSIGNED NOT NULL,
        amount     DECIMAL(12,2) NOT NULL,
        note       VARCHAR(200) NOT NULL DEFAULT '',
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        KEY settle_seller (seller_id, created_at),
        CONSTRAINT settle_seller_fk FOREIGN KEY (seller_id) REFERENCES profiles (id),
        CONSTRAINT settle_created_fk FOREIGN KEY (created_by) REFERENCES profiles (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Textos de la página: ahora son seis categorías, con Jumbo
    foreach (['draft', 'published'] as $col) {
        q("UPDATE content_sections SET $col = REPLACE(REPLACE(REPLACE($col,
            'Ofrecemos cinco categorías, de primera a quinta,', 'Ofrecemos seis categorías, de Jumbo (doble yema) a quinta,'),
            'en categorías de primera a quinta', 'en categorías de Jumbo a quinta'),
            'Ofrecemos cinco categorías', 'Ofrecemos seis categorías')");
    }

    foreach ([
        'stock_auto' => true,              // la disponibilidad de la página sale del stock real
        'stock_low_maples' => 30,          // con menos de esto se muestra "Poco stock"
        'package_discount' => 20,          // paquete = 10 maples − este descuento (Bs)
        'stock_location' => 'Zona Sur',    // dónde está el almacén
    ] as $k => $v) q('INSERT IGNORE INTO site_settings (`key`, value) VALUES (?, ?)', [$k, json_encode($v, JSON_UNESCAPED_UNICODE)]);
    // 3 socios + 3 responsables de ventas
    if ((int) setting('max_users', 3) < 6) save_setting('max_users', 6, null);
    save_setting('schema_version', 2, null);
}

// v3 · Ingreso con usuario o celular (el correo pasa a ser opcional)
function migrate_v3(): void
{
    $pdo = db();
    if (!column_exists('profiles', 'username')) {
        $pdo->exec('ALTER TABLE profiles MODIFY email VARCHAR(160) NULL,
            ADD username VARCHAR(40) NULL AFTER email, ADD phone VARCHAR(15) NULL AFTER username,
            ADD UNIQUE KEY profiles_username (username), ADD UNIQUE KEY profiles_phone (phone)');
    }
    save_setting('schema_version', 3, null);
}

// v4 · Cuatro estados de pedido, cada pedido enlazado al vendedor de su zona y entregas que suman a las ventas
function migrate_v4(): void
{
    $pdo = db();
    if (!column_exists('partner_sales', 'order_id')) {
        $pdo->exec('ALTER TABLE partner_sales ADD order_id INT UNSIGNED NULL AFTER note, ADD KEY sales_order (order_id),
            ADD CONSTRAINT sales_order_fk FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL');
    }
    q("UPDATE orders SET status = 'nuevo' WHERE status = 'pendiente_whatsapp'");
    q("UPDATE orders SET status = 'confirmado' WHERE status IN ('preparando', 'listo')");
    save_setting('zone_routing_enabled', true, null);
    q("UPDATE business_contacts SET role_label = REPLACE(role_label, 'Apoyo ', 'Pedidos ') WHERE role_label LIKE 'Apoyo %'");
    // Ana Cintia atiende la Zona Norte (sigue como administradora mientras revisa el sistema)
    q("UPDATE profiles SET zone_id = (SELECT id FROM zones WHERE slug = 'norte') WHERE username = 'anacintia' AND zone_id IS NULL");
    // Los pedidos que ya existían quedan con el vendedor de su zona
    q("UPDATE orders o JOIN profiles p ON p.zone_id = o.zone_id AND p.is_active = 1 AND p.deleted_at IS NULL SET o.assigned_to = p.id WHERE o.assigned_to IS NULL AND o.zone_id IS NOT NULL");
    save_setting('schema_version', 4, null);
}
