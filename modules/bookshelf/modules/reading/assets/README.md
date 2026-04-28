# Politeia Reading — Assets

Este directorio contiene los recursos estáticos (CSS y JS) que dan vida a la interfaz de usuario de Politeia Reading.

## 🏗️ Arquitectura de Scripts (Regla Madre)

Siguiendo nuestra arquitectura modular, los scripts principales han sido desintegrados para mantener archivos de menos de 500 líneas:

### 📁 `js/add-book/`
Contiene la lógica para el flujo de búsqueda y adición de libros.
- `main.js`: Inicializador.
- `api.js`: Comunicación con el backend.
- `ui.js`: Manipulación del DOM.

### 📁 `js/my-book/`
Contiene la lógica de la página individual del libro y la biblioteca, dividida en 11 fragmentos (`part-00.js` a `part-10.js`) para garantizar estabilidad y mantenibilidad.

### 📁 `js/start-reading/`
Lógica del cronómetro y registro de sesiones, dividida en fragmentos secuenciales.

## 🎨 Estilos (CSS)
- `politeia.css`: Estilos base compartidos.
- `my-book.css`: Diseño de la ficha técnica del libro y biblioteca.
- `notes-feed.css`: Estilos para el muro de notas y actividades.

## 📝 Nota sobre Desarrollo
Cualquier cambio en la lógica de cliente debe hacerse en sus respectivos módulos dentro de las carpetas específicas. No se deben crear archivos monolíticos nuevos.
