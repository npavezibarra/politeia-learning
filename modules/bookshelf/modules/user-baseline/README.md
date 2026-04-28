# Politeia User Baseline

Este módulo se encarga de gestionar los datos base y el perfil inicial de los usuarios dentro del ecosistema Politeia. Su objetivo es asegurar que cada usuario tenga una configuración de inicio consistente para las herramientas de aprendizaje.

## 📋 Responsabilidades
- **Instalación Inicial**: Configuración de tablas y metadatos específicos del usuario al activar el plugin.
- **Gestión de Perfil**: Definición de parámetros básicos (baseline) para el seguimiento del progreso.
- **Helpers de Usuario**: Funciones de utilidad para recuperar información del perfil Politeia.

## 📂 Estructura
- `user-baseline.php`: Punto de entrada y hooks principales.
- `init.php`: Lógica de inicialización del módulo.
- `includes/`: Clases de soporte para instalación, activación y actualización.
  - `class-installer.php`: Maneja la creación de estructuras de datos necesarias.
  - `helpers.php`: Colección de funciones globales para uso en otros módulos.
