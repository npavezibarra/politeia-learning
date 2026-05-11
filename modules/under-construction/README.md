# Módulo: Under Construction

Permite activar/desactivar un modo "under construction" desde el dashboard de WordPress.

## Qué hace
- Agrega una página de administración para encender/apagar el modo.
- Cuando está activo, bloquea el frontend para usuarios no autorizados y muestra una página blanca con el logo al centro, un mensaje, y un botón **INGRESAR** que abre el login modal existente.
- Restringe el login (mientras el modo está activo) a roles `administrator` y `editor`.

## Entry point
- `modules/under-construction/init.php`

## Opciones
- `pl_under_construction_enabled` (bool)

