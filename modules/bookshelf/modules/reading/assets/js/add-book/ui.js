window.PRS_Add_Book = window.PRS_Add_Book || {};

(function (exports) {
    exports.ui = {
        updateAriaLabelledBy: function (id) {
            if (!exports.DOM || !exports.DOM.modal) return;
            if (id) exports.DOM.modal.setAttribute('aria-labelledby', id);
        },

        clearSuccessParams: function () {
            if (typeof window === 'undefined' || !window.history || typeof window.URL !== 'function') return;
            try {
                var currentUrl = new window.URL(window.location.href);
                var params = currentUrl.searchParams;
                var removed = false;
                var keys = ['prs_added', 'prs_added_title', 'prs_added_author', 'prs_added_year', 'prs_added_pages', 'prs_added_cover', 'prs_added_slug'];
                keys.forEach(function(key) {
                    if (params.has(key)) {
                        params.delete(key);
                        removed = true;
                    }
                });
                if (removed) {
                    var newUrl = currentUrl.pathname + (params.toString() ? '?' + params.toString() : '') + currentUrl.hash;
                    window.history.replaceState({}, '', newUrl);
                }
            } catch (e) {}
        },

        updateModeButtons: function () {
            if (!exports.DOM || !exports.DOM.modeButtons) return;
            exports.DOM.modeButtons.forEach(function (button) {
                var buttonMode = button.getAttribute('data-mode') || 'single';
                var isActive = buttonMode === exports.state.currentMode;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        },

        updateModeVisibility: function () {
            var pendingSuccess = exports.state.successActive || (exports.DOM && exports.DOM.modal && exports.DOM.modal.getAttribute('data-success') === '1');

            if (exports.DOM && exports.DOM.modalContent) {
                exports.DOM.modalContent.classList.remove('prs-add-book__modal-content--multiple');
                if (!pendingSuccess && exports.state.currentMode === 'multiple') {
                    exports.DOM.modalContent.classList.add('prs-add-book__modal-content--multiple');
                }
            }

            if (exports.DOM && exports.DOM.modeSwitch) exports.DOM.modeSwitch.hidden = pendingSuccess;

            if (pendingSuccess) {
                if (exports.DOM && exports.DOM.form) exports.DOM.form.hidden = true;
                if (exports.DOM && exports.DOM.formHeading) exports.DOM.formHeading.hidden = true;
                if (exports.DOM && exports.DOM.multipleContainer) exports.DOM.multipleContainer.hidden = true;
                if (exports.DOM && exports.DOM.multipleHeading) exports.DOM.multipleHeading.hidden = true;
                return;
            }

            if (exports.state.currentMode === 'multiple' && exports.DOM && exports.DOM.multipleContainer) {
                if (exports.DOM && exports.DOM.form) exports.DOM.form.hidden = true;
                if (exports.DOM && exports.DOM.formHeading) exports.DOM.formHeading.hidden = true;
                exports.DOM.multipleContainer.hidden = false;
                if (exports.DOM && exports.DOM.multipleHeading) {
                    exports.DOM.multipleHeading.hidden = false;
                    if (exports.DOM.multipleHeading.id) exports.ui.updateAriaLabelledBy(exports.DOM.multipleHeading.id);
                }
            } else {
                exports.state.currentMode = 'single';
                if (exports.DOM && exports.DOM.form) exports.DOM.form.hidden = false;
                if (exports.DOM && exports.DOM.formHeading) {
                    exports.DOM.formHeading.hidden = false;
                    if (exports.DOM.formHeading.id) exports.ui.updateAriaLabelledBy(exports.DOM.formHeading.id);
                }
                if (exports.DOM && exports.DOM.multipleContainer) exports.DOM.multipleContainer.hidden = true;
                if (exports.DOM && exports.DOM.multipleHeading) exports.DOM.multipleHeading.hidden = true;
            }
        },

        setMode: function (mode) {
            exports.state.currentMode = (mode === 'multiple' && exports.DOM && exports.DOM.multipleContainer) ? 'multiple' : 'single';
            exports.ui.updateModeButtons();
            exports.ui.updateModeVisibility();
        },

        closeModal: function () {
            if (exports.DOM && exports.DOM.modal) {
                exports.DOM.modal.style.display = 'none';
            }
            exports.ui.resetToForm();
        },

        resetToForm: function (force) {
            if (!exports.state.successActive && !force) return;
            exports.state.successActive = false;
            if (exports.DOM && exports.DOM.form) {
                exports.DOM.form.reset();
                exports.DOM.form.dispatchEvent(new Event('reset', { bubbles: true }));
            }
            if (exports.DOM && exports.DOM.successContainer) exports.DOM.successContainer.hidden = true;
            if (exports.DOM && exports.DOM.modalContent) exports.DOM.modalContent.classList.remove('prs-add-book__modal-content--success');
            exports.ui.setMode('single');
            if (exports.form && exports.form.goToStep) exports.form.goToStep(0);
        },

        activateSuccess: function () {
            if (exports.DOM && exports.DOM.modal && exports.DOM.modal.getAttribute('data-success') === '1') {
                exports.state.successActive = true;
                if (exports.DOM.modalContent) exports.DOM.modalContent.classList.add('prs-add-book__modal-content--success');
                if (exports.DOM.form) exports.DOM.form.hidden = true;
                if (exports.DOM.formHeading) exports.DOM.formHeading.hidden = true;
                if (exports.DOM.multipleContainer) exports.DOM.multipleContainer.hidden = true;
                if (exports.DOM.multipleHeading) exports.DOM.multipleHeading.hidden = true;
                if (exports.DOM.modeSwitch) exports.DOM.modeSwitch.hidden = true;
                if (exports.DOM.successContainer) exports.DOM.successContainer.hidden = false;
                
                var title = exports.DOM.modal.getAttribute('data-added-title') || '';
                if (title && exports.DOM.successHeading) {
                    exports.DOM.successHeading.textContent = exports.text('success_msg', '¡Enhorabuena! has añadido ' + title + ' a tu biblioteca.');
                }
                
                exports.ui.clearSuccessParams();
                if (exports.DOM.successAction) {
                    setTimeout(function() { exports.DOM.successAction.focus(); }, 100);
                }
            }
        }
    };

})(window.PRS_Add_Book);
