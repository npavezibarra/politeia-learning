/**
 * Unverified Popup JS
 */
(function () {
    function init() {
        var root = document.getElementById('pl-auth-unverified');
        if (!root) return false;

        var openBtn = root.querySelector('[data-pl-auth-unverified-open]');
        var overlay = root.querySelector('.pl-auth-unverified__overlay');
        if (!overlay) return true;

        function setDismissedCookie() {
            // Don't auto-open again for 24 hours (tab still allows opening)
            var date = new Date();
            date.setTime(date.getTime() + (24 * 60 * 60 * 1000));
            document.cookie = "pl_auth_unverified_dismissed=1; expires=" + date.toUTCString() + "; path=/";
        }

        function closeOverlay() {
            root.classList.remove('is-open');
            setDismissedCookie();
        }

        function openOverlay() {
            root.classList.add('is-open');
        }

        try {
            if (root.classList.contains('is-open') && window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.delete('pl_auth_unverified_after_quiz');
                url.searchParams.delete('pl_auth_unverified');
                url.searchParams.delete('pl_auth_notice');
                url.searchParams.delete('pl_auth_error');
                window.history.replaceState({}, document.title, url.toString());
            }
        } catch (e) { }

        if (openBtn) {
            openBtn.addEventListener('click', function () {
                openOverlay();
            });
        }

        overlay.addEventListener('click', function (event) {
            var closeBtn = event.target && event.target.closest ? event.target.closest('[data-pl-auth-unverified-close]') : null;
            if (closeBtn) {
                event.preventDefault();
                closeOverlay();
                return;
            }

            if (event.target === overlay) {
                closeOverlay();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeOverlay();
            }
        });

        return true;
    }

    if (!init()) {
        document.addEventListener('DOMContentLoaded', init);
    }
})();
