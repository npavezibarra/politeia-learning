<?php
if (!defined('ABSPATH')) {
    exit;
}

$locale = determine_locale();
$lang = strtolower(substr((string) $locale, 0, 2));

$logo_url = 'http://nupoliteia.local/wp-content/uploads/2026/04/Captura-de-pantalla-2026-04-05-a-las-12.29.54-p.m.png';
$pl_email_header_title = __('QUIZ COMPLETADO', 'politeia-learning');

include PL_PATH . 'templates/emails/partials/quickchart.php';

$percentage_first = isset($percentage_first) ? (int) $percentage_first : (isset($first_percentage) ? (int) $first_percentage : random_int(0, 100));
$percentage_final = isset($percentage_final) ? (int) $percentage_final : (isset($final_percentage) ? (int) $final_percentage : random_int(0, 100));
$percentage_first = max(0, min(100, $percentage_first));
$percentage_final = max(0, min(100, $percentage_final));

$first_date_label = isset($first_date_label) ? (string) $first_date_label : '';
$final_date_label = isset($final_date_label) ? (string) $final_date_label : '';
$duration_days = isset($duration_days) ? (int) $duration_days : 0;
$lessons_url = isset($lessons_url) ? (string) $lessons_url : '';
$cooldown_days_remaining = isset($cooldown_days_remaining) ? (int) $cooldown_days_remaining : 0;
$retry_date_label = isset($retry_date_label) ? (string) $retry_date_label : '';
$cta_url = isset($cta_url) ? (string) $cta_url : '';
$cta_label = isset($cta_label) ? (string) $cta_label : '';

if ($first_date_label === '') {
    $first_date_label = date_i18n('d M Y', strtotime('-' . (string) random_int(15, 60) . ' days'));
}
if ($final_date_label === '') {
    $final_date_label = date_i18n('d M Y');
}
if ($duration_days <= 0) {
    $duration_days = random_int(10, 90);
}
if ($lessons_url === '') {
    $lessons_url = home_url('/courses/');
}
if ($cta_url === '') {
    $cta_url = $lessons_url;
}
if ($cta_label === '') {
    $cta_label = __('Revisar Lecciones', 'politeia-learning');
}

$delta = $percentage_final - $percentage_first;
$delta_abs = abs($delta);

$variation_label = ($delta > 0 ? '+' : ($delta < 0 ? '-' : '')) . (string) $delta_abs . '%';
$variation_color = $delta > 0 ? '#16a34a' : ($delta < 0 ? '#ef4444' : '#6b7280');
$variation_arrow = $delta > 0 ? '↑' : ($delta < 0 ? '↓' : '→');

$headline = '¡Has terminado la Evaluación Final!';
$subhead = 'Aquí la comparación con la primera evaluación.';

if ($delta > 0) {
    $description = 'Es un gusto ver tu evolución. Superaste tu desempeño inicial y eso suele reflejar un aprendizaje más sólido y una mejor estrategia al responder.';
    $recommendation = 'Sigue avanzando con el mismo ritmo. Si quieres consolidar aún más, revisa las lecciones donde tuviste dudas y vuelve a practicar los conceptos clave.';
} elseif ($delta === 0) {
    $description = 'Mantuviste tu nivel respecto al resultado inicial. Eso es una señal de consistencia: partiste con una buena base y la sostuviste hasta el final.';
    $recommendation = 'Para subir tu puntaje, revisa las lecciones asociadas a las preguntas más difíciles y vuelve a intentarlo cuando te sientas listo/a.';
} else {
    $description = 'Completaste la evaluación final del curso. Vemos una baja respecto a tu resultado inicial. Dado que la evaluación puede ser muy similar (o incluso la misma), este tipo de variación a veces ocurre por factores externos como el tiempo disponible, la concentración del día o la estrategia al responder.';
    $recommendation = 'Revisa las lecciones vinculadas a las preguntas donde tuviste más dificultad y vuelve a intentarlo cuando te sientas listo/a. Usa este resultado como una guía práctica para enfocar tu repaso.';
    if ($cooldown_days_remaining > 0) {
        $recommendation .= ' ' . sprintf(
            _n(
                'Podrás volver a tomar la Evaluación Final en %d día.',
                'Podrás volver a tomar la Evaluación Final en %d días.',
                $cooldown_days_remaining,
                'politeia-learning'
            ),
            $cooldown_days_remaining
        );
        if ($retry_date_label !== '') {
            $recommendation .= ' ' . sprintf(__('Fecha estimada: %s.', 'politeia-learning'), $retry_date_label);
        }
        $recommendation .= ' ' . __('Para obtener el certificado, necesitas sacar un puntaje igual o mayor al de la Evaluación Inicial.', 'politeia-learning');
    }
}

