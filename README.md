# Politeia Learning (Plugin)

Plugin principal de funcionalidades educativas de Politeia: LMS interno, dashboard de creadores, navegación, perfiles y extensiones de comercio.

## Para agentes (dónde partir)

- Reglas operativas y convenciones: `AGENTS.md`.
- Bootstrap del plugin: `politeia-learning.php`.
- La lógica vive en módulos: `modules/<module>/init.php`.
- Cada módulo documenta su propósito/entrypoints en: `modules/<module>/README.md`.

## Arquitectura

El plugin sigue una arquitectura modular: la mayoría de las funcionalidades viven en `modules/` y se inicializan desde sus respectivos `init.php`.

### Módulos

- `core`: configuración global + UI de administración.
- `learni`: motor LMS interno (post types, DB, REST, dashboard y quiz editor).
- `login-register`: autenticación/registro en frontend con modales.
- `navigation`: navegación unificada (desktop + mobile) y overrides de menú.
- `member-profile`: perfiles públicos `/profile/{username}` + portfolio.
- `payments-subscriptions`: suscripciones pagadas (PPS) + marketplace/shortcodes.
- `email-log`: log de correos enviados (tabla + UI admin).
- `under-construction`: modo under construction (toggle + allowlist admin/editor).
- `woo`: ajustes/extensiones WooCommerce (métricas, templates, emails, etc.).
