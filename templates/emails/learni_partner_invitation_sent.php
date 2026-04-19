<?php
/**
 * Email: Learni partner invitation (sent confirmation to inviter).
 *
 * Available variables:
 * - $invitee_name (string) Partner name/email.
 * - $inviter_name (string) Inviter display name.
 * - $course_name  (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$invitee_name = isset($invitee_name) ? (string) $invitee_name : '';
$inviter_name = isset($inviter_name) ? (string) $inviter_name : 'Politeia';
$course_name = isset($course_name) ? (string) $course_name : '';

$locale = determine_locale();
$lang = strtolower(substr((string) $locale, 0, 2));
$logo_url = 'http://nupoliteia.local/wp-content/uploads/2026/04/Captura-de-pantalla-2026-04-05-a-las-12.29.54-p.m.png';
$pl_email_header_title = __('INVITACIÓN ENVIADA', 'politeia-learning');
$pl_email_document_title = (string) __('Invitación Enviada - Politeia', 'politeia-learning');

$inviter = trim($inviter_name);
$invitee = trim($invitee_name);
?>
<?php include PL_PATH . 'templates/emails/partials/unified-shell-top.php'; ?>

                        <tr>
                            <td align="center" class="poppins" style="padding:50px 40px 20px;font-size:16px;line-height:1.6;color:#000000;">
                                <div class="poppins" style="font-size:20px;font-weight:600;color:#000000;margin-bottom:12px;">
                                    <?php
                                    if ($inviter === '') {
                                        echo esc_html('¡Hola!');
                                    } else {
                                        echo esc_html(sprintf('¡Hola %s!', $inviter));
                                    }
                                    ?>
                                </div>

                                <div class="poppins" style="font-size:16px;color:#4b5563;margin-bottom:10px;">
                                    <?php
                                    $target = $invitee !== '' ? $invitee : __('tu partner', 'politeia-learning');
                                    echo esc_html(sprintf('Hemos enviado la invitación a %s.', $target));
                                    ?>
                                </div>

                                <div class="poppins" style="font-size:16px;color:#4b5563;margin-bottom:0;">
                                    <?php echo esc_html__('Esperemos que acepte pronto para comenzar las lecciones.', 'politeia-learning'); ?>
                                </div>

                                <?php if (trim($course_name) !== '') : ?>
                                    <div class="poppins" style="display:block;font-size:14px;color:#6b7280;margin-top:18px;">
                                        <?php echo esc_html(sprintf('Curso: %s', $course_name)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>

<?php include PL_PATH . 'templates/emails/partials/unified-shell-bottom.php'; ?>

