# Módulo: Learni (LMS interno)

`learni` es el motor LMS interno usado por Politeia Learning: define los post types del contenido educativo y provee persistencia (enrollments/progreso), endpoints REST y piezas de frontend para cursos/lecciones.

> Nota: este módulo se carga como “internal” y declara explícitamente que **no registra aún toda la capa de routing frontend** de Learni; la migración es incremental.

## Qué hace

- Registra post types: `learni_course`, `learni_lesson`, `learni_special` (legacy: `learni_specialization`), `learni_program`.
- Asegura esquema/upgrade de base de datos vía `Learni\\Database\\Installer`.
- Expone REST routes vía `Learni\\Rest\\Routes::register`.
- Integra con WooCommerce (inicialización condicional) y sincronización de cursos/productos.
- Inicializa el “Dashboard” migrado (clases `PL_CC_*` en `includes/Dashboard/`).
- Incluye un “Quiz Editor” modular (repositorio/editor/permisos/parser + AJAX + shortcode).
- Mantiene un flujo legacy de “quiz creator” (UI) que facilita generar/importar preguntas vía copy/paste de prompts (por ejemplo “ChatGPT/Claude”) desde `assets/` + `templates/quiz-creator/`.

## Puntos de entrada

- `init.php`: define constantes `PL_LEARNI_*` / `LEARNI_*`, carga clases principales y ejecuta `PL_Learni_Module::init()`.
- Clase orquestadora: `PL_Learni_Module` (hooks en `plugins_loaded`, `init`, `rest_api_init`, `wp_enqueue_scripts`).

## Estructura útil

- `includes/Database/`: instalación y tablas de enrollments/progreso.
- `includes/PostTypes/`: registro de cursos/lecciones/programas.
- `includes/Frontend/`: templates/acciones/certificados/vistas.
- `includes/Rest/`: rutas REST.
- `includes/WooCommerce/`: integración + sync.
- `includes/QuizEditor/`: editor de evaluaciones.

## Compatibilidad (no requerida)

- El dashboard incluye rutas/plantillas propias y no depende de integraciones externas de comunidad/perfiles para funcionar.
