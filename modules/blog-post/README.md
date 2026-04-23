# Módulo: Blog Post (Escritos)

El módulo `blog-post` personaliza la presentación de los posts estándar de WordPress (en Politeia, “Escritos”) para una lectura más cuidada y consistente con el look & feel del resto de la plataforma.

## Qué hace

- Reemplaza el template single de posts por `templates/single-post.php` (sin afectar otros post types).
- Encola estilos/recursos propios del módulo para controlar tipografía, anchos de lectura y espaciado.

## Puntos de entrada

- `init.php`: registra autoloader `PL_BP_*` e instancia `PL_BP_Blog_Post_Template` en `plugins_loaded`.

## Archivos relevantes

- `assets/`: CSS/JS del módulo (estilos de lectura).
- `templates/single-post.php`: layout de post.
