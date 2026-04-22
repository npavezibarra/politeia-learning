# Learni: El motor LMS de Politeia

Learni es el ecosistema central de gestión de aprendizaje (LMS) de Politeia. Este módulo integra de forma unificada la experiencia del estudiante (LMS) y la del instructor (**Creator Dashboard / Center-2**), gestionando programas, especializaciones, evaluaciones binomiales, certificados y partnerships.

## 🚀 Responsabilidades del Módulo

- **LMS Core**: Gestión de cursos, lecciones y tracking de progreso.
- **Evaluación Binomial**: Flujo de validación de conocimientos mediante Evaluación Inicial (baseline) y Final.
- **Test Partner (Cross Evaluation)**: Sistema único de evaluación mutua entre socios de estudio.
- **Creator Dashboard**: Interfaz premium para la gestión de contenidos, ventas y analíticas de estudiantes.
- **Certificación**: Motor de generación de certificados dinámicos basados en mérito y progreso.

---

## 🏗️ Arquitectura Técnica

El módulo sigue un diseño orientado a dominios (Domain-Driven Design) para garantizar que la lógica permanezca desacoplada y escalable.

### Estructura de Directorios (includes/)
- **`Access/`**: Reglas de negocio para el acceso y permisos.
- **`Dashboard/`**: (Anteriormente `course-creator`) Dashboard Center-2 para instructores.
- **`Database/`**: Abstracción de datos y persistencia (Enrollments, Progress, Attempts).
- **`PostTypes/`**: Definición de CPTs (Course, Quiz, Program).
- **`Rest/`**: API REST modularizada en controladores específicos:
    - `Binomial`: Lógica de estados y gating de quices.
    - `CrossEval`: Handshake y sesiones de evaluación compartida.
    - `Attempts`: Procesamiento de respuestas y cálculo de puntajes.
    - `Certificates`: Generación de metadatos y plantillas de certificación.

---

## 📏 Reglas de Oro del Desarrollo

Para asegurar la mantenibilidad a largo plazo, todo desarrollo en Learni debe adherirse a los siguientes estándares:

### 1. Regla de las 500 Líneas (Mandatoria)
- Ningún archivo de lógica o clase debe exceder las **500 líneas**.
- Si un archivo se acerca a este límite, **debe ser refactorizado** en sub-módulos o clases de dominio más pequeñas.

### 2. Estética Premium (Aesthetics First)
- Las interfaces deben usar **Vanilla CSS** con variables de diseño coherentes.
- Se priorizan micro-animaciones, modos oscuros/ligeros elegantes y una tipografía moderna (Inter/Outfit).
- **No usar Placeholders**: Todas las imágenes deben ser activos reales o generados específicamente para el contexto.

### 3. Modularidad REST
- Las rutas en `Routes.php` solo deben actuar como registro y delegación.
- La implementación reside siempre en la clase de dominio correspondiente dentro de `Rest/`.

---

## 🔄 Flujo de Evaluación Binomial

1. **Baseline**: El alumno rinde la **Evaluación Inicial** antes de comenzar.
2. **Progreso**: Completa el **100% de las lecciones**.
3. **Validación**: Rinde la **Evaluación Final**.
4. **Criterio de Éxito**: El certificado se emite **solo si** el puntaje final es mayor o igual al inicial.
5. **Cooldown**: Si falla (Final < Inicial), se aplica un bloqueo de **7 días** para fomentar el repaso.

---

## 🤝 Test Partner (Evaluación Cruzada)

Diseñado para fomentar el aprendizaje colaborativo:
- Un usuario inicia la sesión de evaluación para su partner.
- El partner recibe una notificación global en tiempo real.
- El examen es validado bajo la cuenta del partner evaluado, permitiendo que ambos obtengan sus certificados mediante la validación mutua.
