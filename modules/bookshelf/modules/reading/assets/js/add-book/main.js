window.PRS_Add_Book = window.PRS_Add_Book || {};

(function (exports) {
    exports.init = function () {
        exports.initDOM();
        
        var DOM = exports.DOM;
        var state = exports.state;
        var ui = exports.ui;
        var form = exports.form;
        var search = exports.search;
        var api = exports.api;

        
        // Modal Open/Close
        if (DOM.openButtons) {
            DOM.openButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (DOM.modal) DOM.modal.style.display = 'flex';
                });
            });
        }

        if (DOM.closeButtons) {
            DOM.closeButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    ui.closeModal();
                });
            });
        }

        // Mode Switch
        if (DOM.modeButtons) {
            DOM.modeButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    ui.setMode(btn.getAttribute('data-mode'));
                });
            });
        }

        // Search Suggestion
        if (DOM.titleInput) {
            DOM.titleInput.addEventListener('input', function() {
                var query = this.value;
                clearTimeout(state.debounceTimer);
                state.debounceTimer = setTimeout(function() {
                    search.fetchSuggestions(query);
                }, 300);
            });
        }

        // Form Steps
        if (DOM.nextButtons) {
            DOM.nextButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    form.goToStep(state.currentStepIndex + 1);
                });
            });
        }

        if (DOM.prevButtons) {
            DOM.prevButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    form.goToStep(state.currentStepIndex - 1);
                });
            });
        }

        // Authors logic
        if (DOM.authorInputField) {
            DOM.authorInputField.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    if (form.addAuthorValue(this.value)) {
                        this.value = '';
                        form.refreshAuthors();
                    }
                }
            });
            DOM.authorInputField.addEventListener('blur', function() {
                if (form.addAuthorValue(this.value)) {
                    this.value = '';
                    form.refreshAuthors();
                }
            });
        }

        if (DOM.authorAddButton) {
            DOM.authorAddButton.addEventListener('click', function(e) {
                e.preventDefault();
                state.authorEditMode = true;
                form.refreshAuthors();
                if (DOM.authorInputField) DOM.authorInputField.focus();
            });
        }

        // Submit logic
        if (DOM.form) {
            DOM.form.addEventListener('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);
                
                if (DOM.submitButton) DOM.submitButton.classList.add('is-loading');

                api.addBookToLibrary(formData)
                    .then(function(data) {
                        if (data.success) {
                            if (data.data && data.data.redirect_url) {
                                window.location.href = data.data.redirect_url;
                            } else {
                                DOM.modal.setAttribute('data-success', '1');
                                ui.activateSuccess();
                            }
                        } else {
                            alert(data.data || 'Error saving book');
                        }
                    })
                    .catch(function(err) {
                        alert('Error: ' + err.message);
                    })
                    .finally(function() {
                        if (DOM.submitButton) DOM.submitButton.classList.remove('is-loading');
                    });
            });
        }

        // Rating Stars
        if (DOM.ratingStars) {
            DOM.ratingStars.forEach(function(star) {
                star.addEventListener('click', function() {
                    var val = this.getAttribute('data-value');
                    if (DOM.ratingInput) DOM.ratingInput.value = val;
                    DOM.ratingStars.forEach(function(s, i) {
                        s.classList.toggle('is-active', (i + 1) <= parseInt(val));
                    });
                });
            });
        }

        // Initial setup
        ui.updateModeButtons();
        ui.updateModeVisibility();
        form.refreshAuthors();
        ui.activateSuccess(); // Check if there's a pending success from URL
    };

    // Auto-init on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', exports.init);
    } else {
        exports.init();
    }

})(window.PRS_Add_Book);
