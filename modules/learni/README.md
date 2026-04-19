## Learni: Binomial Quiz + Certificado

Este módulo implementa el flujo de evaluación **binomial** (Evaluación Inicial + Evaluación Final) y el desbloqueo del **Certificado**.

### Flujos

1) **Curso single-user (sin partner)**
- Usuario compra/obtiene acceso.
- Rinde **Evaluación Inicial**.
- Completa **100% lecciones**.
- Rinde **Evaluación Final**.
- Obtiene certificado **solo si** el puntaje final es **≥** al inicial (ver regla).

2) **Curso con partner (Evaluación Cruzada / Test Partner)**
- Cada curso puede tener un partner (owner + partner).
- Ambos rinden **Evaluación Inicial** y completan **100% lecciones**.
- La **Evaluación Final** se rinde vía **TEST PARTNER** (cross evaluation):
  - El evaluador hace clic en `TEST PARTNER`.
  - El testeado acepta (popup global).
  - Se responde el mismo quiz final, pero el attempt se guarda en la cuenta del testeado.
- Certificado se habilita **solo cuando ambos** cumplen la regla del certificado (mutuo).

### Regla del certificado

Para un usuario en un curso:

- Debe tener **Evaluación Inicial** (baseline `X`).
- Debe existir al menos una **Evaluación Final** con puntaje **≥ X**.
- Debe tener **100% lecciones** completadas.
- Debe existir plantilla de certificado configurada.

En cursos con partner:
- Lo anterior debe cumplirse para **ambos usuarios** (owner y partner).

### Cooldown de reintento (Final < Initial)

Si el usuario rinde la **Evaluación Final** y obtiene un puntaje **< X**:

- **No obtiene certificado**.
- El botón para rendir la Evaluación Final permanece visible, pero queda **deshabilitado**.
- Se habilita nuevamente después de **7 días** desde la fecha del **último final fallido**.
- El cálculo se hace cada vez que se consulta el estado del curso/quiz.

Esto aplica tanto para:
- Single-user (`TAKE FINAL QUIZ`)
- Partnered (`TEST PARTNER`, basado en la elegibilidad/cooldown del usuario testeado)

### Datos persistidos en attempts

Los intentos se guardan en `wp_learni_quiz_attempts.answers_json` incluyendo:
- `phase`: `"initial"` o `"final"` (en datos legacy se infiere por orden histórico).
- `percent`, `score`, `total`, `submittedAt`
- En cross-eval: `crossEval.sessionId` y `crossEval.initiatorUserId`

### REST (resumen)

- `GET /learni/v1/courses/{id}/binomial`
  - Devuelve `attempts.initial`, `attempts.final` y `ui.*` incluyendo:
    - `ui.finalEligible`, `ui.finalCooldownDaysRemaining`, `ui.canTakeFinal`
  - En cursos con partner, incluye `partner.other*` para representar el estado del “otro usuario” (y habilitar/inhabilitar `TEST PARTNER`).

