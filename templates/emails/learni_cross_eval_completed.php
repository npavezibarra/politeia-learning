<?php
if (!defined('ABSPATH')) {
    exit;
}

include PL_PATH . 'templates/emails/partials/quickchart.php';

$locale = determine_locale();
$lang = strtolower(substr((string) $locale, 0, 2));
$logo_url = 'http://nupoliteia.local/wp-content/uploads/2026/04/Captura-de-pantalla-2026-04-05-a-las-12.29.54-p.m.png';
$pl_email_header_title = __('EVALUACIÓN FINAL', 'politeia-learning');
$pl_email_document_title = (string) __('Test Partner completado', 'politeia-learning');

$course_name = isset($course_name) ? (string) $course_name : '';
$tester_name = isset($tester_name) ? (string) $tester_name : '';
$tested_name = isset($tested_name) ? (string) $tested_name : '';
$recipient_role = isset($recipient_role) ? (string) $recipient_role : 'tested'; // tested|tester

$percentage_first = isset($percentage_first) ? (int) $percentage_first : 0;
$percentage_final = isset($percentage_final) ? (int) $percentage_final : 0;
$percentage_first = max(0, min(100, $percentage_first));
$percentage_final = max(0, min(100, $percentage_final));

$first_date_label = isset($first_date_label) ? (string) $first_date_label : '';
$final_date_label = isset($final_date_label) ? (string) $final_date_label : '';
$duration_days = isset($duration_days) ? (int) $duration_days : 0;
$cooldown_days_remaining = isset($cooldown_days_remaining) ? (int) $cooldown_days_remaining : 0;
$retry_date_label = isset($retry_date_label) ? (string) $retry_date_label : '';

$cta_url = isset($cta_url) ? (string) $cta_url : '';
$cta_label = isset($cta_label) ? (string) $cta_label : __('VER CURSO', 'politeia-learning');

$delta = $percentage_final - $percentage_first;
$delta_abs = abs($delta);

$variation_label = ($delta > 0 ? '+' : ($delta < 0 ? '-' : '')) . (string) $delta_abs . '%';
$variation_color = $delta > 0 ? '#16a34a' : ($delta < 0 ? '#ef4444' : '#6b7280');
$variation_arrow = $delta > 0 ? '↑' : ($delta < 0 ? '↓' : '→');

$headline = __('Finalizaste Evaluación Cruzada', 'politeia-learning');
$subhead = __('Estos son tus puntajes comparados', 'politeia-learning');

