# Politeia ChatGPT

Este módulo integra las capacidades de inteligencia artificial de OpenAI dentro del ecosistema Politeia. Permite la interacción con modelos de lenguaje (GPT) y servicios de transcripción (Whisper) para mejorar la experiencia de aprendizaje y gestión de contenidos.

## 🚀 Funcionalidades Principales
- **Interacción con GPT**: Procesamiento de texto y generación de respuestas inteligentes.
- **Transcripción con Whisper**: Conversión de audio a texto para notas y sesiones.
- **API Integrada**: Clase controladora para peticiones centralizadas a OpenAI.
- **Shortcodes**: Componentes listos para usar en el frontend de WordPress.

## 📂 Estructura del Módulo
- `politeia-chatgpt-api.php`: Maneja la comunicación core con la API de OpenAI.
- `politeia-chatgpt-whisper.php`: Lógica específica para el procesamiento de audio.
- `politeia-chatgpt-shortcode.php`: Implementación de los bloques de interfaz de usuario.
- `modules/`: Extensiones y sub-módulos adicionales de IA.
- `js/`: Lógica de cliente para la interacción en tiempo real.

## ⚙️ Requisitos
- Una API Key válida de OpenAI configurada en el sistema.
- PHP 7.4+ y soporte para cURL.
