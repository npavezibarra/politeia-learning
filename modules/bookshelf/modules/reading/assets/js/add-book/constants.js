window.PRS_Add_Book = window.PRS_Add_Book || {};

(function (exports) {
    // Internationalization fallback wrapper
    var I18N = window.PRS_ADD_BOOK_I18N || {};
    exports.text = function (key, fallback) {
        return I18N && I18N[key] ? I18N[key] : fallback;
    };

    // Configuration
    exports.config = {
        ajaxUrl: window.PRS_ADD_BOOK_DATA ? window.PRS_ADD_BOOK_DATA.ajaxurl : '',
        nonce: window.PRS_ADD_BOOK_DATA ? window.PRS_ADD_BOOK_DATA.nonce : ''
    };

    // DOM Elements Cache
    exports.DOM = {};

    exports.initDOM = function () {
        var modal = document.getElementById('prs-add-book-modal');
        var form = document.getElementById('prs-add-book-form');
        var successContainer = document.getElementById('prs-add-book-success');
        var modeSwitch = document.getElementById('prs-add-book-mode-switch');
        var multipleContainer = document.getElementById('prs-add-book-multiple');
        var authorContainer = document.getElementById('prs_author_fields');

        Object.assign(exports.DOM, {
            modal: modal,
            modalContent: modal ? modal.querySelector('.prs-add-book__modal-content') : null,
            closeButtons: modal ? modal.querySelectorAll('.prs-add-book__close') : null,
            form: form,
            submitButton: form ? form.querySelector('.prs-add-book__submit') : null,
            formHeading: document.getElementById('prs-add-book-form-title'),
            
            successContainer: successContainer,
            successHeading: successContainer ? successContainer.querySelector('.prs-add-book__success-heading') : null,
            successAction: successContainer ? successContainer.querySelector('.prs-add-book__success-action') : null,
            
            modeSwitch: modeSwitch,
            modeButtons: modeSwitch ? modeSwitch.querySelectorAll('.prs-add-book__mode-button') : null,
            multipleContainer: multipleContainer,
            multipleHeading: multipleContainer ? multipleContainer.querySelector('.prs-add-book__heading') : null,
            
            authorContainer: authorContainer,
            authorInputField: document.getElementById('prs_author_input'),
            authorList: document.getElementById('prs_author_list'),
            authorAddButton: document.getElementById('prs_author_add'),
            authorInputWrapper: authorContainer ? authorContainer.querySelector('.prs-add-book__author-input-wrapper') : null,
            authorHiddenContainer: document.getElementById('prs_author_hidden'),
            authorHint: document.getElementById('prs_author_hint'),
            
            autoFillNote: document.getElementById('prs-add-book-auto-fill-note'),
            titleInput: document.getElementById('prs_title'),
            yearInput: document.getElementById('prs_year'),
            yearDisplay: document.getElementById('prs_year_display'),
            yearEditButton: document.getElementById('prs_year_edit'),
            isbnInput: document.getElementById('prs_isbn'),
            isbnDisplay: document.getElementById('prs_isbn_display'),
            isbnEditButton: document.getElementById('prs_isbn_edit'),
            pagesInput: document.getElementById('prs_pages'),
            pagesDisplay: document.getElementById('prs_pages_display'),
            pagesEditButton: document.getElementById('prs_pages_edit'),
            
            formatInput: document.getElementById('prs_format'),
            sourceInput: document.getElementById('prs_source'),
            ratingInput: document.getElementById('prs_rating'),
            ratingStars: document.querySelectorAll('.prs-add-book__rating-star'),
            statusInput: document.getElementById('prs_reading_status'),
            dateInput: document.getElementById('prs_date_read'),
            dateGroup: document.getElementById('prs-date-read-group'),
            reviewInput: document.getElementById('prs_review'),
            reviewGroup: document.getElementById('prs-review-group'),
            
            suggestionContainer: document.getElementById('prs-add-book-suggestions'),
            isbnSuggestionContainer: document.getElementById('prs-add-book-isbn-suggestions'),
            
            coverInput: document.getElementById('prs_cover_url'),
            coverSourceInput: document.getElementById('prs_cover_source'),
            coverPreviewContainer: document.getElementById('prs-add-book-cover-preview'),
            coverPreviewWrapper: document.getElementById('prs-add-book-cover-preview'), // alias
            coverPreviewImage: document.getElementById('prs-add-book-cover-preview-image') || (document.getElementById('prs-add-book-cover-preview') ? document.getElementById('prs-add-book-cover-preview').querySelector('img') : null),
            coverEmptyState: document.getElementById('prs-add-book-cover-empty'),
            
            steps: document.querySelectorAll('.prs-add-book__step'),
            stepTriggers: document.querySelectorAll('.prs-add-book__step-trigger'),
            prevButtons: document.querySelectorAll('.prs-add-book__prev-step'),
            nextButtons: document.querySelectorAll('.prs-add-book__next-step'),
            
            openButtons: document.querySelectorAll('[aria-controls="prs-add-book-modal"]')
        });
    };

})(window.PRS_Add_Book);
