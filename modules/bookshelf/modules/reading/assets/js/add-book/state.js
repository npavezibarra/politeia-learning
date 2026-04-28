window.PRS_Add_Book = window.PRS_Add_Book || {};

(function (exports) {
    exports.state = {
        successActive: false,
        currentMode: 'single', // 'single' or 'multiple'
        
        // Author fields
        authorValues: [],
        authorLookup: Object.create(null),
        removeAuthorLabel: '', 
        
        // Edit modes
        authorEditMode: false,
        yearEditMode: false,
        isbnEditMode: false,
        pagesEditMode: false,
        
        // Search and Auto-complete
        debounceTimer: null,
        abortController: null,
        lastFetchedQuery: '',
        lastSuggestionItems: [],
        lastSelectionToken: 0,
        
        isbnAbortController: null,
        lastFetchedIsbn: '',
        
        supportsAbortController: typeof window.AbortController === 'function',
        
        // Step navigation
        currentStepIndex: 0
    };
})(window.PRS_Add_Book);
