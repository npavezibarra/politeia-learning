# Módulo: Payments Subscriptions (PPS)

El módulo `payments-subscriptions` integra suscripciones pagadas definidas por creadores (PPS), migradas desde el plugin standalone `politeia-payments-subscriptions`, en **WordPress puro**.

## Qué hace

- Define constantes y back‑compat (`PL_PPS_*` y `POLITEIA_PPS_*`) para reutilizar código migrado.
- Crea/actualiza tablas al activarse el módulo (upgrade en `plugins_loaded`).
- Provee:
  - Configuración (settings) y locale/moneda.
  - Engine de suscripciones.
  - Webhooks para proveedores de pago (según implementación en `class-webhooks.php`).
  - REST API para operaciones de PPS (`class-rest.php`).
  - UI de “suscribirse” desde perfil (orquestado por `class-profile-subscribe.php`).
- Registra assets frontend:
  - `assets/css/politeia-pps.css`
  - `assets/js/marketplace.js`
- Incluye shortcodes:
  - `shortcodes/creator-dashboard.php`
  - `shortcodes/subscriber-dashboard.php`
  - `shortcodes/marketplace.php`

## Puntos de entrada

- `init.php`: define constantes, evita doble carga si existe el plugin standalone, carga clases y registra hooks.

## Dependencias

- WordPress core (Options API, REST API, shortcodes, wp_enqueue_*).
- Proveedores de pago soportados por el código (por ejemplo Mercado Pago / Flow) según configuración.
