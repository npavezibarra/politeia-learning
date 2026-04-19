<?php
if (!defined('ABSPATH')) {
    exit;
}

$locale = determine_locale();
$lang = strtolower(substr((string) $locale, 0, 2));

$user = wp_get_current_user();
$username = $user && $user->user_login ? (string) $user->user_login : 'usuario';

$course_name = isset($course_name) ? (string) $course_name : '';
$evaluation_url = isset($evaluation_url) ? (string) $evaluation_url : '';
$course_url = isset($course_url) ? (string) $course_url : '';

$site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
$year = (string) wp_date('Y');

// Header original (unificado) - NO modificar estructura/estilos.
$logo_url = 'http://nupoliteia.local/wp-content/uploads/2026/04/Captura-de-pantalla-2026-04-05-a-las-12.29.54-p.m.png';
$pl_email_header_title = __('INSCRIPCIÓN CURSO', 'politeia-learning');
?>
<!doctype html>
<html lang="<?php echo esc_attr($lang !== '' ? $lang : 'es'); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo esc_html__('Bienvenido al curso en Politeia', 'politeia-learning'); ?></title>
  <style>
    td#pl-email-body-content {
      padding-top: 20px !important;
    }
  </style>
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <center style="width:100%;background:#ffffff;table-layout:fixed;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="background:#ffffff;">
      <tr>
        <td align="center" style="padding:20px 15px;">
          <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;overflow:hidden;">
            <tbody id="pl-email-wrapper">
              <?php include PL_PATH . 'templates/emails/partials/unified-header.php'; ?>

            <tr id="pl-email-body">
              <td id="pl-email-body-content" align="center" style="padding:0 32px 30px;font-size:16px;line-height:1.6;color:#374151;">
                <p style="margin:0 0 16px;font-size:20px;color:#111827;">¡Hola, <strong><?php echo esc_html($username); ?></strong>!</p>
                <p style="margin:0 0 16px;">
                  Felicitaciones por inscribirte al curso <strong><?php echo esc_html($course_name !== '' ? $course_name : '{{course_name}}'); ?></strong>.<br>
                  Estamos muy emocionados de tenerte con nosotros.
                </p>
              </td>
            </tr>

            <tr id="pl-email-section-evaluation">
              <td id="pl-email-section-evaluation-content" align="center" style="padding:0 32px 30px;">
                <table id="pl-email-section-evaluation-table" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tr id="pl-email-section-evaluation-text-row">
                    <td id="pl-email-section-evaluation-text" align="center" style="padding-bottom:16px;font-size:14px;color:#4b5563;">
                      Si aún no has tomado la <strong>primera evaluación</strong> (necesaria para comenzar),<br> hazlo aquí:
                    </td>
                  </tr>
                  <tr id="pl-email-section-evaluation-cta-row">
                    <td id="pl-email-section-evaluation-cta" align="center">
                      <a id="pl-email-evaluation-button" href="<?php echo esc_url($evaluation_url !== '' ? $evaluation_url : '{{evaluation_url}}'); ?>" style="background:#ffffff;color:#000000 !important;text-decoration:none;display:inline-block;padding:12px 24px;border-radius:6px;font-size:13px;font-weight:700;letter-spacing:2px;border:1px solid #000000;width:240px;text-align:center;">EVALUACIÓN INICIAL</a>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <tr id="pl-email-section-course">
              <td id="pl-email-section-course-content" align="center" style="padding:10px 32px 40px;">
                <table id="pl-email-section-course-table" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tr id="pl-email-section-course-text-row">
                    <td id="pl-email-section-course-text" align="center" style="padding-bottom:16px;font-size:14px;color:#4b5563;">
                      Si ya completaste la evaluación, puedes comenzar de inmediato:
                    </td>
                  </tr>
                  <tr id="pl-email-section-course-cta-row">
                    <td id="pl-email-section-course-cta" align="center">
                      <a id="pl-email-course-button" href="<?php echo esc_url($course_url !== '' ? $course_url : '{{course_url}}'); ?>" style="background:#000000;color:#ffffff !important;text-decoration:none;display:inline-block;padding:12px 24px;border-radius:6px;font-size:13px;font-weight:700;letter-spacing:2px;border:1px solid #000000;width:240px;text-align:center;">IR AL CURSO</a>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <tr id="pl-email-footer">
              <td id="pl-email-footer-content" align="center" style="padding:40px 32px 20px;border-top:1px solid #eeeeee;font-size:12px;color:#9ca3af;">
                Has recibido este correo porque te inscribiste en <?php echo esc_html($site_name !== '' ? $site_name : '{{site_name}}'); ?>.<br>
                &copy; <?php echo esc_html($year !== '' ? $year : '2025'); ?> Politeia. Todos los derechos reservados.
              </td>
            </tr>
            </tbody>
          </table>
        </td>
      </tr>
    </table>
  </center>
</body>
</html>
