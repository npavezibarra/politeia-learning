# Módulo: Core

El módulo `core` concentra la **configuración global** y la **UI de administración** del plugin Politeia Learning. Aquí viven los ajustes que el resto de módulos consulta para resolver rutas, plantillas y comportamiento general.

## Qué hace

- Registra el menú principal de administración de Politeia Learning (vía `PL_Core_Admin`).
- Guarda/lee opciones globales (WordPress Options API), por ejemplo:
  - Plantilla de perfil seleccionada (usada por `member-profile`).
  - Slug/base del dashboard/Center (usado por `navigation` y módulos de dashboard).
  - Flags de habilitación/visibilidad de secciones para distintos roles.
- Centraliza pantallas administrativas en `templates/` para mantener separada la lógica del markup.

## Puntos de entrada

- `init.php`: registra el autoloader `PL_Core_*` y crea `PL_Core_Admin` en `plugins_loaded`.

## Dependencias

- WordPress core (admin hooks + Options API).
- Otros módulos consumen sus opciones; por eso es el lugar “fuente de verdad” para settings globales.
