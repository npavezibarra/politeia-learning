/**
 * Course Creator - Evaluation (PQC Integration) Logic
 */
jQuery(document).ready(function($) {

    // Toggle to Evaluation mode logic
    // Note: The main tab switcher is in creator-dashboard.js, but we handle
    // specific evaluation behaviors here via events or observers if needed.
    
    $(document).on('click', '#pcg-course-form-section .pcg-segment[data-value="evaluacion"]', function() {
        const courseId = window.pcgCourseState.id;
        const $evalAside = $('#pcg-mode-evaluacion .pcg-eval-editor__right');
        
        if (Number(courseId) === 0) {
            $('#pcg-quiz-not-created-msg').show();
            $('#pcg-quiz-creator-container').hide();
            if ($evalAside.length) {
                $evalAside.hide();
            }
        } else {
            $('#pcg-quiz-not-created-msg').hide();
            $('#pcg-quiz-creator-container').show();
            if ($evalAside.length) {
                $evalAside.show();
            }
            
            // Sync price and trigger refresh
            if (typeof window.syncEvalPriceFromMain === 'function') {
                window.syncEvalPriceFromMain();
            }
            $('#pcg-course-price').trigger('input');
            
            // Dynamically refresh quiz module for current course
            $(document).trigger('pqc_refresh', { courseId: courseId });
        }
    });

    // Helper to trigger save on PQC
    window.triggerQuizSave = function(silent = true) {
        try {
            $(document).trigger('pqc_save', [{ silent: silent }]);
        } catch (_) {
            console.warn('PQC Save trigger failed or not available.');
        }
    };

});
