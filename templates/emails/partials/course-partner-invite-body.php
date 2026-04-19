<?php
/**
 * Email body partial: course partner invite (received).
 *
 * Expected variables:
 * - $invitee_name (string)
 * - $inviter_name (string)
 * - $course_name  (string)
 * - $accept_url   (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$invitee_name = isset($invitee_name) ? (string) $invitee_name : '';
$inviter_name = isset($inviter_name) ? (string) $inviter_name : 'Politeia';
$course_name = isset($course_name) ? (string) $course_name : '';
$accept_url = isset($accept_url) ? (string) $accept_url : '';

$name = trim($invitee_name);
?>

                        <tr>
                            <td align="center" class="poppins" style="padding:50px 40px 20px;font-size:16px;line-height:1.6;color:#000000;">
                                <div class="poppins" style="font-size:20px;font-weight:600;color:#000000;margin-bottom:8px;">
                                    <?php
                                    if ($name === '') {
                                        echo esc_html('¡Hola!');
                                    } else {
                                        echo esc_html(sprintf('¡Hola %s!', $name));
                                    }
                                    ?>
                                </div>

                                <div class="poppins" style="font-size:16px;color:#4b5563;margin-bottom:20px;">
                                    <?php echo esc_html(sprintf('%s te ha invitado a sumarte como partner en el curso:', $inviter_name)); ?>
                                </div>

                                <div class="poppins" style="display:block;font-size:24px;color:#000000;font-weight:700;margin:25px 0;line-height:1.2;">
                                    <?php echo esc_html('"' . ($course_name !== '' ? $course_name : '(curso)') . '"'); ?>
                                </div>

                                <div class="poppins" style="font-size:15px;color:#4b5563;line-height:1.6;margin-bottom:35px;background-color:#f8fafc;padding:20px;border-radius:12px;border:1px solid #f1f5f9;text-align:left;">
                                    ¡Estamos muy emocionados de que comiences este camino! Al aceptar, no solo <span style="color:#000000;font-weight:600;">podrás acceder a todo el contenido del curso</span>, sino que también participarás en una experiencia de aprendizaje colaborativo única: <span style="color:#000000;font-weight:600;">podrás tomar la Evaluación Final a tu partner cuando finalicen todas las lecciones</span>.
                                    <br><br>
                                    Esta evaluación cruzada es clave para su crecimiento: debe ser <span style="color:#000000;font-weight:600;">grabada en vivo, subida a tu canal de YouTube y publicada</span> para obtener tu certificado final. ¡Anímate a completar este desafío y demostrar todo lo aprendido!
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" style="padding:10px 40px 30px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td align="center" bgcolor="#000000" style="border-radius:6px;">
                                            <a href="<?php echo esc_url($accept_url); ?>" class="poppins btn"
                                                style="background-color:#000000;color:#ffffff !important;text-decoration:none;display:inline-block;padding:12px 24px;border-radius:6px;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:2px;border:1px solid #000000;">
                                                <?php echo esc_html__('ACEPTAR INVITACIÓN', 'politeia-learning'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" class="poppins" style="padding:0 40px 60px;font-size:14px;line-height:1.6;color:#666666;">
                                <?php echo esc_html__('Si no esperabas este correo, puedes ignorarlo con seguridad.', 'politeia-learning'); ?>
                            </td>
                        </tr>

