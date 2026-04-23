# Arquitectura del Backend: Course Creator (Learni Native)

Esta carpeta contiene la lógica del servidor para el módulo **Course Creator**. Siguiendo los principios de código limpio y mantenibilidad, la arquitectura ha sido modularizada para evitar los "Objetos Dios" y garantizar que ningún archivo supere las 500 líneas de código.

## Estructura de Archivos

El backend se organiza en **Orquestadores** (Clases principales) y **Módulos de Lógica** (Traits).

### 1. Orquestadores (Puntos de Entrada)
Son las clases que WordPress reconoce para hooks de AJAX, rutas y renderizado.

*   **`class-course-save-handler.php`**: El motor principal de guardado. Registra todos los endpoints de AJAX para cursos, especializaciones, programas y media.
*   **`class-creator-dashboard.php`**: Gestiona las reglas de reescritura de URL (`/members/{user}/center`), el renderizado de plantillas y el control de acceso al dashboard.
*   **`class-inclusion-approvals.php`**: Orquesta el flujo de aprobaciones multi-autor para contenidos compartidos.

### 2. Capa de Lógica (`/traits/`)
Toda la lógica pesada ha sido extraída a **Traits** para mantener los archivos principales ligeros y fáciles de auditar.

#### Gestión de Contenidos (Save Handler)
*   `trait-save-course.php`: Creación de cursos y gestión de la malla curricular (lecciones/secciones).
*   `trait-save-specialization.php`: Gestión de especializaciones y sistema de Snapshots.
*   `trait-save-program.php`: Gestión de programas académicos (agrupación de especializaciones).
*   `trait-save-escritos.php`: CRUD de artículos/posts del blog integrados en lecciones.

#### Integraciones y Utilidades
*   `trait-save-woo-sync.php`: Sincronización 1:1 con productos de WooCommerce (precios, categorías, dueños).
*   `trait-save-taxonomy-roles.php`: Manejo de categorías/etiquetas de Learni y roles de colaboradores (Partnerships).
*   `trait-save-media-profile.php`: Procesamiento de imágenes (Cropper) y avatares de perfil.
*   `trait-save-utils.php`: Lógica transversal de permisos, normalización de datos y seguridad.

#### Presentación y Lógica Interna
*   `trait-dashboard-assets.php`: Encolado de scripts, estilos y las extensas traducciones (`wp_localize_script`) del dashboard.
*   `trait-approvals-logic.php`: Operaciones de base de datos (`wpdb`) y algoritmos de rebalanceo de porcentajes para aprobaciones.

## Estándares de Desarrollo (La Regla de las 500 Líneas)

Para mantener la calidad del motor de **Learni**, se deben seguir estas reglas en futuras expansiones:

1.  **Límite de Líneas**: Ningún archivo PHP debe exceder las **500 líneas**. Si una funcionalidad crece más allá de este punto, debe fragmentarse en un nuevo Trait o clase especializada.
2.  **Nativización total**: No usar lógica ni comentarios residuales de integraciones legacy. Toda la interacción con la base de datos debe usar las constantes nativas de `\Learni\PostTypes\...`.
3.  **Separación de Responsabilidades**: Las clases principales (Orquestadores) solo deben contener registros de hooks y delegación. La lógica de negocio debe residir en los Traits.

---
*Este sistema ha sido diseñado para ser escalable, rápido y 100% nativo de Politeia Learning.*
