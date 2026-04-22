<?php
/**
 * Login/register modal template.
 */

if (!defined('ABSPATH')) {
    exit;
}

$auto_open = !empty($auto_open);
$is_spanish = strpos(get_locale(), 'es') === 0;

$labels = [
    'welcome' => $is_spanish ? 'Bienvenido de nuevo' : 'Welcome back',
    'register_title' => $is_spanish ? 'Crea tu cuenta' : 'Create your account',
    'forgot_title' => $is_spanish ? 'Olvidé contraseña' : 'Forgot password',
    'login_copy' => $is_spanish ? 'Inicia sesión para continuar o crea una nueva cuenta.' : 'Log in to continue or create a new account.',
    'register_copy' => $is_spanish ? 'Crea una cuenta para recibir tu email de confirmación.' : 'Create an account and we will send you a confirmation email.',
    'forgot_copy' => $is_spanish ? 'Ingresa tu correo electrónico para restablecer tu contraseña.' : 'Enter your email to reset your password.',
    'login' => $is_spanish ? 'Ingresar' : 'Login',
    'register' => $is_spanish ? 'Registrarse' : 'Register',
    'first_name' => $is_spanish ? 'Nombre' : 'First name',
    'last_name' => $is_spanish ? 'Apellido' : 'Last name',
    'email' => $is_spanish ? 'Correo electrónico' : 'Email',
    'login_identifier' => $is_spanish ? 'Correo o usuario' : 'Email or username',
    'confirm_email' => $is_spanish ? 'Confirmar correo' : 'Confirm email',
    'password' => $is_spanish ? 'Contraseña' : 'Password',
    'confirm_password' => $is_spanish ? 'Confirmar contraseña' : 'Confirm password',
    'remember_me' => $is_spanish ? 'Recuérdame' : 'Remember me',
    'create_account' => $is_spanish ? 'Crear cuenta' : 'Create account',
    'new_here' => $is_spanish ? '¿No tienes cuenta?' : 'New here?',
    'create_account_link' => $is_spanish ? 'Crea una cuenta' : 'Create an account',
    'already_account' => $is_spanish ? '¿Ya tienes cuenta?' : 'Already have an account?',
    'back_to_login' => $is_spanish ? 'Inicia sesión' : 'Back to login',
    'forgot_link' => $is_spanish ? 'Olvidé mi contraseña' : 'Forgot password',
];

$login_url = \Learni\Auth\Utilities\AuthUtils::build_modal_url('login', $redirect_to);
$register_url = \Learni\Auth\Utilities\AuthUtils::build_modal_url('register', $redirect_to);
?>
<div id="pl-auth-overlay" data-initial-view="<?php echo esc_attr($view); ?>" data-notice="<?php echo esc_attr($notice); ?>" data-error="<?php echo esc_attr($error); ?>" data-auto-open="<?php echo esc_attr($auto_open ? '1' : '0'); ?>">
    <div id="pl-auth-card" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Authentication', 'politeia-learning'); ?>">
        <div class="pl-auth-loading" data-pl-auth-loading aria-hidden="true">
            <div class="pl-auth-loading__spinner" aria-hidden="true"></div>
            <div class="pl-auth-loading__text"><?php echo esc_html($is_spanish ? 'Creando cuenta…' : 'Creating account…'); ?></div>
        </div>
        <button class="pl-auth-close" type="button" data-pl-auth-close aria-label="<?php echo esc_attr__('Close', 'politeia-learning'); ?>">×</button>
        <div class="pl-auth-shell">
            <p class="pl-auth-eyebrow"><?php echo esc_html__('Politeia Learning', 'politeia-learning'); ?></p>
            <h2 class="pl-auth-title" data-pl-auth-title><?php echo esc_html($view === 'register' ? $labels['register_title'] : $labels['welcome']); ?></h2>
            <p class="pl-auth-copy" data-pl-auth-copy><?php echo esc_html($view === 'register' ? $labels['register_copy'] : $labels['login_copy']); ?></p>

            <div class="pl-auth-message" data-pl-auth-message></div>

            <div class="pl-auth-tabs">
                <button class="pl-auth-tab is-active" type="button" data-pl-auth-view="login"><?php echo esc_html($labels['login']); ?></button>
                <button class="pl-auth-tab" type="button" data-pl-auth-view="register"><?php echo esc_html($labels['register']); ?></button>
            </div>

            <form class="pl-auth-form" method="post" action="<?php echo esc_url($action_url); ?>" data-pl-auth-form>
                <input type="hidden" name="action" value="pl_auth_submit">
                <input type="hidden" name="pl_auth_nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="mode" value="<?php echo esc_attr($view); ?>" data-pl-auth-mode>
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" data-pl-auth-redirect>

                <?php include PL_AUTH_PATH . 'templates/auth/parts/register-fields.php'; ?>

                <div class="pl-auth-field">
                    <label for="pl-auth-email" data-pl-auth-email-label><?php echo esc_html($view === 'register' ? $labels['email'] : $labels['login_identifier']); ?></label>
                    <input
                        id="pl-auth-email"
                        name="user_login"
                        type="<?php echo esc_attr($view === 'register' ? 'email' : 'text'); ?>"
                        autocomplete="<?php echo esc_attr($view === 'register' ? 'email' : 'username'); ?>"
                        inputmode="<?php echo esc_attr($view === 'register' ? 'email' : 'text'); ?>"
                        placeholder="<?php echo esc_attr($is_spanish ? ($view === 'register' ? 'correo@ejemplo.com' : 'correo@ejemplo.com o usuario') : ($view === 'register' ? 'email@domain.com' : 'email@domain.com or username')); ?>"
                    >
                    <div class="pl-auth-inline-message" data-pl-auth-inline-message style="display:none; margin-top:10px; font-size:12px; font-weight:600; color:#000000;"></div>
                </div>

                <div class="pl-auth-field pl-auth-register-only pl-auth-hidden" data-pl-auth-email-confirm>
                    <label for="pl-auth-email-confirm"><?php echo esc_html($labels['confirm_email']); ?></label>
                    <input id="pl-auth-email-confirm" name="email_confirm" type="email" autocomplete="email" placeholder="correo@ejemplo.com">
                </div>

                <div class="pl-auth-field" data-pl-auth-password-field>
                    <label for="pl-auth-password"><?php echo esc_html($labels['password']); ?></label>
                    <input id="pl-auth-password" name="password" type="password" autocomplete="<?php echo esc_attr($view === 'register' ? 'new-password' : 'current-password'); ?>" placeholder="********">
                </div>

                <div class="pl-auth-field pl-auth-register-only pl-auth-hidden" data-pl-auth-password-confirm>
                    <label for="pl-auth-password-confirm"><?php echo esc_html($labels['confirm_password']); ?></label>
                    <input id="pl-auth-password-confirm" name="password_confirm" type="password" autocomplete="new-password" placeholder="********">
                </div>

                <?php include PL_AUTH_PATH . 'templates/auth/parts/login-form.php'; ?>

                <button class="pl-auth-submit" type="submit" data-pl-auth-submit><?php echo esc_html($view === 'register' ? $labels['create_account'] : $labels['login']); ?></button>
            </form>

            <div class="pl-auth-footer">
                <span data-pl-auth-footer-copy><?php echo esc_html($view === 'register' ? $labels['already_account'] : $labels['new_here']); ?></span>
                <a href="<?php echo esc_url($view === 'register' ? $login_url : $register_url); ?>" data-pl-auth-toggle-link><?php echo esc_html($view === 'register' ? $labels['back_to_login'] : $labels['create_account_link']); ?></a>
            </div>
        </div>
    </div>
</div>
