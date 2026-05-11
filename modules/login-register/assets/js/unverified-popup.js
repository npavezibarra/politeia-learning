/**
 * Unverified Popup JS
 */
(function () {
    var overlay = document.getElementById('pl-auth-unverified');
    if (!overlay) return;

    function setDismissedCookie() {
        // Dismiss for 24 hours
        var date = new Date();
        date.setTime(date.getTime() + (24 * 60 * 60 * 1000));
        document.cookie = "pl_auth_unverified_dismissed=1; expires=" + date.toUTCString() + "; path=/";
    }

    function closeOverlay() {
        if (!overlay.classList.contains('is-open')) return;
        overlay.classList.remove('is-open');
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

    var btn = overlay.querySelector('[data-pl-auth-unverified-close]');
    if (btn) {
        btn.addEventListener('click', function () {
            closeOverlay();
        });
    }

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            closeOverlay();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeOverlay();
        }
    });
})();
