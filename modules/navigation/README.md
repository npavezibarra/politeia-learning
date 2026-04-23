# Módulo: Navigation

El módulo `navigation` implementa un sistema de navegación unificado (desktop + mobile) y “toma control” de menús WordPress para asegurar consistencia visual y funcional en el frontend de Politeia Learning.

## Qué hace

- Encola assets compartidos:
  - `assets/css/navigation.css`
  - `assets/js/navigation.js`
- Inyecta estilos de emergencia en `wp_head` para ocultar menús “default” que rompen el layout y forzar la visibilidad del menú gestionado por el módulo.
- Override de navegación clásica (menús tradicionales):
  - `wp_nav_menu_args` (fallback)
  - `pre_wp_nav_menu` (short‑circuit del HTML)
  - `wp_nav_menu_items` (reemplazo/append items)
- Override de navegación Gutenberg:
  - `render_block_core/navigation`
- Render móvil:
  - `wp_body_open` (header mobile)

## Puntos de extensión (para otros módulos)

- Filtro para inyectar items de menú: `pl_navigation_menu_items`
- Filtro para breadcrumb: `pl_navigation_breadcrumb`
- Items dropdown usuario: `pl_navigation_user_dropdown_items`

## Puntos de entrada

- `init.php`: define `PL_NAV_PATH`/`PL_NAV_URL`, carga clases `Learni\\Navigation\\*` y crea `NavOrchestrator`.
- `includes/Navigation/NavEngine.php`: arma `menu_items`, dropdown y breadcrumb.
- `includes/Navigation/DesktopRenderer.php`: render del `<li>`/dropdown para desktop.
- `includes/Navigation/MobileRenderer.php` y `includes/Navigation/GutenbergRenderer.php`: render/filters para mobile y blocks.

## Dependencias y acoplamientos

- WordPress core (menus, blocks y hooks).
- Integra con el módulo `login-register` indirectamente: el botón “INGRESAR” se renderiza con atributos `data-pl-auth-*` para abrir el modal de auth cuando está presente.
