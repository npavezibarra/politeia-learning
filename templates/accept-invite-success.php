<?php
/**
 * Accept Invite: success screen after non-member registration.
 *
 * Expected variables in scope:
 * - $course_url (string)
 * - $profile_url (string)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<main style="max-width: 720px; margin: 48px auto; padding: 0 18px;">
    <section style="background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; padding:30px; box-shadow:0 18px 40px rgba(15,23,42,.06); text-align:center;">
        <h1 style="margin:0 0 10px; font-size:24px; color:#0f172a;">
            <?php echo esc_html__('¡Cuenta creada!', 'politeia-learning'); ?>
        </h1>
        <p style="margin:0 0 22px; color:#475569; font-size:15px;">
            <?php echo esc_html__('Ya puedes acceder al curso como partner.', 'politeia-learning'); ?>
        </p>

        <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
            <a href="<?php echo esc_url($course_url); ?>"
                style="display:inline-flex; align-items:center; justify-content:center; height:46px; padding:0 20px; border-radius:999px; background:#000; color:#fff; text-decoration:none; font-weight:800; letter-spacing:1px; text-transform:uppercase; font-size:12px;">
                <?php echo esc_html__('Ir al curso', 'politeia-learning'); ?>
            </a>
            <a href="<?php echo esc_url($profile_url); ?>"
                style="display:inline-flex; align-items:center; justify-content:center; height:46px; padding:0 20px; border-radius:999px; background:#f1f5f9; color:#0f172a; text-decoration:none; font-weight:800; letter-spacing:1px; text-transform:uppercase; font-size:12px;">
                <?php echo esc_html__('Mi Perfil', 'politeia-learning'); ?>
            </a>
        </div>
    </section>
</main>

