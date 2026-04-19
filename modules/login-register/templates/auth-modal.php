<?php
/**
 * Login/register modal template.
 *
 * Available variables:
 * - $view
 * - $notice
 * - $error
 * - $redirect_to
 * - $action_url
 * - $nonce
 * - $auto_open
 */

if (!defined('ABSPATH')) {
    exit;
}

$auto_open = !empty($auto_open);
$is_spanish = strpos(get_locale(), 'es') === 0;

// Translation helpers for UI
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
        'email_not_registered' => $is_spanish ? 'Este email no está registrado' : 'This email is not registered',
        'reset_sent' => $is_spanish ? 'Hemos enviado un correo para reestablecer contraseña' : 'We sent a password reset email',
	];

$login_url = PL_Auth_Login_Register::build_modal_url('login', $redirect_to);
$register_url = PL_Auth_Login_Register::build_modal_url('register', $redirect_to);
?>
	<style>
	    #pl-auth-overlay {
	        position: fixed;
	        inset: 0;
	        display: flex;
	        align-items: center;
	        justify-content: center;
	        padding: 24px;
	        background: rgba(15, 23, 42, 0.68);
	        backdrop-filter: blur(10px);
	        z-index: 9999;
	        opacity: 0;
	        visibility: hidden;
	        transition: opacity 180ms ease, visibility 180ms ease;
	        font-family: Poppins, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	        box-sizing: border-box;
	    }

    #pl-auth-overlay * {
        box-sizing: border-box;
    }

    #pl-auth-overlay.is-open {
        opacity: 1;
        visibility: visible;
    }

	    #pl-auth-card {
	        position: relative;
	        width: min(100%, 400px);
	        background: #ffffff;
	        border: none;
	        border-radius: 14px;
	        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
	        overflow: hidden;
	        transform: translateY(12px) scale(0.98);
	        transition: transform 180ms ease;
	    }

    .pl-auth-loading {
        position: absolute;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 14px;
        padding: 26px;
        background: rgba(248, 250, 252, 0.92);
        backdrop-filter: blur(6px);
        z-index: 2;
        text-align: center;
    }

    #pl-auth-overlay.is-loading .pl-auth-loading {
        display: flex;
    }

    .pl-auth-loading__spinner {
        width: 34px;
        height: 34px;
        border-radius: 999px;
        border: 3px solid rgba(15, 23, 42, 0.14);
        border-top-color: rgba(15, 23, 42, 0.78);
        animation: plAuthSpin 0.8s linear infinite;
    }

    .pl-auth-loading__text {
        font-size: 14px;
        line-height: 1.4;
        color: #0f172a;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    @keyframes plAuthSpin {
        to { transform: rotate(360deg); }
    }

    #pl-auth-overlay.is-open #pl-auth-card {
        transform: translateY(0) scale(1);
    }

	    .pl-auth-shell {
	        padding: 40px;
	    }

	    .pl-auth-eyebrow {
	        display: none;
	    }

	    .pl-auth-title {
	        margin: 0 0 8px;
	        font-size: 1.75rem;
	        line-height: 1.05;
	        color: #000000;
	        font-weight: 600;
	        text-transform: uppercase;
	        letter-spacing: -0.02em;
	    }

	    .pl-auth-copy {
	        margin: 0 0 40px;
	        color: #666666;
	        font-size: 0.85rem;
	        line-height: 1.45;
	    }

	    .pl-auth-tabs {
	        display: none;
	    }

    .pl-auth-tab {
        appearance: none;
        border: 0;
        border-radius: 6px;
        padding: 11px 14px;
        background: transparent;
        color: #475569;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: background 160ms ease, color 160ms ease, box-shadow 160ms ease;
    }

    .pl-auth-tab.is-active {
        background: #111827;
        color: #ffffff;
        box-shadow: 0 10px 20px rgba(17, 24, 39, 0.2);
    }

	    .pl-auth-form {
	        display: block;
	    }

	    .pl-auth-grid {
	        display: grid;
	        grid-template-columns: 1fr;
	        gap: 0;
	    }

	    .pl-auth-field {
	        position: relative;
	        margin-bottom: 30px;
	    }

	    .pl-auth-field label {
	        display: block;
	        font-size: 0.7rem;
	        font-weight: 600;
	        color: #000000;
	        margin-bottom: 2px;
	        letter-spacing: 0.05em;
	        text-transform: uppercase;
	    }

	    .pl-auth-field input {
	        width: 100%;
	        border: none;
	        border-bottom: 2px solid #e0e0e0;
	        border-radius: 0;
	        padding: 8px 0;
	        background: transparent;
	        color: #000000;
	        font-size: 0.95rem;
	        outline: none;
	        transition: border-color 0.2s ease;
	    }

	    .pl-auth-field input:focus {
	        border-bottom-color: #000000;
	        box-shadow: none;
	    }

    .pl-auth-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

	    .pl-auth-remember {
	        display: inline-flex;
	        align-items: center;
	        gap: 8px;
	        color: #666666;
	        font-size: 0.8rem;
	    }

	    .pl-auth-message {
	        display: none;
	        margin: 20px 0 0;
	        padding: 12px;
	        text-align: center;
	        font-size: 0.8rem;
	        font-weight: 500;
	        border: 1px solid #000000;
	        text-transform: uppercase;
	        border-radius: 8px;
	    }

    .pl-auth-message.is-visible {
        display: block;
    }

	    .pl-auth-message.is-error {
	        background: #ffffff;
	        color: #000000;
	        border-style: dashed;
	    }

	    .pl-auth-message.is-notice {
	        background: #000000;
	        color: #ffffff;
	    }

	    .pl-auth-submit {
	        width: 100%;
	        padding: 16px;
	        background-color: #000000;
	        color: #ffffff;
	        border: 1px solid #000000;
	        border-radius: 6px;
	        font-weight: 600;
	        font-size: 0.9rem;
	        letter-spacing: 0.1em;
	        text-transform: uppercase;
	        cursor: pointer;
	        transition: all 0.2s ease;
	        margin-top: 10px;
	    }

	    .pl-auth-submit:hover {
	        background-color: #ffffff;
	        color: #000000;
	    }

	    .pl-auth-footer {
	        text-align: center;
	        margin-top: 30px;
	        font-size: 0.75rem;
	        color: #666666;
	        text-transform: uppercase;
	        letter-spacing: 0.05em;
	        border-top: 0;
	        padding-top: 0;
	    }

	    .pl-auth-footer a {
	        color: #000000;
	        text-decoration: underline;
	        font-weight: 600;
	        cursor: pointer;
	    }

	    .pl-auth-close {
	        position: absolute;
	        top: 14px;
	        right: 14px;
	        width: 40px;
	        height: 40px;
	        border: 0;
	        border-radius: 999px;
	        background: rgba(0, 0, 0, 0.04);
	        color: #000000;
	        cursor: pointer;
	    }

    .pl-auth-hidden {
        display: none !important;
    }

	    @media (max-width: 640px) {
	        .pl-auth-shell {
	            padding: 22px;
	        }

	        .pl-auth-title {
	            font-size: 1.5rem;
	        }

	        .pl-auth-grid {
	            grid-template-columns: 1fr;
	        }
	    }
	</style>
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

                <div class="pl-auth-grid pl-auth-register-only pl-auth-hidden" data-pl-auth-register-fields>
                    <div class="pl-auth-field">
                        <label for="pl-auth-first-name"><?php echo esc_html($labels['first_name']); ?></label>
                        <input id="pl-auth-first-name" name="first_name" type="text" autocomplete="given-name" placeholder="Ana">
                    </div>
                    <div class="pl-auth-field">
                        <label for="pl-auth-last-name"><?php echo esc_html($labels['last_name']); ?></label>
                        <input id="pl-auth-last-name" name="last_name" type="text" autocomplete="family-name" placeholder="García">
                    </div>
	                </div>

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

                <div class="pl-auth-row pl-auth-login-only" data-pl-auth-login-row>
                    <label class="pl-auth-remember">
                        <input type="checkbox" name="remember" value="1">
                        <?php echo esc_html($labels['remember_me']); ?>
                    </label>
                    <a href="#" class="pl-auth-forgot" data-pl-auth-forgot-link style="font-size: 0.75rem; color:#000000; text-decoration: underline; font-weight: 600;">
                        <?php echo esc_html($labels['forgot_link']); ?>
                    </a>
                </div>

                <button class="pl-auth-submit" type="submit" data-pl-auth-submit><?php echo esc_html($view === 'register' ? $labels['create_account'] : $labels['login']); ?></button>
            </form>

            <div class="pl-auth-footer">
                <span data-pl-auth-footer-copy><?php echo esc_html($view === 'register' ? $labels['already_account'] : $labels['new_here']); ?></span>
                <a href="<?php echo esc_url($view === 'register' ? $login_url : $register_url); ?>" data-pl-auth-toggle-link><?php echo esc_html($view === 'register' ? $labels['back_to_login'] : $labels['create_account_link']); ?></a>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('pl-auth-overlay');
    if (!overlay) {
        return;
    }

    var closeBtn = overlay.querySelector('[data-pl-auth-close]');
    var tabs = overlay.querySelectorAll('[data-pl-auth-view]');
    var form = overlay.querySelector('[data-pl-auth-form]');
    var modeInput = overlay.querySelector('[data-pl-auth-mode]');
    var submitBtn = overlay.querySelector('[data-pl-auth-submit]');
    var title = overlay.querySelector('[data-pl-auth-title]');
    var copy = overlay.querySelector('[data-pl-auth-copy]');
    var footerCopy = overlay.querySelector('[data-pl-auth-footer-copy]');
    var toggleLink = overlay.querySelector('[data-pl-auth-toggle-link]');
    var message = overlay.querySelector('[data-pl-auth-message]');
    var loginOnly = overlay.querySelector('[data-pl-auth-login-row]');
    var registerFields = overlay.querySelectorAll('.pl-auth-register-only');
    var initialView = overlay.getAttribute('data-initial-view') || 'login';
    var notice = overlay.getAttribute('data-notice') || '';
    var error = overlay.getAttribute('data-error') || '';
    var autoOpen = overlay.getAttribute('data-auto-open') === '1';
    var emailField = document.getElementById('pl-auth-email');
	    var emailConfirmField = document.getElementById('pl-auth-email-confirm');
	    var firstNameField = document.getElementById('pl-auth-first-name');
	    var lastNameField = document.getElementById('pl-auth-last-name');
	    var emailLabel = overlay.querySelector('[data-pl-auth-email-label]');
        var inlineMessage = overlay.querySelector('[data-pl-auth-inline-message]');
        var forgotLink = overlay.querySelector('[data-pl-auth-forgot-link]');
        var passwordField = overlay.querySelector('[data-pl-auth-password-field]');
        var forgotNonce = '<?php echo esc_js(wp_create_nonce('pl_auth_forgot_password')); ?>';
        var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        var forgotTimer = null;
        var lastForgotEmail = '';
        var hasSentReset = false;

    function prefillFromQuery() {
        try {
            var params = new URLSearchParams(window.location.search || '');

            var emailKeys = ['invite_email', 'signup_email', 'email'];
            var firstNameKeys = ['invite_first_name', 'signup_first_name', 'first_name'];
            var lastNameKeys = ['invite_last_name', 'signup_last_name', 'last_name'];

            var prefillEmail = '';
            emailKeys.some(function (key) {
                var v = (params.get(key) || '').trim();
                if (v) {
                    prefillEmail = v;
                    return true;
                }
                return false;
            });

            if (prefillEmail && emailField && !emailField.value) {
                emailField.value = prefillEmail;
            }
            if (prefillEmail && emailConfirmField && !emailConfirmField.value) {
                emailConfirmField.value = prefillEmail;
            }

            var prefillFirst = '';
            firstNameKeys.some(function (key) {
                var v = (params.get(key) || '').trim();
                if (v) {
                    prefillFirst = v;
                    return true;
                }
                return false;
            });
            if (prefillFirst && firstNameField && !firstNameField.value) {
                firstNameField.value = prefillFirst;
            }

            var prefillLast = '';
            lastNameKeys.some(function (key) {
                var v = (params.get(key) || '').trim();
                if (v) {
                    prefillLast = v;
                    return true;
                }
                return false;
            });
            if (prefillLast && lastNameField && !lastNameField.value) {
                lastNameField.value = prefillLast;
            }
        } catch (e) {
            // ignore
        }
    }

    function setMessage(type, text) {
        if (!message) {
            return;
        }

        if (!text) {
            message.textContent = '';
            message.className = 'pl-auth-message';
            return;
        }

        message.textContent = text;
        message.className = 'pl-auth-message is-visible ' + (type === 'error' ? 'is-error' : 'is-notice');
    }

    function currentLabel(view) {
        return view === 'register' ? 'register' : 'login';
    }

	    function showView(view) {
	        var isRegister = view === 'register';
            var isForgot = view === 'forgot';

        tabs.forEach(function (tab) {
            tab.classList.toggle('is-active', tab.getAttribute('data-pl-auth-view') === view);
        });

        if (modeInput) {
            modeInput.value = view;
        }

        if (submitBtn) {
            submitBtn.textContent = isRegister ? '<?php echo esc_js($labels['create_account']); ?>' : '<?php echo esc_js($labels['login']); ?>';
            submitBtn.classList.toggle('pl-auth-hidden', isForgot);
        }

        if (title) {
            title.textContent = isForgot
                ? '<?php echo esc_js($labels['forgot_title']); ?>'
                : (isRegister ? '<?php echo esc_js($labels['register_title']); ?>' : '<?php echo esc_js($labels['welcome']); ?>');
        }

	        if (copy) {
	            copy.textContent = isForgot
                    ? '<?php echo esc_js($labels['forgot_copy']); ?>'
	                : (isRegister ? '<?php echo esc_js($labels['register_copy']); ?>' : '<?php echo esc_js($labels['login_copy']); ?>');
	        }

	        if (emailField) {
	            emailField.type = (isRegister || isForgot) ? 'email' : 'text';
	            emailField.setAttribute('autocomplete', (isRegister || isForgot) ? 'email' : 'username');
	            emailField.setAttribute('inputmode', (isRegister || isForgot) ? 'email' : 'text');
	            emailField.setAttribute('placeholder', isRegister
	                ? '<?php echo esc_js($is_spanish ? 'correo@ejemplo.com' : 'email@domain.com'); ?>'
	                : (isForgot
                        ? '<?php echo esc_js($is_spanish ? 'correo@ejemplo.com' : 'email@domain.com'); ?>'
	                    : '<?php echo esc_js($is_spanish ? 'correo@ejemplo.com o usuario' : 'email@domain.com or username'); ?>'));
	        }

	        if (emailLabel) {
	            emailLabel.textContent = (isRegister || isForgot)
	                ? '<?php echo esc_js($labels['email']); ?>'
	                : '<?php echo esc_js($labels['login_identifier']); ?>';
	        }

	        if (footerCopy && toggleLink) {
	            footerCopy.textContent = isForgot
                    ? '<?php echo esc_js($is_spanish ? "¿Recordaste tu contraseña?" : "Remembered your password?"); ?>'
	                : (isRegister ? '<?php echo esc_js($labels['already_account']); ?>' : '<?php echo esc_js($labels['new_here']); ?>');
            toggleLink.textContent = isForgot
                ? '<?php echo esc_js($labels['back_to_login']); ?>'
                : (isRegister ? '<?php echo esc_js($labels['back_to_login']); ?>' : '<?php echo esc_js($labels['create_account_link']); ?>');
            toggleLink.setAttribute('href', isForgot ? '<?php echo esc_js($login_url); ?>' : (isRegister ? '<?php echo esc_js($login_url); ?>' : '<?php echo esc_js($register_url); ?>'));
        }

        if (loginOnly) {
            loginOnly.classList.toggle('pl-auth-hidden', isRegister || isForgot);
        }

        registerFields.forEach(function (field) {
            field.classList.toggle('pl-auth-hidden', !isRegister);
        });

        if (passwordField) {
            passwordField.classList.toggle('pl-auth-hidden', isForgot);
        }

        if (inlineMessage) {
            inlineMessage.style.display = 'none';
            inlineMessage.textContent = '';
            inlineMessage.style.color = '#000000';
        }

        hasSentReset = false;
    }

    function openModal(view) {
        showView(view);
        overlay.classList.add('is-open');
    }

    window.PLAuthOpenModal = function (view) {
        openModal(view === 'register' ? 'register' : 'login');
    };

    window.PLAuthCloseModal = function () {
        overlay.classList.remove('is-open');
    };

    prefillFromQuery();

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            showView(currentLabel(tab.getAttribute('data-pl-auth-view') || 'login'));
            setMessage('', '');
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            if (overlay.classList.contains('is-loading')) return;
            window.PLAuthCloseModal();
        });
    }

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            if (overlay.classList.contains('is-loading')) return;
            window.PLAuthCloseModal();
        }
    });

    document.addEventListener('click', function (event) {
        var trigger = event.target && event.target.closest ? event.target.closest('[data-pl-auth-open], [data-rcp-auth-open]') : null;
        if (!trigger) {
            return;
        }

        event.preventDefault();
        openModal(trigger.getAttribute('data-pl-auth-view') === 'register' ? 'register' : 'login');
    });

    if (toggleLink) {
        toggleLink.addEventListener('click', function (event) {
            event.preventDefault();
            var current = modeInput ? modeInput.value : 'login';
            if (current === 'forgot') {
                showView('login');
            } else {
                showView(current === 'register' ? 'login' : 'register');
            }
            setMessage('', '');
        });
    }

    if (forgotLink) {
        forgotLink.addEventListener('click', function (event) {
            event.preventDefault();
            showView('forgot');
            setMessage('', '');
        });
    }

    function setInlineMessage(type, text) {
        if (!inlineMessage) return;
        if (!text) {
            inlineMessage.style.display = 'none';
            inlineMessage.textContent = '';
            inlineMessage.style.color = '#000000';
            return;
        }
        inlineMessage.style.display = 'block';
        inlineMessage.textContent = text;
        inlineMessage.style.color = type === 'error' ? '#b91c1c' : '#000000';
    }

    function debounceForgotProbe() {
        if (!emailField) return;
        var email = (emailField.value || '').trim().toLowerCase();
        if (forgotTimer) window.clearTimeout(forgotTimer);

        if (!email || email.indexOf('@') === -1) {
            setInlineMessage('', '');
            lastForgotEmail = '';
            hasSentReset = false;
            return;
        }

        forgotTimer = window.setTimeout(function () {
            if ((modeInput ? modeInput.value : 'login') !== 'forgot') return;
            if (email === lastForgotEmail && hasSentReset) return;

            lastForgotEmail = email;
            setInlineMessage('', '');

            var url = ajaxUrl
                + '?action=pl_auth_forgot_password_probe'
                + '&nonce=' + encodeURIComponent(forgotNonce)
                + '&email=' + encodeURIComponent(email);

            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.success || !data.data) return;
                    if (data.data.invalid) return;
                    if (!data.data.exists) {
                        hasSentReset = false;
                        setInlineMessage('error', '<?php echo esc_js($labels['email_not_registered']); ?>');
                        return;
                    }
                    hasSentReset = true;
                    setInlineMessage('notice', '<?php echo esc_js($labels['reset_sent']); ?>');
                })
                .catch(function () {});
        }, 450);
    }

    if (emailField) {
        emailField.addEventListener('input', function () {
            if ((modeInput ? modeInput.value : 'login') !== 'forgot') return;
            debounceForgotProbe();
        });
        emailField.addEventListener('blur', function () {
            if ((modeInput ? modeInput.value : 'login') !== 'forgot') return;
            debounceForgotProbe();
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            var view = modeInput ? modeInput.value : 'login';
            var email = document.getElementById('pl-auth-email');
            var emailConfirm = document.getElementById('pl-auth-email-confirm');
            var password = document.getElementById('pl-auth-password');
            var passwordConfirm = document.getElementById('pl-auth-password-confirm');

            if (view === 'forgot') {
                event.preventDefault();
                debounceForgotProbe();
                return;
            }

            if (view === 'register') {
                var emailValue = email ? email.value.trim() : '';
                var emailConfirmValue = emailConfirm ? emailConfirm.value.trim() : '';
                var passwordValue = password ? password.value : '';
                var passwordConfirmValue = passwordConfirm ? passwordConfirm.value : '';

                if (emailValue !== emailConfirmValue) {
                    event.preventDefault();
                    setMessage('error', '<?php echo esc_js(__('The email addresses do not match.', 'politeia-learning')); ?>');
                    return;
                }

                if (passwordValue !== passwordConfirmValue) {
                    event.preventDefault();
                    setMessage('error', '<?php echo esc_js(__('The passwords do not match.', 'politeia-learning')); ?>');
                    return;
                }
            }

            if (submitBtn) {
                submitBtn.disabled = true;
            }

            if (view === 'register') {
                overlay.classList.add('is-loading');
                if (closeBtn) closeBtn.disabled = true;
            }
        });
    }

    if (notice === 'verification_sent') {
        setMessage('notice', '<?php echo esc_js($is_spanish ? "Hemos enviado un correo de confirmación. Por favor, revisa tu bandeja de entrada." : "We sent a confirmation email. Please check your inbox."); ?>');
    } else if (notice === 'verified') {
        setMessage('notice', '<?php echo esc_js($is_spanish ? "Tu correo ha sido verificado. Ahora puedes iniciar sesión." : "Your email has been confirmed. You can now log in."); ?>');
    } else if (error) {
        var isSpanish = <?php echo $is_spanish ? 'true' : 'false'; ?>;
        var messageMap = isSpanish ? {
            invalid_nonce: 'No pudimos verificar tu solicitud. Por favor, inténtalo de nuevo.',
            missing_login: 'Por favor, ingresa tu correo y contraseña.',
            invalid_login: 'Los datos de acceso no son válidos.',
            pl_auth_unverified: 'Tu cuenta aún no está verificada. Por favor, confirma tu correo primero.',
            invalid_email: 'Por favor, ingresa un correo electrónico válido.',
            invalid_username: 'No pudimos crear tu usuario. Por favor, intenta con otro correo electrónico.',
            email_mismatch: 'Los correos electrónicos no coinciden.',
            weak_password: 'La contraseña debe tener al menos 8 caracteres.',
            password_mismatch: 'Las contraseñas no coinciden.',
            account_exists: 'Ya existe una cuenta con ese correo electrónico.',
            create_failed: 'No pudimos crear tu cuenta. Por favor, inténtalo de nuevo.',
            invalid_token: 'El enlace de confirmación no es válido o ha expirado.',
            token_expired: 'El enlace de confirmación ha expirado.'
        } : {
            invalid_nonce: 'We could not verify your request. Please try again.',
            missing_login: 'Please enter your email and password.',
            invalid_login: 'The login details were not valid.',
            pl_auth_unverified: 'Your account is not verified yet. Please confirm your email address first.',
            invalid_email: 'Please enter a valid email address.',
            invalid_username: 'We could not create your user. Please try a different email address.',
            email_mismatch: 'The email addresses do not match.',
            weak_password: 'Your password must be at least 8 characters long.',
            password_mismatch: 'The passwords do not match.',
            account_exists: 'An account already exists with that email address.',
            create_failed: 'We could not create your account. Please try again.',
            invalid_token: 'The confirmation link is invalid or expired.',
            token_expired: 'The confirmation link is invalid or expired.'
        };

        setMessage('error', messageMap[error] || (isSpanish ? 'Algo salió mal. Por favor, inténtalo de nuevo.' : 'Something went wrong. Please try again.'));
    }

    if (autoOpen) {
        openModal(initialView === 'register' ? 'register' : 'login');
    } else {
        showView(initialView === 'register' ? 'register' : 'login');
    }
})();
</script>
