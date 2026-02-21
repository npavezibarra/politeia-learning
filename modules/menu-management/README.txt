Menu Management (Politeia Learning)
==================================

Este módulo existe para resolver una limitación común de los menús de WordPress:
WordPress permite crear links “Custom” en los menús, pero no permite que esos links
sean dinámicos según el usuario loggeado.

Ejemplo (caso real)
-------------------
En el menú principal necesitamos mantener los ítems existentes:
- Feed
- Cursos
- My Books

Y agregar un ítem adicional:
- Center

El link de "Center" debe apuntar a la página "center" de cada usuario:
http://nupoliteia.local/members/{username}/center

Donde {username} corresponde al user_login del usuario actualmente autenticado.

Implementación
--------------
El módulo agrega el ítem "Center" en el menú principal (y menú móvil logged-in),
sólo si el usuario está loggeado, generando la URL con home_url().

