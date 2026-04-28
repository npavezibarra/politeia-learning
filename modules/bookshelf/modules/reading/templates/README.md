# Politeia Reading — Templates

Contiene los archivos de vista (HTML/PHP) que definen la estructura visual de los componentes de lectura.

## 🖼️ Vistas Principales

- `my-books.php`: El "Bookshelf" o estantería global del usuario.
- `single-book.php`: La página de detalle de un libro específico (Ficha técnica).
- `start-reading.php`: Interfaz del cronómetro y control de sesión activa.

## 🧩 Partials (Fragmentos)
- `book-card.php`: Representación individual de un libro en listas.
- `reading-stats.php`: Visualizaciones de datos y progreso.
- `notes-feed.php`: El feed de notas de lectura.

## 💡 Uso
Estos archivos son llamados por los controladores en `includes/` (especialmente `class-prs-ui-renderer.php`) para renderizar el contenido de los shortcodes.
