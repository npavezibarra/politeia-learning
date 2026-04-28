# Politeia Reading — Includes

Esta carpeta contiene el "cerebro" del módulo de lectura. Aquí reside la lógica de negocio, los controladores de base de datos, los gestores de sesiones y los manejadores de AJAX.

## 🧠 Componentes Clave

### 📚 Gestión de Libros
- `class-books.php`: Controlador principal de la entidad Libro.
- `class-prs-book-repository.php`: Capa de acceso a datos para consultas complejas.
- `class-prs-author-manager.php`: Gestión de autores y taxonomías relacionadas.

### ⏱️ Sesiones de Lectura
- `class-reading-sessions-handler.php`: Lógica para iniciar, pausar y guardar sesiones.
- `class-reading-sessions-recorder.php`: Maneja el registro persistente del tiempo y progreso.
- `class-reading-sessions-stats.php`: Cálculos de velocidad, densidad y hábitos de lectura.

### 👥 Biblioteca de Usuario
- `class-user-books.php`: Vinculación entre usuarios y libros.
- `class-user-books-meta.php`: Gestión de metadatos (calificación, fechas de compra, etc.).
- `class-politeia-loan-manager.php`: Sistema de préstamos y estados de posesión.

### 🔌 Infraestructura y Assets
- `class-prs-my-book-assets.php` y `class-prs-start-reading-assets.php`: Controladores de activos (JS/CSS) modulares.
- `class-ajax-handler.php`: Punto de entrada central para peticiones asíncronas.
- `class-db-migrations.php`: Gestión de la estructura de tablas SQL.

## 📂 Subdirectorios
- `add-book/`: Lógica específica para el flujo de añadir libros al catálogo.
- `cover-upload/`: Sistema de gestión y subida de portadas.
- `migrations/`: Scripts SQL para actualizaciones de base de datos.
