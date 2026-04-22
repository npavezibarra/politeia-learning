# Learni: El Motor LMS de Politeia

Learni es el núcleo unificado de gestión de aprendizaje de Politeia. Integra la experiencia del estudiante (LMS) con un dashboard premium para instructores (**Center-2**), gestionando el ciclo completo de formación: desde la creación de contenidos hasta la certificación.

## 🚀 Módulos Core

- **LMS Engine**: Control de cursos, lecciones (video/texto) y tracking de progreso.
- **QuizEditor (Nuevo)**: Motor de evaluaciones modular que reemplaza al antiguo `quiz-creator`.
    - **IA Assisted**: Integración nativa para generar preguntas vía LLM (ChatGPT/Claude).
    - **Multi-formato**: Importación masiva desde JSON, CSV, XML y texto plano.
    - **Real-time Editor**: Interfaz slide-based para edición rápida sin recargas.
- **Creator Dashboard (Dashboard/)**: Centro de comando para instructores que unifica la gestión de cursos, ventas y analíticas de estudiantes.
- **Evaluación Binomial**: Sistema de validación (Baseline vs Final) con reglas de cooldown y emisión de certificados por mérito.
- **Test Partner**: Sistema de evaluación cruzada entre pares para aprendizaje colaborativo.

## 🏗️ Arquitectura y Organización

El módulo sigue un diseño desacoplado orientado a dominios:

- **`includes/QuizEditor/`**: Lógica de exámenes (Repository, Editor, Parser, Permissions).
- **`includes/Dashboard/`**: Orquestación del Center-2 e interfaces de creación.
- **`includes/Database/`**: Capa de persistencia (Enrollments, Progress, Attempts).
- **`includes/Rest/`**: API modularizada por funcionalidades (Binomial, Certificates, Sales).
- **`assets/`**: Recursos estáticos (CSS/JS) organizados por sub-módulo.
- **`templates/`**: Vistas PHP modulares siguiendo la regla de <500 líneas.

## 📏 Reglas de Oro

1.  **Mantenibilidad**: Ningún archivo de lógica debe exceder las **500 líneas**. Refactorizar en sub-clases si es necesario.
2.  **Estética Premium**: Uso estricto de **Vanilla CSS**, tipografía moderna (Outfit/Inter) y micro-animaciones.
3.  **Namespace Único**: Todo nuevo código debe usar `Learni\` como namespace raíz.
4.  **I18n**: Uso mandatorio del textdomain `politeia-learning`.

## 🤝 Integraciones

- **WooCommerce**: Sincronización automática de cursos como productos.
- **BuddyBoss**: Integración de perfiles y navegación en el Center del miembro.
- **Politeia PPS**: Motor de suscripciones y tiers de membresía para creadores.

---
*Learni: Diseñado para escalar la educación con elegancia técnica.*
