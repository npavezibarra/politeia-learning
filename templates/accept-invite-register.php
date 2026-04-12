<?php
/**
 * Accept Invite: Non-member registration screen.
 *
 * Expected variables in scope:
 * - $raw_token (string)
 * - $email (string)
 * - $first_name (string)
 * - $last_name (string)
 * - $nonce (string)
 * - $err (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$full_name = trim($first_name . ' ' . $last_name);
$error_msg = '';
if ($err === 'password') {
    $error_msg = __('Las contraseñas no coinciden o son demasiado cortas (mínimo 8 caracteres).', 'politeia-learning');
}
?>
<main class="pl-invite-wrap" style="max-width: 720px; margin: 40px auto; padding: 0 18px;">
    <section style="background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; padding:28px; box-shadow:0 18px 40px rgba(15,23,42,.06);">
        <h1 style="margin:0 0 8px; font-size:22px; line-height:1.2; color:#0f172a;">
            <?php echo esc_html($full_name !== '' ? sprintf(__('Hola %s', 'politeia-learning'), $full_name) : __('Hola', 'politeia-learning')); ?>
        </h1>
        <p style="margin:0 0 18px; color:#475569; font-size:15px;">
            <?php echo esc_html__('Vas a crear cuenta en Politeia.', 'politeia-learning'); ?>
            <?php echo ' '; ?>
            <?php echo esc_html(sprintf(__('El correo de tu cuenta es %s', 'politeia-learning'), $email)); ?>
        </p>

        <?php if ($error_msg !== '') : ?>
            <div style="margin:0 0 16px; padding:12px 14px; border-radius:12px; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; font-size:14px;">
                <?php echo esc_html($error_msg); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid; gap:14px;">
            <input type="hidden" name="action" value="pl_accept_invite_register">
            <input type="hidden" name="token" value="<?php echo esc_attr($raw_token); ?>">
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="first_name" value="<?php echo esc_attr($first_name); ?>">
            <input type="hidden" name="last_name" value="<?php echo esc_attr($last_name); ?>">

            <div style="display:grid; gap:6px;">
                <label for="pl-invite-password" style="font-weight:600; font-size:13px; color:#0f172a;">
                    <?php echo esc_html__('Ingresa una clave', 'politeia-learning'); ?>
                </label>
                <input id="pl-invite-password" name="password" type="password" autocomplete="new-password" required
                    style="height:44px; border-radius:12px; border:1px solid #e2e8f0; padding:0 14px; font-size:14px;">
            </div>

            <div style="display:grid; gap:6px;">
                <label for="pl-invite-password-confirm" style="font-weight:600; font-size:13px; color:#0f172a;">
                    <?php echo esc_html__('Confirmar clave', 'politeia-learning'); ?>
                </label>
                <input id="pl-invite-password-confirm" name="password_confirm" type="password" autocomplete="new-password" required
                    style="height:44px; border-radius:12px; border:1px solid #e2e8f0; padding:0 14px; font-size:14px;">
            </div>

            <button type="submit"
                style="margin-top:8px; height:48px; border-radius:999px; border:0; background:#000; color:#fff; font-weight:800; letter-spacing:2px; text-transform:uppercase; font-size:12px; cursor:pointer;">
                <?php echo esc_html__('Confirmar', 'politeia-learning'); ?>
            </button>
        </form>
    </section>
</main>

