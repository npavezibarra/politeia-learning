# Módulo Login-Register (Learni Auth)

Módulo avanzado de autenticación y registro para Politeia Learning. Este módulo implementa un flujo de usuario premium basado en modales dinámicos, verificación de correo electrónico y una arquitectura modular desacoplada.

## 🚀 Características principales
- **Modal All-in-One**: Interfaz única para Login, Registro y Recuperación de contraseña.
- **Arquitectura Modular**: Basada en el estándar Learni con namespace `Learni\Auth`.
- **Regla de Oro**: Ningún archivo supera las 500 líneas de código para máxima mantenibilidad.
- **Verificación de Email**: Bloqueo opcional de funcionalidades para usuarios no verificados con popup de reenvío.
- **Assets Desacoplados**: CSS y JS externos (sin scripts inline) con carga condicional.
- **SEO & UX**: URLs amigables para modales (`?pl_auth_view=login`) y manejo inteligente de redirecciones.

## 🏗️ Estructura del Módulo
El módulo se organiza bajo el namespace `Learni\Auth` y sigue una estructura de responsabilidades claras:

```text
login-register/
├── assets/                # CSS y JS optimizados
│   ├── css/               # Estilos modulares (modal, popup)
│   └── js/                # Lógica frontend (jQuery + Fetch API)
├── includes/
│   └── Auth/
│       ├── Handlers/      # Lógica de negocio (Login, Register, Email, Pass)
│       ├── UI/            # Renderizado de componentes
│       ├── Utilities/     # Funciones auxiliares y URLs
│       ├── AuthOrchestrator.php # Cerebro del módulo
│       └── PasswordPage.php     # Manejo de la página de reset
├── templates/
│   └── auth/              # Plantillas atómicas (PHP)
│       └── parts/         # Fragmentos de formulario reutilizables
└── init.php               # Punto de entrada y Autoloader PSR-4
```

## 🛠️ Cómo funciona

### 1. Inicialización
El módulo se inicializa a través de `AuthOrchestrator`. Este orquestador gestiona:
- Registro de hooks de WordPress (`wp_footer`, `template_redirect`, etc.).
- Encolado de assets condicionales.
- Registro de shortcodes.

### 2. Handlers (Lógica de Backend)
- **LoginHandler**: Gestiona la autenticación segura y redirecciones post-login.
- **RegisterHandler**: Maneja la creación de usuarios, validaciones y auto-login inicial.
- **VerificationHandler**: Gestiona los tokens de seguridad y el estado `pl_auth_email_verified`.
- **PasswordHandler**: Provee lógica AJAX para detectar cuentas y enviar correos de recuperación.

### 3. Interfaz (UI/Renderer)
El `Renderer` es el encargado de procesar las plantillas en `templates/`. Utiliza un sistema de buffers para devolver el markup que luego el Orchestrator inyecta en el footer o mediante shortcodes.

## ⌨️ Uso Técnico

### Shortcodes
- `[pl_auth_links]`: Genera botones automáticos de Ingresar/Registrarse que abren el modal.

### URLs de Modal
Puedes forzar la apertura del modal mediante parámetros GET:
- `?pl_auth_view=login`: Abre el formulario de acceso.
- `?pl_auth_view=register`: Abre el formulario de registro.
- `?pl_auth_notice=verified`: Muestra mensaje de éxito tras verificar email.

### Redirecciones Inteligentes
El módulo respeta el parámetro `redirect_to`. Si se encuentra en un flujo de autenticación, el sistema recordará dónde estaba el usuario para devolverlo allí tras completar el proceso.

## 🔒 Seguridad
- Uso estricto de **Nonces** en todos los formularios y llamadas AJAX.
- Sanitización profunda de inputs mediante `AuthUtils`.
- Verificación de tokens mediante `hash_hmac` con `wp_salt`.
- Validación de redirecciones mediante `wp_validate_redirect`.

---
*Mantenido por el equipo de Politeia Learning - Estándar Modular Learni v2.0*