if ($delta > 0) {
    $description = __('¡Gran avance! El puntaje final superó el inicial, lo que suele reflejar un aprendizaje más sólido y una mejor estrategia al responder.', 'politeia-learning');
    $recommendation = __('Para consolidar aún más, revisa las lecciones donde hubo dudas y vuelve a practicar los conceptos clave.', 'politeia-learning');
} elseif ($delta === 0) {
    $description = __('El puntaje se mantuvo respecto al resultado inicial. Eso habla de consistencia: se partió con una buena base y se sostuvo hasta el final.', 'politeia-learning');
    $recommendation = __('Para subir el puntaje, revisa las lecciones asociadas a las preguntas más difíciles y vuelve a intentarlo cuando estés listo/a.', 'politeia-learning');
} else {
    $description = __('Se completó la evaluación final. Vemos una baja respecto al resultado inicial; esto a veces ocurre por factores externos (tiempo, concentración, estrategia al responder).', 'politeia-learning');
    $recommendation = __('Revisa las lecciones vinculadas a las preguntas más difíciles y vuelve a intentarlo cuando estés listo/a. Usa este resultado como guía para enfocar el repaso.', 'politeia-learning');
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

include PL_PATH . 'templates/emails/partials/unified-shell-top.php';
?>
<tr>
    <td align="center" class="poppins" style="padding:34px 24px 8px;">
        <div style="font-size:26px;font-weight:900;color:#111827;line-height:1.25;">
            <?php echo esc_html($headline); ?>
        </div>
        <div style="margin-top:8px;font-size:13px;font-weight:700;color:#6b7280;letter-spacing:.02em;">
            <?php echo esc_html($subhead); ?>
        </div>
    </td>
</tr>

<tr>
    <td align="center" style="padding:22px 16px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td align="center" valign="top" width="50%" style="padding:0 8px;">
                    <img src="<?php echo esc_url($chart_url_first); ?>" width="208" alt="<?php echo esc_attr__('First Quiz chart', 'politeia-learning'); ?>" style="display:block;max-width:100%;height:auto;">
                    <div class="poppins" style="margin-top:10px;font-size:11px;font-weight:900;letter-spacing:2px;text-transform:uppercase;color:#6b7280;">
                        <?php echo esc_html__('Evaluación inicial', 'politeia-learning'); ?>
                    </div>
                    <?php if ($first_date_label !== ''): ?>
                        <div class="poppins" style="margin-top:4px;font-size:12px;color:#9ca3af;">
                            <?php echo esc_html($first_date_label); ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td align="center" valign="top" width="50%" style="padding:0 8px;">
                    <img src="<?php echo esc_url($chart_url_final); ?>" width="208" alt="<?php echo esc_attr__('Final Quiz chart', 'politeia-learning'); ?>" style="display:block;max-width:100%;height:auto;">
                    <div class="poppins" style="margin-top:10px;font-size:11px;font-weight:900;letter-spacing:2px;text-transform:uppercase;color:#6b7280;">
                        <?php echo esc_html__('Evaluación final', 'politeia-learning'); ?>
                    </div>
                    <?php if ($final_date_label !== ''): ?>
                        <div class="poppins" style="margin-top:4px;font-size:12px;color:#9ca3af;">
                            <?php echo esc_html($final_date_label); ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </td>
</tr>

<tr>
    <td align="center" style="padding:8px 24px 10px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;">
            <tr>
                <td align="center" width="50%" style="padding:18px 10px;">
                    <div class="poppins" style="color:#9ca3af;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:2px;margin-bottom:10px;">
                        <?php echo esc_html__('Variación %', 'politeia-learning'); ?>
                    </div>
                    <div class="poppins" style="font-size:26px;font-weight:900;color:<?php echo esc_attr($variation_color); ?>;line-height:1;">
                        <?php echo esc_html($variation_arrow . $variation_label); ?>
                    </div>
                </td>
                <td width="1" bgcolor="#e5e7eb" style="width:1px;background:#e5e7eb;"></td>
                <td align="center" width="50%" style="padding:18px 10px;">
                    <div class="poppins" style="color:#9ca3af;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:2px;margin-bottom:10px;">
                        <?php echo esc_html__('Tiempo total', 'politeia-learning'); ?>
                    </div>
                    <div class="poppins" style="font-size:26px;font-weight:900;color:#111827;line-height:1;">
                        <?php echo esc_html($duration_days > 0 ? (string) $duration_days : '—'); ?>
                        <span style="font-size:16px;color:#9ca3af;font-weight:700;">
                            <?php echo esc_html__('Días', 'politeia-learning'); ?>
                        </span>
                    </div>
                </td>
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
                                    <?php echo esc_html__('Recomendación:', 'politeia-learning'); ?>
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

<?php if ($cta_url !== ''): ?>
<tr>
    <td align="center" style="padding:26px 24px 42px;">
        <a href="<?php echo esc_url($cta_url); ?>" class="btn" style="display:inline-block;background:#000000;color:#ffffff !important;padding:14px 46px;border-radius:6px;font-weight:900;font-size:12px;text-transform:uppercase;letter-spacing:2px;border:1px solid #000000;">
            <?php echo esc_html($cta_label); ?>
        </a>
    </td>
</tr>
<?php endif; ?>
<?php
include PL_PATH . 'templates/emails/partials/unified-shell-bottom.php';
