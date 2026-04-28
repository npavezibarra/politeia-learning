# Politeia Reading — Shortcodes

En esta carpeta se definen los shortcodes que permiten inyectar las funcionalidades de lectura en cualquier página o post de WordPress.

## 🧱 Shortcodes Disponibles

- `[politeia_my_books]`: Renderiza la biblioteca personal del usuario.
- `[politeia_add_book]`: Muestra el buscador y formulario para añadir nuevos libros.
- `[politeia_start_reading]`: Inyecta el panel de control de sesiones de lectura (cronómetro).

## 🛠️ Implementación
Cada archivo aquí suele ser un wrapper que inicializa el controlador correspondiente y retorna el buffer de salida generado por los templates.
