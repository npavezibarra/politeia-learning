/**
 * Global utilities for Politeia Dashboard
 */

/**
 * Translation helper
 */
function t(key) {
    try {
        return (window.pcgCreatorData && window.pcgCreatorData.i18n && window.pcgCreatorData.i18n[key]) ? window.pcgCreatorData.i18n[key] : key;
    } catch (_) {
        return key;
    }
}

/**
 * Percentage formatter
 */
function formatPercent(value) {
    const num = typeof value === 'number' ? value : Number(value || 0);
    if (!isFinite(num)) return '0';
    const rounded = Math.round(num * 100) / 100;
    if (Math.abs(rounded - Math.round(rounded)) < 0.001) {
        return String(Math.round(rounded));
    }
    return String(rounded);
}
/**
 * Toast Notifications
 */
window.pcgShowToast = function(message, type = 'success', duration = 4000) {
    let $container = $('.pcg-toast-container');
    if (!$container.length) {
        $container = $('<div class="pcg-toast-container"></div>').appendTo('body');
    }

    const icons = {
        success: 'dashicons-yes-alt',
        error: 'dashicons-warning',
        info: 'dashicons-info'
    };

    const $toast = $(`
        <div class="pcg-toast pcg-toast--${type}">
            <span class="dashicons ${icons[type] || icons.info}"></span>
            <span class="pcg-toast__message">${message}</span>
        </div>
    `);

    $container.append($toast);
    
    // Trigger entrance animation
    setTimeout(() => $toast.addClass('is-visible'), 10);

    // Auto-remove
    setTimeout(() => {
        $toast.removeClass('is-visible');
        setTimeout(() => $toast.remove(), 400);
    }, duration);
};
