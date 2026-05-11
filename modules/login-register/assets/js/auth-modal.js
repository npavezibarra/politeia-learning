/**
 * Auth Modal JS
 */

// Global functions (available immediately for inline onclick handlers)
window.PLAuthOpenModal = function (view) {
    if (typeof initPlAuth === 'function') {
        initPlAuth();
    }
    var overlay = document.getElementById('pl-auth-overlay');
    if (overlay) {
        overlay.classList.add('is-open');
        // If we have showView available, use it
        if (typeof window.plAuthShowView === 'function') {
            window.plAuthShowView(view === 'register' ? 'register' : 'login');
        }
    }
};

window.PLAuthCloseModal = function () {
    var overlay = document.getElementById('pl-auth-overlay');
    if (overlay) {
        overlay.classList.remove('is-open');
    }
};

function initPlAuth() {
    var overlay = document.getElementById('pl-auth-overlay');
    if (!overlay || overlay.hasAttribute('data-pl-auth-inited')) {
        return;
    }
    overlay.setAttribute('data-pl-auth-inited', '1');

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
    
    if (typeof plAuthData === 'undefined') {
        console.error('plAuthData not found');
        return;
    }

    var forgotNonce = plAuthData.forgotNonce;
    var ajaxUrl = plAuthData.ajaxUrl;
    var labels = plAuthData.labels;
    var loginUrl = plAuthData.loginUrl;
    var registerUrl = plAuthData.registerUrl;
    var isSpanish = plAuthData.isSpanish;

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
                if (v) { prefillEmail = v; return true; }
                return false;
            });

            if (prefillEmail && emailField && !emailField.value) { emailField.value = prefillEmail; }
            if (prefillEmail && emailConfirmField && !emailConfirmField.value) { emailConfirmField.value = prefillEmail; }

            var prefillFirst = '';
            firstNameKeys.some(function (key) {
                var v = (params.get(key) || '').trim();
                if (v) { prefillFirst = v; return true; }
                return false;
            });
            if (prefillFirst && firstNameField && !firstNameField.value) { firstNameField.value = prefillFirst; }

            var prefillLast = '';
            lastNameKeys.some(function (key) {
                var v = (params.get(key) || '').trim();
                if (v) { prefillLast = v; return true; }
                return false;
            });
            if (prefillLast && lastNameField && !lastNameField.value) { lastNameField.value = prefillLast; }
        } catch (e) {}
    }

    function setMessage(type, text) {
        if (!message) return;
        if (!text) {
            message.textContent = '';
            message.className = 'pl-auth-message';
            return;
        }
        message.textContent = text;
        message.className = 'pl-auth-message is-visible ' + (type === 'error' ? 'is-error' : 'is-notice');
    }

    function showView(view) {
        var isRegister = view === 'register';
        var isForgot = view === 'forgot';

        tabs.forEach(function (tab) {
            tab.classList.toggle('is-active', tab.getAttribute('data-pl-auth-view') === view);
        });

        if (modeInput) modeInput.value = view;
        if (submitBtn) {
            submitBtn.textContent = isRegister ? labels.create_account : labels.login;
            submitBtn.classList.toggle('pl-auth-hidden', isForgot);
        }
        if (title) title.textContent = isForgot ? labels.forgot_title : (isRegister ? labels.register_title : labels.welcome);
        if (copy) copy.textContent = isForgot ? labels.forgot_copy : (isRegister ? labels.register_copy : labels.login_copy);

        if (emailField) {
            emailField.type = (isRegister || isForgot) ? 'email' : 'text';
            emailField.setAttribute('autocomplete', (isRegister || isForgot) ? 'email' : 'username');
            emailField.setAttribute('inputmode', (isRegister || isForgot) ? 'email' : 'text');
            emailField.setAttribute('placeholder', isRegister ? (isSpanish ? 'correo@ejemplo.com' : 'email@domain.com') : (isForgot ? (isSpanish ? 'correo@ejemplo.com' : 'email@domain.com') : (isSpanish ? 'correo@ejemplo.com o usuario' : 'email@domain.com or username')));
        }
        if (emailLabel) emailLabel.textContent = (isRegister || isForgot) ? labels.email : labels.login_identifier;

        if (footerCopy && toggleLink) {
            footerCopy.textContent = isForgot ? (isSpanish ? "¿Recordaste tu contraseña?" : "Remembered your password?") : (isRegister ? labels.already_account : labels.new_here);
            toggleLink.textContent = isForgot ? labels.back_to_login : (isRegister ? labels.back_to_login : labels.create_account_link);
            toggleLink.setAttribute('href', isForgot ? loginUrl : (isRegister ? loginUrl : registerUrl));
        }

        if (loginOnly) loginOnly.classList.toggle('pl-auth-hidden', isRegister || isForgot);
        registerFields.forEach(function (field) { field.classList.toggle('pl-auth-hidden', !isRegister); });
        if (passwordField) passwordField.classList.toggle('pl-auth-hidden', isForgot);
        if (inlineMessage) { inlineMessage.style.display = 'none'; inlineMessage.textContent = ''; inlineMessage.style.color = '#000000'; }
        hasSentReset = false;
    }

    // Export showView to window so PLAuthOpenModal can use it
    window.plAuthShowView = showView;

    function openModal(view) {
        showView(view);
        overlay.classList.add('is-open');
    }

    prefillFromQuery();

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            showView(tab.getAttribute('data-pl-auth-view') === 'register' ? 'register' : 'login');
            setMessage('', '');
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            if (overlay.classList.contains('is-loading')) return;
            window.PLAuthCloseModal();
        });
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target && event.target.closest ? event.target.closest('[data-pl-auth-open], [data-rcp-auth-open]') : null;
        if (!trigger) return;
        event.preventDefault();
        window.PLAuthOpenModal(trigger.getAttribute('data-pl-auth-view') === 'register' ? 'register' : 'login');
    });

    if (toggleLink) {
        toggleLink.addEventListener('click', function (event) {
            event.preventDefault();
            var current = modeInput ? modeInput.value : 'login';
            if (current === 'forgot') { showView('login'); } else { showView(current === 'register' ? 'login' : 'register'); }
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
        if (!text) { inlineMessage.style.display = 'none'; inlineMessage.textContent = ''; inlineMessage.style.color = '#000000'; return; }
        inlineMessage.style.display = 'block';
        inlineMessage.textContent = text;
        inlineMessage.style.color = type === 'error' ? '#b91c1c' : '#000000';
    }

    function debounceForgotProbe() {
        if (!emailField) return;
        var email = (emailField.value || '').trim().toLowerCase();
        if (forgotTimer) window.clearTimeout(forgotTimer);
        if (!email || email.indexOf('@') === -1) { setInlineMessage('', ''); lastForgotEmail = ''; hasSentReset = false; return; }

        forgotTimer = window.setTimeout(function () {
            if ((modeInput ? modeInput.value : 'login') !== 'forgot') return;
            if (email === lastForgotEmail && hasSentReset) return;
            lastForgotEmail = email;
            setInlineMessage('', '');

            var url = ajaxUrl + '?action=pl_auth_forgot_password_probe' + '&nonce=' + encodeURIComponent(forgotNonce) + '&email=' + encodeURIComponent(email);
            fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data || !data.success || !data.data) return;
                if (data.data.invalid) return;
                if (!data.data.exists) { hasSentReset = false; setInlineMessage('error', labels.email_not_registered); return; }
                hasSentReset = true;
                setInlineMessage('notice', labels.reset_sent);
            }).catch(function () {});
        }, 450);
    }

    if (emailField) {
        emailField.addEventListener('input', function () { if ((modeInput ? modeInput.value : 'login') !== 'forgot') return; debounceForgotProbe(); });
        emailField.addEventListener('blur', function () { if ((modeInput ? modeInput.value : 'login') !== 'forgot') return; debounceForgotProbe(); });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            var view = modeInput ? modeInput.value : 'login';
            var email = document.getElementById('pl-auth-email');
            var emailConfirm = document.getElementById('pl-auth-email-confirm');
            var password = document.getElementById('pl-auth-password');
            var passwordConfirm = document.getElementById('pl-auth-password-confirm');

            if (view === 'forgot') { event.preventDefault(); debounceForgotProbe(); return; }

            if (view === 'register') {
                var emailValue = email ? email.value.trim() : '';
                var emailConfirmValue = emailConfirm ? emailConfirm.value.trim() : '';
                var passwordValue = password ? password.value : '';
                var passwordConfirmValue = passwordConfirm ? passwordConfirm.value : '';

                if (emailValue !== emailConfirmValue) { event.preventDefault(); setMessage('error', plAuthData.messages.email_mismatch); return; }
                if (passwordValue !== passwordConfirmValue) { event.preventDefault(); setMessage('error', plAuthData.messages.password_mismatch); return; }
            }

            if (submitBtn) submitBtn.disabled = true;
            if (view === 'register') { overlay.classList.add('is-loading'); if (closeBtn) closeBtn.disabled = true; }
        });
    }

    if (notice === 'verification_sent') {
        setMessage('notice', isSpanish ? "Hemos enviado un correo de confirmación. Por favor, revisa tu bandeja de entrada." : "We sent a confirmation email. Please check your inbox.");
    } else if (notice === 'verified') {
        setMessage('notice', isSpanish ? "Tu correo ha sido verificado. Ahora puedes iniciar sesión." : "Your email has been confirmed. You can now log in.");
    } else if (error) {
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
            token_expired: 'El enlace de confirmación ha expirado.',
            pl_uc_role_blocked: 'Solo administradores o editores pueden ingresar mientras el sitio está en construcción.'
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
            token_expired: 'The confirmation link is invalid or expired.',
            pl_uc_role_blocked: 'Only administrators or editors can log in while the site is under construction.'
        };
        setMessage('error', messageMap[error] || (isSpanish ? 'Algo salió mal. Por favor, inténtalo de nuevo.' : 'Something went wrong. Please try again.'));
    }

    if (autoOpen) {
        openModal(initialView === 'register' ? 'register' : 'login');
    } else {
        showView(initialView === 'register' ? 'register' : 'login');
    }
}

// Initial attempt
initPlAuth();

// DOMContentLoaded fallback
document.addEventListener('DOMContentLoaded', initPlAuth);

// Export to window
window.initPlAuth = initPlAuth;
