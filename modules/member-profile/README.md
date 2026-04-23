# Módulo: Member Profile

El módulo `member-profile` implementa perfiles públicos de miembros en **WordPress puro**, con una ruta propia `/profile/{username}` y plantillas dedicadas.

## Qué hace

- Registra una ruta pública:
  - URL: `/profile/{username}`
  - Query vars: `pl_profile_username`, `pl_profile_user_id`
- Intercambia el template en frontend cuando la URL coincide, usando `template_include`.
- Resuelve el usuario desde `{username}` (por `slug` o `login`), setea `pl_profile_user_id` y fuerza status 200 (evita 404).
- Bloquea la visualización si existe `PL_Relationships` y el viewer está bloqueado por el perfil (retorna 404).
- Administra configuración de “Portfolio” del usuario vía AJAX (visibilidad, selección de items por sección) usando una tabla:
  - `{$wpdb->prefix}politeia_portfolio_settings`
- Encola assets mínimos necesarios para la ruta de perfil (por ejemplo iconografía de Material Symbols) usando `wp_enqueue_*`.

## Puntos de entrada

- `init.php`: autoloader `PL_Member_Profile_*`, registra `PL_Member_Profile_Public_Route`, `PL_Member_Profile_Template` y `PL_Member_Profile_Portfolio_Manager`.
- `includes/class-public-route.php`: rewrite rule + query vars para `/profile/{username}`.
- `includes/class-template.php`: selección de template según `pcg_profile_template`.
- `includes/class-portfolio-manager.php`: endpoints AJAX de portfolio.

## Notas

- Si se habilita este módulo en un sitio ya existente, puede requerir flush de rewrite rules para activar la ruta `/profile/{username}`.
- Las plantillas están pensadas para funcionar sin dependencias externas de comunidad/perfiles.
