<?php
/**
 * Page: Restablecer contraseña (custom reset password flow).
 *
 * Intercepted by PL_Auth_Reset_Password_Page.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once ABSPATH . WPINC . '/pluggable.php';

$rp_key = isset($_GET['key']) ? sanitize_text_field((string) $_GET['key']) : '';
$rp_login = isset($_GET['login']) ? sanitize_text_field((string) $_GET['login']) : '';

$error_message = '';
$success_message = '';
$user = null;

if ($rp_key && $rp_login) {
    $user = check_password_reset_key($rp_key, $rp_login);
    if (is_wp_error($user)) {
        $error_message = __('El enlace ha expirado o es inválido. Por favor, solicita uno nuevo.', 'politeia-learning');
        $user = null;
    }
} else {
    $error_message = __('Falta información necesaria para restablecer la contraseña.', 'politeia-learning');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error_message === '') {
    $nonce_ok = isset($_POST['_wpnonce']) && wp_verify_nonce((string) $_POST['_wpnonce'], 'pl_reset_password');
    if (!$nonce_ok) {
        $error_message = __('Sesión inválida. Vuelve a intentar.', 'politeia-learning');
    } else {
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $confirm = isset($_POST['confirm_password']) ? (string) wp_unslash($_POST['confirm_password']) : '';

        if (strlen($password) < 8) {
            $error_message = __('La contraseña debe tener al menos 8 caracteres.', 'politeia-learning');
        } elseif ($password !== $confirm) {
            $error_message = __('Las contraseñas no coinciden.', 'politeia-learning');
        } elseif (!($user instanceof WP_User)) {
            $error_message = __('El enlace ha expirado o es inválido. Por favor, solicita uno nuevo.', 'politeia-learning');
        } else {
            reset_password($user, $password);
            $success_message = __('Tu contraseña fue actualizada con éxito.', 'politeia-learning');
        }
    }
}

pl_template_open();

$display_name = $user instanceof WP_User ? ($user->display_name ?: $user->user_login) : __('Usuario', 'politeia-learning');
?>

<div class="pl-rp-wrapper">
    <div class="pl-rp-card <?php echo $error_message ? 'pl-hidden' : ''; ?>">
        <div class="pl-rp-header">
            <h1 class="pl-rp-title">
                <?php echo esc_html__('Crea nueva contraseña', 'politeia-learning'); ?><br>
                <span class="pl-rp-badge"><?php echo esc_html((string) $display_name); ?></span>
            </h1>
            <p class="pl-rp-subtitle"><?php echo esc_html__('Ingresa y confirma tus nuevas credenciales.', 'politeia-learning'); ?></p>
        </div>

        <?php if ($success_message): ?>
            <div class="pl-rp-success">
                <h2><?php echo esc_html__('Éxito', 'politeia-learning'); ?></h2>
                <p><?php echo esc_html($success_message); ?></p>
                <a class="pl-rp-button" href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html__('Ir al inicio', 'politeia-learning'); ?></a>
            </div>
        <?php else: ?>
            <form method="post" class="pl-rp-form" novalidate>
                <?php wp_nonce_field('pl_reset_password'); ?>
                <input type="hidden" name="rp_key" value="<?php echo esc_attr($rp_key); ?>">
                <input type="hidden" name="rp_login" value="<?php echo esc_attr($rp_login); ?>">

                <div class="pl-rp-field">
                    <label for="pl_rp_password"><?php echo esc_html__('Nueva contraseña', 'politeia-learning'); ?></label>
                    <input id="pl_rp_password" name="password" type="password" required autocomplete="new-password" placeholder="••••••••">
                </div>

                <div class="pl-rp-field">
                    <label for="pl_rp_confirm"><?php echo esc_html__('Confirmar contraseña', 'politeia-learning'); ?></label>
                    <input id="pl_rp_confirm" name="confirm_password" type="password" required autocomplete="new-password" placeholder="••••••••">
                </div>

                <button type="submit" class="pl-rp-button">
                    <?php echo esc_html__('Aceptar y actualizar', 'politeia-learning'); ?>
                </button>

                <div class="pl-rp-hints">
                    <div>[<?php echo esc_html__('mínimo 8 caracteres', 'politeia-learning'); ?>]</div>
                    <div>[<?php echo esc_html__('combinación alfa-numérica', 'politeia-learning'); ?>]</div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($error_message): ?>
        <div class="pl-rp-card">
            <div class="pl-rp-critical">
                <h2><?php echo esc_html__('Error', 'politeia-learning'); ?></h2>
                <p><?php echo esc_html($error_message); ?></p>
                <a class="pl-rp-button" href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html__('Volver al inicio', 'politeia-learning'); ?></a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .pl-hidden { display: none; }
    .pl-rp-wrapper {
        min-height: 70vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px 16px;
        background: #fcfcfc;
        color: #000;
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }
    .pl-rp-card {
        width: 100%;
        max-width: 420px;
        background: #fff;
        border: 1px solid #000;
        border-radius: 8px;
        padding: 36px 32px;
        box-sizing: border-box;
    }
    .pl-rp-title {
        margin: 0;
        font-size: 22px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: -0.01em;
        line-height: 1.1;
        text-align: center;
    }
    .pl-rp-badge {
        display: inline-block;
        margin-top: 14px;
        background: #000;
        color: #fff;
        padding: 6px 10px;
        border-radius: 4px;
        font-size: 12px;
        letter-spacing: 0;
        text-transform: none;
    }
    .pl-rp-subtitle {
        margin: 14px 0 0;
        font-size: 12px;
        font-weight: 600;
        text-align: center;
        opacity: .85;
    }
    .pl-rp-form { margin-top: 22px; }
    .pl-rp-field { margin-bottom: 16px; }
    .pl-rp-field label {
        display: block;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .18em;
        margin-bottom: 8px;
    }
    .pl-rp-field input {
        width: 100%;
        padding: 12px 12px;
        border: 1px solid #000;
        border-radius: 8px;
        font-size: 14px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
        box-sizing: border-box;
        outline: none;
    }
    .pl-rp-field input:focus { background: #fafafa; }
    .pl-rp-button {
        display: block;
        width: 100%;
        padding: 14px 14px;
        background: #000;
        color: #fff;
        border: 1px solid #000;
        border-radius: 8px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .15em;
        font-size: 12px;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
        box-sizing: border-box;
    }
    .pl-rp-button:hover { background: #fff; color: #000; }
    .pl-rp-hints {
        margin-top: 18px;
        padding-top: 18px;
        border-top: 1px dotted #000;
        text-align: center;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .18em;
        opacity: .7;
        line-height: 1.8;
    }
    .pl-rp-critical h2,
    .pl-rp-success h2 {
        margin: 0 0 10px;
        text-align: center;
        font-size: 18px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .pl-rp-critical p,
    .pl-rp-success p {
        margin: 0 0 18px;
        text-align: center;
        font-size: 14px;
        line-height: 1.6;
    }
</style>

<?php
pl_template_close();

