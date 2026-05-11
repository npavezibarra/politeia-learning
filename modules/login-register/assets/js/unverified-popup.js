/**
 * Unverified Popup JS
 */
(function () {
    function init() {
        var overlay = document.getElementById('pl-auth-unverified');
        if (!overlay) return false;

        function setDismissedCookie() {
            // Dismiss for 24 hours
            var date = new Date();
            date.setTime(date.getTime() + (24 * 60 * 60 * 1000));
            document.cookie = "pl_auth_unverified_dismissed=1; expires=" + date.toUTCString() + "; path=/";
        }

        function closeOverlay() {
            overlay.classList.remove('is-open');
            overlay.style.display = 'none';
            setDismissedCookie();
        }

        try {
            if (overlay.classList.contains('is-open') && window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.delete('pl_auth_unverified_after_quiz');
                url.searchParams.delete('pl_auth_unverified');
                url.searchParams.delete('pl_auth_notice');
                url.searchParams.delete('pl_auth_error');
                window.history.replaceState({}, document.title, url.toString());
            }
        } catch (e) { }

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
