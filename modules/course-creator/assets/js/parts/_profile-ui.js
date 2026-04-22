/**
 * Course Creator - Profile UI Logic
 * Extracted from profile.php for modularity in Learni.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        initProfileTabs();
        initProfilePhotoUpload();
        initForms();
        initPortfolioLogic();

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

    /**
     * Main Profile Tabs Switching (Robust delegation)
     */
    function initProfileTabs() {
        $(document).on('click', '#pcg-profile-tabs .pcg-segment', function() {
            const tab = $(this).data('profile-tab');
            
            // UI Update
            $('#pcg-profile-tabs .pcg-segment').removeClass('active');
            $(this).addClass('active');
            
            // Panels Switching
            $('[data-profile-panel]').hide();
            const $panel = $(`[data-profile-panel="${tab}"]`);
            $panel.show();

            // Re-init lucide icons for the newly visible panel
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Lazy load portfolio items if this is the first time visiting the tab
            if (tab === 'portfolio' && !window.pcgPortfolioLoaded) {
                initPortfolioBulkLoad();
            }
            
            // Trigger global event for other components
            window.dispatchEvent(new CustomEvent('pcg:profile-tab-changed', { detail: { tab } }));
        });

        // Open initial tab from URL if present
        try {
            const initialTab = new URLSearchParams(window.location.search).get('profile_tab');
            if (initialTab) {
                const $initial = $(`#pcg-profile-tabs .pcg-segment[data-profile-tab="${initialTab}"]`);
                if ($initial.length) {
                    $initial.trigger('click');
                }
            }
        } catch (e) {
            console.error('Error parsing initial tab:', e);
        }
    }

    /**
     * Profile Photo Upload with Cropper integration
     */
    function initProfilePhotoUpload() {
        $(document).on('click', '.profile-photo-container', function() {
            if (typeof PL_Cropper === 'undefined') {
                console.error('PL_Cropper not found');
                return;
            }

            PL_Cropper.open({
                width: pcgCreatorData.avatarFullWidth || 300,
                height: pcgCreatorData.avatarFullHeight || 300,
                circleMask: true,
                title: pcgCreatorData.i18n.changeProfilePhoto || 'Cambiar foto de perfil',
                onSave: function(dataUrl) {
                    const $img = $('.profile-photo');
                    const originalSrc = $img.attr('src');
                    $img.css('opacity', '0.5');

                    $.ajax({
                        url: pcgCreatorData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'pcg_save_profile_avatar',
                            nonce: pcgCreatorData.nonce,
                            image_data: dataUrl
                        },
                        success: function(response) {
                            if (response.success) {
                                $img.attr('src', response.data.url).css('opacity', '1');
                            } else {
                                alert(response.data.message || 'Error uploading avatar');
                                $img.attr('src', originalSrc).css('opacity', '1');
                            }
                        },
                        error: function() {
                            alert('Connection error');
                            $img.attr('src', originalSrc).css('opacity', '1');
                        }
                    });
                }
            });
        });
    }

    /**
     * Individual Form Handling
     */
    function initForms() {
        $('#pcg-profile-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $btn = $form.find('#saveBtn');
            const $loader = $form.find('#loader');
            const $saveIcon = $form.find('#saveIcon');
            const $btnText = $form.find('#btnText');
            const $status = $('#pcg-profile-status-msg');

            // Loading state
            $btn.prop('disabled', true);
            $loader.show();
            $saveIcon.hide();
            const originalBtnText = $btnText.text();
            $btnText.text('Guardando...');

            const formData = new FormData(this);
            formData.append('action', 'pcg_save_profile');
            formData.append('nonce', pcgCreatorData.nonce);

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        notify(response.data.message || "Perfil actualizado correctamente.");
                    } else {
                        notify(response.data.message || "Error al actualizar el perfil.");
                    }
                },
                error: function() {
                    notify("Error de conexión.");
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $loader.hide();
                    $saveIcon.show();
                    $btnText.text(originalBtnText);
                }
            });
        });

        $('#pcg-interests-form').on('submit', function(e) {
            e.preventDefault();
            notify("Intereses actualizados correctamente.");
        });
    }

    /**
     * Portfolio Visibility Logic (Redesign)
     */
    function initPortfolioLogic() {
        // Custom Checkbox Trigger
        $(document).on('click', '.pcg-item-content', function(e) {
            if ($(e.target).hasClass('pcg-tag-remove')) return;
            
            const $container = $(this);
            const $checkbox = $container.find('.pcg-custom-checkbox');
            const $section = $container.closest('.pcg-portfolio-section');
            const id = parseInt($checkbox.data('id'));
            
            $checkbox.toggleClass('checked');
            const isChecked = $checkbox.hasClass('checked');

            // Sync with hidden data tags
            const $dataStore = $section.find('.pcg-hidden-selected-data');
            if (isChecked) {
                if ($dataStore.find(`[data-id="${id}"]`).length === 0) {
                    $dataStore.append(`<span class="pcg-tag-pill" data-id="${id}"></span>`);
                }
            } else {
                $dataStore.find(`[data-id="${id}"]`).remove();
            }

            updateSelectedCount($section);
            syncSelectAll($section);
        });

        // Select All Toggle
        $(document).on('click', '.pcg-bulk-toggle-wrap', function() {
            const $section = $(this).closest('.pcg-portfolio-section');
            const $masterCheck = $(this).find('.pcg-custom-checkbox');
            $masterCheck.toggleClass('checked');
            const isAllChecked = $masterCheck.hasClass('checked');

            $section.find('.pcg-item-row .pcg-custom-checkbox').each(function() {
                const $cb = $(this);
                if ($cb.hasClass('checked') !== isAllChecked) {
                    $cb.parent().trigger('click');
                }
            });
        });

        // Pagination
        $(document).on('click', '.pcg-prev-page', function() {
            const $section = $(this).closest('.pcg-portfolio-section');
            const sectionId = $section.data('section');
            const currentPage = parseInt($section.find('.pcg-item-grid-container').data('page'));
            if (currentPage > 1) {
                loadPortfolioItems(sectionId, currentPage - 1);
            }
        });

        $(document).on('click', '.pcg-next-page', function() {
            const $section = $(this).closest('.pcg-portfolio-section');
            const sectionId = $section.data('section');
            const currentPage = parseInt($section.find('.pcg-item-grid-container').data('page'));
            loadPortfolioItems(sectionId, currentPage + 1);
        });

        // Save Portfolio
        $('#pcg-save-portfolio').on('click', function() {
            const $btn = $(this);
            const originalText = $btn.html();
            $btn.prop('disabled', true).text('Guardando...');

            const sections = [];
            $('.pcg-portfolio-section').each(function() {
                const $sec = $(this);
                const sectionId = $sec.data('section');
                const selectedIds = [];
                $sec.find('.pcg-hidden-selected-data .pcg-tag-pill').each(function() {
                    selectedIds.push($(this).data('id'));
                });

                sections.push({
                    section_id: sectionId,
                    is_private: 0,
                    visibility_mode: 'selected',
                    selected_ids: selectedIds
                });
            });

            let completed = 0;
            let success = true;

            sections.forEach(sec => {
                $.ajax({
                    url: pcgCreatorData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pl_save_portfolio_settings',
                        nonce: pcgCreatorData.portfolioNonce,
                        ...sec
                    },
                    complete: function() {
                        completed++;
                        if (completed === sections.length) {
                            $btn.prop('disabled', false).html(originalText);
                            notify(success ? "Portafolio actualizado con éxito" : "Error al guardar cambios");
                        }
                    },
                    error: function() {
                        success = false;
                    }
                });
            });
        });
    }

    function loadPortfolioItems(sectionId, page) {
        const $section = $(`.pcg-portfolio-section[data-section="${sectionId}"]`);
        const $grid = $section.find('.pcg-item-grid');
        const $pagination = $section.find('.pcg-pagination');

        $grid.html('<div class="text-center py-10 text-neutral-400"><i data-lucide="loader-2" class="animate-spin" style="display:inline-block; width: 24px; height: 24px;"></i></div>');
        if (typeof lucide !== 'undefined') lucide.createIcons();

        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pl_get_portfolio_items',
                nonce: pcgCreatorData.portfolioNonce,
                type: sectionId,
                paged: page
            },
            success: function(response) {
                if (response.success) {
                    renderGrid($grid, response.data.items, sectionId);
                    renderPagination($pagination, response.data, sectionId);
                    updateSelectedCount($section);
                } else {
                    $grid.html('<div class="text-center py-4 text-neutral-400">Error al cargar datos.</div>');
                }
            },
            error: function() {
                $grid.html('<div class="text-center py-4 text-red-400">Error de conexión.</div>');
            }
        });
    }

    function initPortfolioBulkLoad() {
        if (!pcgCreatorData.portfolioNonce) return;

        const sectionsToLoad = [];
        $('.pcg-portfolio-section').each(function() {
            sectionsToLoad.push($(this).data('section'));
        });

        if (sectionsToLoad.length === 0) {
            window.pcgPortfolioLoaded = true;
            return;
        }

        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pl_get_bulk_portfolio_items',
                nonce: pcgCreatorData.portfolioNonce,
                sections: sectionsToLoad
            },
            success: function(response) {
                if (response.success) {
                    Object.keys(response.data).forEach(sectionId => {
                        const data = response.data[sectionId];
                        const $section = $(`.pcg-portfolio-section[data-section="${sectionId}"]`);
                        const $grid = $section.find('.pcg-item-grid');
                        const $pagination = $section.find('.pcg-pagination');
                        
                        renderGrid($grid, data.items, sectionId);
                        renderPagination($pagination, data, sectionId);
                        updateSelectedCount($section);
                    });
                }
            },
            complete: function() {
                window.pcgPortfolioLoaded = true;
            }
        });
    }

    function renderGrid($grid, items, sectionId) {
        const $section = $(`.pcg-portfolio-section[data-section="${sectionId}"]`);
        const $dataStore = $section.find('.pcg-hidden-selected-data');
        const selectedIds = [];
        $dataStore.find('.pcg-tag-pill').each(function() {
            selectedIds.push(parseInt($(this).data('id')));
        });

        if (items.length === 0) {
            // Hide entire section when there are no items (UX request).
            $section.hide();
            return;
        }

        // Ensure section is visible when it has items.
        $section.show();

        let html = '';
        items.forEach(item => {
            const isChecked = selectedIds.includes(item.id);
            html += `
                <div class="pcg-item-row">
                    <div class="pcg-item-content">
                        <div class="pcg-custom-checkbox ${isChecked ? 'checked' : ''}" data-id="${item.id}" data-title="${item.title}"></div>
                        <span class="pcg-item-title">${item.title}</span>
                    </div>
                    <div class="pcg-item-actions">
                        <button type="button" class="pcg-item-options" data-title="${item.title}">
                            <i data-lucide="more-vertical" style="width:16px;height:16px;"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        $grid.html(html);
        if (typeof lucide !== 'undefined') lucide.createIcons();
        syncSelectAll($section);
    }

    // Options (3-dots) on portfolio items
    $(document).on('click', '.pcg-item-options', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const title = $(this).data('title') || '';
        notify(title ? `Opciones para: ${title}` : 'Opciones');
    });

    function renderPagination($pagination, data, sectionId) {
        const $footer = $pagination.closest('.pcg-portfolio-footer');
        $footer.find('.pcg-page-current').text(data.current_page);
        $footer.find('.pcg-page-total').text(data.total_pages);
        $footer.find('.pcg-prev-page').prop('disabled', data.current_page <= 1);
        $footer.find('.pcg-next-page').prop('disabled', data.current_page >= data.total_pages);
    }

    function updateSelectedCount($section) {
        const selectedCount = $section.find('.pcg-hidden-selected-data .pcg-tag-pill').length;
        $section.find('.pcg-selected-count-value').text(selectedCount);
    }

    function syncSelectAll($section) {
        const $allRows = $section.find('.pcg-item-row .pcg-custom-checkbox');
        const $checkedRows = $section.find('.pcg-item-row .pcg-custom-checkbox.checked');
        const $masterCheck = $section.find('.pcg-select-all-toggle');
        $masterCheck.toggleClass('checked', $allRows.length > 0 && $allRows.length === $checkedRows.length);
    }

    function notify(msg) {
        const $el = $('#pcg-notification');
        if (!$el.length) {
            $('body').append('<div id="pcg-notification"></div>');
        }
        $('#pcg-notification').text(msg).addClass('show');
        setTimeout(() => {
            $('#pcg-notification').removeClass('show');
        }, 3000);
    }

})(jQuery);