$chart_url_first = pl_quickchart_doughnut_url($percentage_first, 'First Quiz');
$chart_url_final = pl_quickchart_doughnut_url($percentage_final, 'Final Quiz');
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang !== '' ? $lang : 'es'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($headline); ?></title>
    <style>
        body { margin:0; padding:0; font-family: Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background:#ffffff; }
        .poppins { font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important; }
        img { border:0; outline:none; text-decoration:none; }
        table { border-collapse: collapse !important; }
        a { text-decoration: none !important; }
    </style>
</head>
<body style="margin:0;padding:0;background:#ffffff;">
    <center style="width:100%;background:#ffffff;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="background:#ffffff;">
            <tr>
                <td align="center" style="padding:20px 15px;">
                    <table role="presentation" width="672" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:672px;background:#ffffff;border-radius:16px;overflow:hidden;">
                        <?php include PL_PATH . 'templates/emails/partials/unified-header.php'; ?>
                        <tr>
                            <td align="center" style="padding:48px 32px 10px;">
                                <div style="font-size:30px;font-weight:800;color:#111827;line-height:1.25;">
                                    <?php echo esc_html($headline); ?>
                                </div>
                                <div style="margin-top:8px;font-size:13px;font-weight:600;color:#6b7280;">
                                    <?php echo esc_html($subhead); ?>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" style="padding:40px 20px 24px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td align="center" valign="top" width="50%" style="padding:0 10px;">
                                            <img src="<?php echo esc_url($chart_url_first); ?>" width="208" alt="<?php echo esc_attr__('First Quiz chart', 'politeia-learning'); ?>" style="display:block;max-width:100%;height:auto;margin:0 auto;">
                                            <div style="margin-top:14px;font-weight:800;color:#4b5563;">First Quiz</div>
                                            <div style="margin-top:4px;font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:1px;">
                                                <?php echo esc_html($first_date_label); ?>
                                            </div>
                                        </td>
                                        <td align="center" valign="top" width="50%" style="padding:0 10px;">
                                            <img src="<?php echo esc_url($chart_url_final); ?>" width="208" alt="<?php echo esc_attr__('Final Quiz chart', 'politeia-learning'); ?>" style="display:block;max-width:100%;height:auto;margin:0 auto;">
                                            <div style="margin-top:14px;font-weight:800;color:#4b5563;">Final Quiz</div>
                                            <div style="margin-top:4px;font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:1px;">
                                                <?php echo esc_html($final_date_label); ?>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" style="padding:0 32px 20px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="border-top:1px solid #e5e7eb;"></td>
                                    </tr>
                                    <tr>
                                        <td align="center" style="padding:24px 0;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td align="center" width="50%" style="padding:0 10px;">
                                                        <div style="color:#9ca3af;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:2px;margin-bottom:10px;">
                                                            Variación %
                                                        </div>
                                                        <div style="font-size:26px;font-weight:900;color:<?php echo esc_attr($variation_color); ?>;line-height:1;">
                                                            <?php echo esc_html($variation_arrow . $variation_label); ?>
                                                        </div>
                                                    </td>
                                                    <td width="1" bgcolor="#e5e7eb" style="width:1px;background:#e5e7eb;"></td>
                                                    <td align="center" width="50%" style="padding:0 10px;">
                                                        <div style="color:#9ca3af;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:2px;margin-bottom:10px;">
                                                            Tiempo Total
                                                        </div>
                                                        <div style="font-size:26px;font-weight:900;color:#111827;line-height:1;">
                                                            <?php echo esc_html((string) $duration_days); ?> <span style="font-size:16px;color:#9ca3af;font-weight:700;">Días</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border-top:1px solid #e5e7eb;"></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td align="left" style="padding:20px 32px 0;">
                                <div style="max-width:520px;margin:0 auto;font-size:15px;line-height:1.75;color:#4b5563;">
                                    <?php echo esc_html($description); ?>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" style="padding:24px 32px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
                                    <tr>
                                        <td bgcolor="#ffffff" style="background:#ffffff;border-radius:6px;padding:18px 18px;border:1px solid #e5e7eb;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td width="28" valign="top" style="padding-right:12px;">
                                                        <div style="width:24px;height:24px;line-height:24px;text-align:center;border-radius:999px;background:#f3f4f6;color:#111827;font-weight:900;">i</div>
                                                    </td>
                                                    <td valign="top">
                                                        <div style="font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:2px;color:#111827;margin-bottom:8px;">
                                                            Recomendación:
                                                        </div>
                                                        <div style="font-size:13px;line-height:1.7;color:#4b5563;">
                                                            <?php echo esc_html($recommendation); ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" style="padding:28px 32px 40px;">
                                <a href="<?php echo esc_url($cta_url); ?>" style="display:inline-block;background:#000000;color:#ffffff !important;padding:14px 46px;border-radius:6px;font-weight:900;font-size:12px;text-transform:uppercase;letter-spacing:2px;border:1px solid #000000;">
                                    <?php echo esc_html($cta_label); ?>
                                </a>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" style="padding:16px 32px;border-top:1px solid #f3f4f6;font-size:12px;color:#9ca3af;">
                                Este es un correo automático de seguimiento de progreso.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </center>
</body>
</html>
