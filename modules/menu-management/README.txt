Menu Management (Politeia Learning)
==================================

Este módulo existe para gestionar el menú principal desde el plugin (sin depender
de BuddyBoss Theme/Plugin) y para resolver una limitación común de los menús de
WordPress: los links “Custom” no pueden ser dinámicos según el usuario loggeado.

Ejemplo (caso real)
-------------------
Queremos que el menú principal contenga sólo nuestros ítems:
- Cursos
- My Books

Y agregar un ítem adicional:
- Center

El link de "Center" debe apuntar a la página "center" de cada usuario:
http://nupoliteia.local/members/{username}/center

Donde {username} corresponde al user_login del usuario actualmente autenticado.

Implementación
--------------
El módulo reemplaza los ítems del menú en los theme locations principales y
renderiza una lista controlada por el plugin, eliminando los ítems “por default”.

Si estás usando el Site Editor (bloque "Navegación" / core/navigation) con "Lista de páginas",
agrega la clase CSS `pl-managed-menu` al bloque de navegación para que el plugin
reemplace automáticamente sus ítems.

Incluye:
- Cursos: archive de LearnDash (sfwd-courses) o /courses como fallback
- My Books: /my-books
- Center: /members/{username}/{centerSlug} (sólo loggeado, centerSlug viene desde pcg_operation_template)
