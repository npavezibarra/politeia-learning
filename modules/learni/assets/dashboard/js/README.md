# Arquitectura Javascript: Course Creator Dashboard

Este directorio contiene la lógica de frontend para el Dashboard de Creadores de Politeia Learning. Tras la refactorización integral, el sistema ha pasado de ser un archivo monolítico a una **arquitectura modular orientada a componentes y estado centralizado**.

## 🏗️ Organización de Archivos

### 1. Orquestador Principal
- `creator-dashboard.js`: Es el núcleo del sistema. Se encarga de:
  - Inicializar el estado global (`window.pcgCourseState`).
  - Gestionar la navegación principal entre modos del curso.
  - Orquestar el guardado y publicación del curso.
  - Observar y actualizar la checklist de progreso en tiempo real (con debounce).

### 2. Módulos Especializados (`/parts`)
Para mejorar la mantenibilidad, la lógica se ha dividido en módulos independientes cargados bajo demanda:

- **[_utils.js](file:///Users/nicolaspavez/Local%20Sites/nupoliteia/app/public/wp-content/plugins/politeia-learning/modules/course-creator/assets/js/parts/_utils.js)**: Ayudantes globales como traducciones (`t()`), formateo y el sistema de **Notificaciones Toast**.
- `_utils.js`: Ayudantes globales como traducciones (`t()`), formateo y el sistema de **Notificaciones Toast**.
- `_shared-logic.js`: Lógica compartida de metadatos (Categorías/Etiquetas) y gestión de Profesores/Colaboradores.
- `_ui-nav.js`: Lógica específica para la navegación móvil y drawers laterales.
- `_escritos.js`: Gestión completa del editor de artículos e imágenes inline.
- `_lessons.js`: Constructor de la malla curricular, lecciones y secciones.
- `_evaluation.js`: Integración reactiva con el motor de cuestionarios (PQC).
- `_media-handlers.js`: Controladores para la subida, previsualización y recorte de imágenes de portada y certificados.
- `_specializations.js`: Interfaz de creación de especializaciones.
- `_programs.js`: Interfaz de creación de programas.

### 3. Otros Dashboards
- `pcg-sales-dashboard.js`: Lógica de analíticas y reportes de ventas.
- `pcg-students-dashboard.js`: Gestión de inscripciones, rankings y progreso de alumnos.

---

## 💡 Conceptos Clave

### Estado Global (`pcgCourseState`)
Ubicado en `window.pcgCourseState`, es la **fuente única de verdad** para el curso que se está editando activamente.
```javascript
window.pcgCourseState = {
    id: 0,
    status: 'draft',
    permalink: '',
    thumbnailId: 0,
    coverId: 0,
    // ...
};
```

### Animaciones Premium
La navegación entre secciones utiliza clases de visibilidad (`is-visible`) coordinadas con CSS para transiciones suaves de opacidad y desplazamiento.

### Comunicaciones AJAX
Todas las llamadas se centralizan utilizando el objeto `pcgCreatorData` (localizado vía PHP) para garantizar el uso correcto de URLs y Nonces de seguridad.

---

## 🛠️ Guías de Desarrollo

### Regla de la Modularidad (500 Líneas)
Para mantener la salud del código a largo plazo, hemos establecido la siguiente directriz:
> [!TIP]
> **Si un archivo Javascript supera las 500 líneas de código**, se debe evaluar obligatoriamente su modularización. La lógica debe extraerse a un nuevo submódulo en la carpeta `/parts` o separarse en funciones de utilidad compartidas. Esta magnitud asegura que el código siga siendo legible, fácil de testear y mantenible para el equipo.

### Cómo añadir nuevas funcionalidades
1. Crear el nuevo archivo en `assets/js/parts/_funcionalidad.js`.
2. Registrar el nuevo archivo en `includes/class-creator-dashboard.php` dentro del método de encolado de scripts.
3. Utilizar el objeto global `window.pcgCourseState` para acceder a la información del curso actual.
4. Seguir el patrón de visibilidad `is-visible` para mantener la coherencia en las transiciones de UI.

---
> [!IMPORTANT]
> **Mantenimiento**: Al añadir nuevas pestañas o modos, asegúrese de registrar el nuevo contenedor con la clase `.pcg-mode-content` y gestionar su visibilidad mediante `addClass('is-visible')` para mantener la fluidez de la interfaz.
