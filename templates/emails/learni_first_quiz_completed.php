<?php
if (!defined('ABSPATH')) {
    exit;
}

include PL_PATH . 'templates/emails/partials/quickchart.php';

$locale = determine_locale();
$lang = strtolower(substr((string) $locale, 0, 2));
$logo_url = 'http://nupoliteia.local/wp-content/uploads/2026/04/Captura-de-pantalla-2026-04-05-a-las-12.29.54-p.m.png';
$pl_email_header_title = __('QUIZ COMPLETADO', 'politeia-learning');
$pl_email_document_title = (string) __('First Quiz completado', 'politeia-learning');

$percentage = isset($percentage) ? (int) $percentage : random_int(0, 100);
$percentage = max(0, min(100, $percentage));

if ($percentage >= 90) {
    $email_body = "Hola,\n\n"
        . "¡Qué gran resultado! Has obtenido un {$percentage}% de respuestas correctas, lo que demuestra un dominio excepcional de los conceptos clave tratados hasta ahora.\n\n"
        . "Tu base de conocimientos es sólida, y esto te facilitará enormemente el camino que sigue. ¡Felicidades por este logro! Ahora, el siguiente paso es completar las lecciones restantes del curso. Una vez que las termines, estarás en la posición perfecta para enfrentar la evaluación final con total éxito.\n\n"
        . "¡Sigue así!";
} elseif ($percentage >= 70) {
    $email_body = "Hola,\n\n"
        . "Has completado el quiz con un {$percentage}% de aciertos. Es un desempeño muy positivo que indica que has captado los pilares fundamentales del contenido.\n\n"
        . "¡Muchas felicidades por tu avance! Para asegurar que los detalles más específicos queden bien integrados, te recomendamos prestar especial atención a las próximas lecciones del curso. Completar este material será fundamental para que, al momento de tomar la evaluación final, logres el puntaje máximo.\n\n"
        . "¡Nos vemos en la siguiente lección!";
} else {
    $email_body = "Hola,\n\n"
        . "Has finalizado el quiz con un {$percentage}% de respuestas correctas. Completar esta actividad es un paso valioso para identificar qué áreas requieren un poco más de atención.\n\n"
        . "¡Felicidades por dar este paso y medir tus conocimientos! El aprendizaje es un proceso gradual, y por eso ahora lo más importante es que te enfoques en completar las lecciones del curso. Allí encontrarás todas las herramientas necesarias para profundizar en los temas y prepararte con seguridad para la evaluación final.\n\n"
        . "¡Tu compromiso con el curso te llevará al éxito!";
}

$chart_url = pl_quickchart_doughnut_url($percentage, 'First Quiz');

include PL_PATH . 'templates/emails/partials/unified-shell-top.php';
?>
<tr id="pl-email-body">
    <td align="center" class="poppins" style="padding:50px 40px 10px;font-size:16px;line-height:1.7;color:#000000;">
        <img src="<?php echo esc_url($chart_url); ?>" alt="<?php echo esc_attr__('First Quiz chart', 'politeia-learning'); ?>" width="260" style="display:block;max-width:100%;height:auto;">
    </td>
</tr>

<tr id="pl-email-message">
    <td align="left" class="poppins" style="padding:10px 40px 60px;font-size:15px;line-height:1.75;color:#111827;">
        <?php echo nl2br(esc_html($email_body)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </td>
</tr>
<?php
include PL_PATH . 'templates/emails/partials/unified-shell-bottom.php';

