# Módulo: Email Log

El módulo `email-log` registra en base de datos los correos enviados por WordPress/WooCommerce y expone una UI de administración para revisar el histórico.

## Qué hace

- Crea (y migra si aplica) la tabla de logs:
  - Tabla actual: `{$wpdb->prefix}politeia_email_logs`
  - Tabla legacy migrada: `{$wpdb->prefix}pl_email_logs` (se renombra si existe).
- Captura datos de correos enviados con hooks de WordPress:
  - `wp_mail` (filtro): captura `to`, `subject`, `message`, `headers` y un `caller_file` best‑effort.
  - `wp_mail_succeeded` / `wp_mail_failed` (WP >= 5.9) o fallback `phpmailer_init` para versiones anteriores.
- Clasifica el email (tipo/plantilla) heurísticamente (por headers, subject, etc.) para facilitar el filtrado.
- Provee pantallas en admin para:
  - Listado/paginación/búsqueda de logs.
  - Pruebas/envío de correos (según templates en `templates/`).

## Puntos de entrada

- `init.php`: carga `PL_Email_Log_DB`, `PL_Email_Log_Manager` y ejecuta `PL_Email_Log_Manager::init()`.
- `includes/class-pl-email-log-manager.php`: orquesta captura, instalación de tabla y guardado.
- `includes/class-pl-email-log-db.php`: crea/consulta/inserta en la tabla.

## Notas operativas

- La tabla se asegura en `admin_init` y también en `init` (para correos disparados en frontend).
- La eliminación/limpieza de logs antiguos está implementada en `PL_Email_Log_DB::delete_old_logs()` (no se ejecuta automáticamente).
