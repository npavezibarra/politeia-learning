(function ($) {
    'use strict';

    if (typeof window.pcgCourseAuthors === 'undefined') {
        return;
    }

    const settings = window.pcgCourseAuthors;

    $(document).ready(function () {
        const $wrapper = $('#pcg-course-teachers-wrapper');
        if (!$wrapper.length) return;

        const $input = $('#pcg-teacher-search');
        const $results = $wrapper.find('.pcg-search-results');
        const $list = $wrapper.find('.pcg-teachers-list');
        const $hidden = $('#pcg-teachers-hidden');

        let selectedIds = JSON.parse($hidden.val() || '[]');
        let searchTimeout = null;

        /**
         * Update the hidden input with current IDs
         */
        function updateHiddenField() {
            $hidden.val(JSON.stringify(selectedIds));
        }

        /**
         * Add a teacher tag to the UI
         */
        function addTeacherTag(id, name) {
            id = parseInt(id);
            if (selectedIds.includes(id)) return;

            selectedIds.push(id);
            updateHiddenField();

            const $tag = $(`
                <span class="pcg-teacher-tag" data-user-id="${id}">
                    <span class="pcg-tag-label">${name}</span>
                    <button type="button" class="pcg-tag-remove">&times;</button>
                </span>
            `);

            $list.append($tag);
        }

        /**
         * Remove a teacher tag
         */
        $list.on('click', '.pcg-tag-remove', function () {
            const $tag = $(this).closest('.pcg-teacher-tag');
            const id = parseInt($tag.data('user-id'));

            selectedIds = selectedIds.filter(item => item !== id);
            updateHiddenField();
            $tag.remove();
        });

        /**
         * Handle Search Input
         */
        $input.on('input', function () {
            const query = $(this).val().trim();

            clearTimeout(searchTimeout);
            if (query.length < 2) {
                $results.hide().empty();
                return;
            }

            searchTimeout = setTimeout(() => {
                $.ajax({
                    url: settings.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: settings.action,
                        nonce: settings.nonce,
                        q: query,
                        exclude_author: settings.mainAuthorId
                    },
                    success: function (response) {
                        if (response.success && response.data.length > 0) {
                            $results.empty().show();
                            response.data.forEach(user => {
                                // Don't show already selected users in results
                                if (selectedIds.includes(parseInt(user.id))) return;

                                const $item = $(`<div>${user.name}</div>`);
                                $item.on('click', function () {
                                    addTeacherTag(user.id, user.name);
                                    $results.hide().empty();
                                    $input.val('');
                                });
                                $results.append($item);
                            });

                            if ($results.is(':empty')) {
                                $results.hide();
                            }
                        } else {
                            $results.hide().empty();
                        }
                    }
                });
            }, 300);
        });

        /**
         * Close results when clicking outside
         */
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.pcg-search-wrapper').length) {
                $results.hide().empty();
            }
        });
    });

})(jQuery);
