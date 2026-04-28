# Politeia Bookshelf — Modules

Esta carpeta contiene los módulos core que componen el ecosistema de aprendizaje y lectura de Politeia. Cada subdirectorio es un módulo independiente pero interconectado.

## 📦 Módulos Incluidos

### 1. 📖 [Reading](./reading)
El motor principal de gestión de libros. Maneja el catálogo canónico, las bibliotecas personales de los usuarios y el registro de sesiones de lectura con cronómetro y estadísticas.

### 2. 🤖 [ChatGPT](./chatgpt)
Integración con Inteligencia Artificial (OpenAI). Permite procesar el contenido de las lecturas, generar resúmenes y transcribir notas de voz mediante Whisper.

### 3. 👤 [User Baseline](./user-baseline)
Gestiona el estado inicial y el perfil de los usuarios. Asegura que la infraestructura necesaria esté lista para que el usuario pueda usar los módulos de Reading e IA.

---
**Regla Madre**: Ningún archivo fuente (.php, .js) debe superar las 500 líneas de código. Si un archivo crece más allá de este límite, debe ser modularizado.
