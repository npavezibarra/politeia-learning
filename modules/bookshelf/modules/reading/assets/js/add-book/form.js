window.PRS_Add_Book = window.PRS_Add_Book || {};

(function (exports) {
    exports.form = {
        getFieldValue: function (input) {
            return input ? String(input.value || '').trim() : '';
        },

        toggleFieldLabel: function (input) {
            if (!input || !input.id) return;
            var label = document.querySelector('.prs-add-book__field-label[data-for="' + input.id + '"]');
            if (label) label.hidden = exports.form.getFieldValue(input) === '';
        },

        updateEditFieldState: function (input, editButton, editMode, display) {
            if (!input) return;
            input.hidden = false; // Always visible in this version
            if (display) {
                display.textContent = exports.form.getFieldValue(input);
                display.hidden = true;
            }
            if (editButton) editButton.hidden = true;
        },

        refreshAuthors: function () {
            if (exports.DOM && exports.DOM.authorList) {
                exports.DOM.authorList.innerHTML = '';
                exports.DOM.authorList.hidden = exports.state.authorValues.length === 0;
            }
            if (exports.DOM && exports.DOM.authorHiddenContainer) {
                exports.DOM.authorHiddenContainer.innerHTML = '';
            }

            exports.state.authorValues.forEach(function (value, i) {
                if (exports.DOM && exports.DOM.authorList) {
                    var chip = document.createElement('span');
                    chip.className = 'prs-add-book__author-chip';
                    chip.innerHTML = '<span class="prs-add-book__author-chip-label">' + value + '</span>';
                    
                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'prs-add-book__author-chip-remove';
                    remove.setAttribute('aria-label', 'Remove author ' + value);
                    remove.innerHTML = '<span class="material-symbols-rounded">close</span>';
                    remove.addEventListener('click', function () {
                        exports.form.removeAuthor(i);
                    });
                    
                    chip.appendChild(remove);
                    exports.DOM.authorList.appendChild(chip);
                }

                if (exports.DOM && exports.DOM.authorHiddenContainer) {
                    var hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'prs_author_names[]';
                    hiddenInput.value = value;
                    exports.DOM.authorHiddenContainer.appendChild(hiddenInput);
                }
            });
        },

        addAuthorValue: function (value) {
            if (!value) return false;
            var val = String(value).trim();
            if (!val) return false;
            if (exports.state.authorValues.indexOf(val) === -1) {
                exports.state.authorValues.push(val);
                return true;
            }
            return false;
        },

        addAuthor: function () {
            if (!exports.DOM || !exports.DOM.authorInput) return;
            if (exports.form.addAuthorValue(exports.DOM.authorInput.value)) {
                exports.form.refreshAuthors();
                exports.DOM.authorInput.value = '';
                exports.DOM.authorInput.focus();
            }
        },

        removeAuthor: function (index) {
            if (index >= 0 && index < exports.state.authorValues.length) {
                exports.state.authorValues.splice(index, 1);
                exports.form.refreshAuthors();
            }
        },

        goToStep: function (step) {
            var stepIndex = Math.max(0, Math.min(step, (exports.DOM && exports.DOM.steps ? exports.DOM.steps.length - 1 : 0)));
            exports.state.currentStepIndex = stepIndex;
            if (exports.DOM && exports.DOM.steps) {
                exports.DOM.steps.forEach(function (stepEl, i) {
                    stepEl.hidden = i !== stepIndex;
                });
            }
        },

        setLoading: function (loading) {
            exports.state.isSubmitting = loading;
            if (exports.DOM && exports.DOM.submitButton) {
                exports.DOM.submitButton.disabled = loading;
                exports.DOM.submitButton.classList.toggle('is-loading', loading);
            }
        },

        setAuthors: function (authors) {
            if (!authors) return;
            exports.state.authorValues = [];
            if (Array.isArray(authors)) {
                authors.forEach(function(a) { exports.form.addAuthorValue(a); });
            } else if (typeof authors === 'string') {
                authors.split(',').forEach(function(a) { exports.form.addAuthorValue(a); });
            }
            exports.form.refreshAuthors();
        },

        setCoverPreview: function (url) {
            if (exports.DOM && exports.DOM.coverInput) exports.DOM.coverInput.value = url || '';
            if (exports.DOM && exports.DOM.coverPreview) {
                if (url) {
                    exports.DOM.coverPreview.src = url;
                    exports.DOM.coverPreview.hidden = false;
                } else {
                    exports.DOM.coverPreview.hidden = true;
                }
            }
        }
    };

})(window.PRS_Add_Book);
