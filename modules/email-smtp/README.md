# Email SMTP (Gmail)

Este módulo configura `wp_mail()` para enviar correos vía SMTP usando Gmail (App Password).

## Admin UI

- Menú: `Politeia Learning → SMTP Email`
- Permite activar/desactivar SMTP, configurar `Gmail Address` + `App Password`, y enviar un correo de prueba.

## Notas

- Gmail requiere 2FA y generar un **App Password** para SMTP.
- Esto no implementa OAuth2; es el camino rápido/estable para salir de `wp_mail()` sin servidor MTA.

