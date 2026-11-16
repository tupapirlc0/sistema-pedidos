# Sistema de pedidos por zona · Order management system

Sistema web para distribuidoras: catálogo público, pedidos por WhatsApp y un panel de administración con ventas, stock y clientes. Esta es la versión demo, con una marca inventada (*Distribuidora Demo*) y datos de ejemplo.

**Demo en vivo:** https://comerciointegral.com/demo-pedidos

## Funciones

**Sitio público**
- Catálogo de productos con fotos, precios y disponibilidad.
- Carrito y pedido que llega al vendedor de la zona por WhatsApp.

**Panel de administración**
- Pedidos: estados, detalle e impresión de comprobantes.
- Productos, precios, stock e inventario.
- Clientes y su historial de compras.
- Ventas y analítica (eventos del sitio, productos más pedidos).
- Usuarios con roles: administrador, socio, ventas y operador.
- Exportación de datos y contenido del sitio editable sin tocar código.

## Tecnología

PHP 8 (sin framework, `strict_types`) · MySQL / PDO con consultas preparadas · JavaScript · HTML y CSS · contraseñas con Argon2id · Apache (`.htaccess`).

## Instalación

1. Crea una base de datos MySQL e importa `instalacion/demo-pedidos.sql`.
2. Copia `config.ejemplo.php` como `config.php` y escribe los datos de la base.
3. Sube los archivos a un servidor con PHP 8 y Apache.

Fotos libres de uso (CC0) de StockSnap y rawpixel; detalle en `instalacion/creditos-fotos.json`.

---

### English

Web app for distributors: public product catalog, orders routed to each zone's sales rep via WhatsApp, and an admin panel for orders, products, pricing, stock, customers, sales analytics and role-based users. Built with PHP 8, MySQL (PDO, prepared statements), JavaScript, HTML and CSS. Live demo: https://comerciointegral.com/demo-pedidos

---

Desarrollado por **Victor Ortiz** · [Comercio Integral](https://comerciointegral.com)
