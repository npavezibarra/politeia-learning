# Fase 10 — Bookshelf como módulo dentro de Politeia Learning

Fecha: 2026-04-26

Objetivo:
- `politeia-bookshelf` deja de ser “core paralelo” y pasa a ser un **shim**.
- La funcionalidad “Bookshelf” corre dentro de `politeia-learning` como módulo:
  - Reading
  - ChatGPT
  - User Baseline (requerido por Reading Planner para `create_user_baseline()`)
- Mantener compatibilidad de datos (mismas tablas/opts, sin cambios de esquema).

---

## Ownership toggle (flag)

En `wp-config.php`:
```php
define('PL_BOOKSHELF_MODULE_ENABLED', true);
```

---

## Implementación en Learning

Módulo:
- `app/public/wp-content/plugins/politeia-learning/modules/bookshelf/`

Bootstrap:
- `app/public/wp-content/plugins/politeia-learning/modules/bookshelf/bootstrap.php`
  - Gated por `PL_BOOKSHELF_MODULE_ENABLED`
  - Evita doble-boot si detecta constantes/clases ya cargadas
  - Carga:
    - `admin/google-books-settings.php` (API key helper usado por Reading/ChatGPT)
    - `bookshelf-admin.php` (menu admin)
    - `modules/user-baseline/*` (incluye `create_user_baseline()`)
    - `modules/reading/*`
    - `modules/chatgpt/*`

Nota:
- Se mantienen los mismos text domains preexistentes (`politeia-reading`, `politeia-chatgpt`, `politeia-bookshelf`).

---

## Bookshelf plugin como shim

Archivo:
- `app/public/wp-content/plugins/politeia-bookshelf/politeia-bookshelf.php`

Comportamiento:
- Si `PL_BOOKSHELF_MODULE_ENABLED === true` y Learning está activo (`PL_PATH` / `PL_Module_Loader`):
  - No bootea Reading/ChatGPT/UserBaseline
  - Solo mantiene shims legacy (por ejemplo el proxy de Reading Planner)

Se removió el código duplicado en Bookshelf:
- `politeia-bookshelf/modules/reading`
- `politeia-bookshelf/modules/chatgpt`
- `politeia-bookshelf/modules/user-baseline`
- `politeia-bookshelf/admin`
- `politeia-bookshelf/includes/template-helpers.php`

---

## Verificación rápida (manual)

1) “My Books” y flujos de Reading (assets + REST):
- Confirmar que se registran estilos/scripts y que funciona `/wp-json/politeia/v1/user-books/{id}`.

2) ChatGPT settings / book-detection:
- Confirmar que los endpoints/admin screens siguen cargando (sin duplicación).

3) Reading Planner:
- Confirmar que el acceptance path no rompe por `create_user_baseline()`.

