/**
 * Mobile Navigation and Global UI Logic
 */
jQuery(document).ready(function ($) {
    (function initMobileNav() {
        const $nav = $('[data-pcg-mobile-nav]');
        if (!$nav.length) return;

        const $mainBtn = $('#pcg-mobile-mainmenu-btn');
        const $sectionBtn = $('#pcg-mobile-sectionmenu-btn');
        const $overlay = $('#pcg-mobile-overlay');
        const $title = $('#pcg-mobile-page-title');
        const $sectionItems = $('#pcg-mobile-section-items');
        const $footer = $('#pcg-mobile-footer');
        const $actionBtn = $('#pcg-mobile-action-btn');

        function isMobileLayout() {
            return window.matchMedia && window.matchMedia('(max-width: 850px)').matches;
        }

        function syncHeaderOffset() {
            const masthead = document.getElementById('masthead');
            if (!masthead) {
                document.documentElement.style.setProperty('--pcg-mobile-top', '0px');
                return;
            }

            const rect = masthead.getBoundingClientRect();
            const bottom = Math.round(rect.bottom);
            const top = bottom > 0 && bottom < window.innerHeight ? bottom : 0;
            document.documentElement.style.setProperty('--pcg-mobile-top', `${top}px`);
        }

        function syncTopbarHeight() {
            const topbar = $nav.find('.pcg-mobile-topbar').get(0);
            if (!topbar) {
                document.documentElement.style.setProperty('--pcg-mobile-topbar-h', '48px');
                return 48;
            }
            const h = Math.max(0, Math.round(topbar.offsetHeight || 0));
            document.documentElement.style.setProperty('--pcg-mobile-topbar-h', `${h || 48}px`);
            return h || 48;
        }

        function syncStackHeight() {
            const top = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--pcg-mobile-top'), 10) || 0;
            const h = syncTopbarHeight();
            document.documentElement.style.setProperty('--pcg-mobile-stack-h', `${Math.max(0, top + h)}px`);
        }

        function syncDrawerTop() {
            const topbar = $nav.find('.pcg-mobile-topbar').get(0);
            if (!topbar) return;
            const rect = topbar.getBoundingClientRect();
            const top = Math.max(0, Math.round(rect.bottom));
            document.documentElement.style.setProperty('--pcg-mobile-drawer-top', `${top}px`);
        }

        function closeAll() {
            $nav.removeClass('pcg-mobile-main-open pcg-mobile-section-open');
            $mainBtn.attr('aria-expanded', 'false');
            $sectionBtn.attr('aria-expanded', 'false');
            $('#pcg-mobile-main-drawer, #pcg-mobile-section-drawer').attr('aria-hidden', 'true');
            $overlay.attr('aria-hidden', 'true');
        }

        function openMain() {
            syncHeaderOffset();
            syncStackHeight();
            syncDrawerTop();
            $nav.removeClass('pcg-mobile-section-open').addClass('pcg-mobile-main-open');
            $mainBtn.attr('aria-expanded', 'true');
            $sectionBtn.attr('aria-expanded', 'false');
            $('#pcg-mobile-main-drawer').attr('aria-hidden', 'false');
            $('#pcg-mobile-section-drawer').attr('aria-hidden', 'true');
            $overlay.attr('aria-hidden', 'false');
        }

        function openSection() {
            syncHeaderOffset();
            syncStackHeight();
            syncDrawerTop();
            buildSectionMenu();
            $nav.removeClass('pcg-mobile-main-open').addClass('pcg-mobile-section-open');
            $mainBtn.attr('aria-expanded', 'false');
            $sectionBtn.attr('aria-expanded', 'true');
            $('#pcg-mobile-main-drawer').attr('aria-hidden', 'true');
            $('#pcg-mobile-section-drawer').attr('aria-hidden', 'false');
            $overlay.attr('aria-hidden', 'false');
        }

        function setTitle(text) {
            $title.text(String(text || '').trim());
        }

        function baseSectionTitle() {
            const $activeMobile = $('#pcg-mobile-main-drawer .pcg-mobile-drawer-item.active');
            if ($activeMobile.length) return $activeMobile.first().text().trim();

            const $activeSidebar = $('#pcg-creator-sidebar .pcg-creator-nav li.active a');
            if ($activeSidebar.length) return $activeSidebar.first().text().trim();

            const section = String($nav.data('pcgSection') || '').trim();
            return section ? section.toUpperCase() : '';
        }

        function getVisibleFormContext() {
            if ($('#pcg-course-form-section').length && $('#pcg-course-form-section').is(':visible')) return 'course';
            if ($('#pcg-escritos-form-section').length && $('#pcg-escritos-form-section').is(':visible')) return 'escritos';
            if ($('#pcg-specialization-form-section').length && $('#pcg-specialization-form-section').is(':visible')) return 'specialization';
            if ($('#pcg-programa-form-section').length && $('#pcg-programa-form-section').is(':visible')) return 'programa';
            return '';
        }

        function updateTitleFromContext() {
            const ctx = getVisibleFormContext();
            if (!ctx) {
                setTitle(baseSectionTitle());
                return;
            }

            if (ctx === 'course') {
                const label = ($('#pcg-course-title').val() || $('#pcg-current-course-label').text() || '').trim();
                const prefix = label || 'NUEVO CURSO';
                const $seg = $('#pcg-course-form-section .pcg-segment.active');
                const segLabel = $seg.length ? $seg.first().text().trim() : '';
                setTitle(segLabel ? `${prefix} - ${segLabel}` : prefix);
                return;
            }

            if (ctx === 'specialization') {
                const label = ($('#pcg-group-title').val() || $('#pcg-current-specialization-label').text() || '').trim();
                const prefix = label || 'NUEVA ESPECIALIZACIÓN';
                const $seg = $('#pcg-specialization-form-section .pcg-spec-segment.active');
                const segLabel = $seg.length ? $seg.first().text().trim() : '';
                setTitle(segLabel ? `${prefix} - ${segLabel}` : prefix);
                return;
            }

            if (ctx === 'programa') {
                const label = ($('#pcg-programa-title').val() || $('#pcg-current-programa-label').text() || '').trim();
                const prefix = label || 'NUEVO PROGRAMA';
                const $seg = $('#pcg-programa-form-section .pcg-prog-segment.active');
                const segLabel = $seg.length ? $seg.first().text().trim() : '';
                setTitle(segLabel ? `${prefix} - ${segLabel}` : prefix);
                return;
            }

            if (ctx === 'escritos') {
                const label = ($('#pcg-escrito-title').val() || '').trim();
                setTitle(label || 'NUEVO ESCRITO');
            }
        }

        function buildSectionMenu() {
            $sectionItems.empty();

            const ctx = getVisibleFormContext();
            let $sources = $();
            if (ctx === 'course') $sources = $('#pcg-course-form-section .pcg-segment');
            if (ctx === 'specialization') $sources = $('#pcg-specialization-form-section .pcg-spec-segment');
            if (ctx === 'programa') $sources = $('#pcg-programa-form-section .pcg-prog-segment');
            if (!ctx) {
                const section = String($nav.data('pcgSection') || '').trim();
                if (section === 'sales') $sources = $('#pcg-sales-tabs .pcg-segment[data-sales-tab]');
                if (section === 'students') $sources = $('#pcg-students-tabs .pcg-segment[data-students-tab]');
            }

            if (!$sources.length) {
                $sectionBtn.css('display', 'none');
                return;
            }

            $sectionBtn.css('display', 'inline-flex');
            $sources.each(function () {
                const $src = $(this);
                const label = $src.text().trim();
                const active = $src.hasClass('active');

                const $btn = $('<button type="button" class="pcg-mobile-drawer-item"></button>');
                $btn.text(label);
                if (active) $btn.addClass('active');
                $btn.on('click', function () {
                    $src.trigger('click');
                    closeAll();
                    updateTitleFromContext();
                });
                $sectionItems.append($btn);
            });
        }

        function updateFooterAction() {
            if (!isMobileLayout()) {
                $footer.hide();
                return;
            }

            const ctx = getVisibleFormContext();
            if (ctx) {
                $footer.hide();
                return;
            }

            const section = String($nav.data('pcgSection') || '').trim();
            const actions = {
                'create-course': { label: '+ CURSO', target: '#pcg-show-creator-form', visibleRoot: '#pcg-my-courses-section' },
                'mis-escritos': { label: 'ESCRIBIR', target: '#pcg-show-escritos-form', visibleRoot: '#pcg-my-escritos-section' },
                'especializacion': { label: '+ ESPECIALIZACIÓN', target: '#pcg-show-specialization-form', visibleRoot: '#pcg-my-specializations-section' },
                'create-group': { label: '+ PROGRAMA', target: '#pcg-show-programa-form', visibleRoot: '#pcg-my-programas-section' },
                'sales': { label: 'DATE RANGE', event: 'pcg:sales-open-range', visibleRoot: '[data-sales-panel="general"] [data-pcg-sales-dashboard]' },
                'students': { label: 'DATE RANGE', event: 'pcg:students-open-range', visibleRoot: '[data-pcg-students-dashboard]' },
            };

            const cfg = actions[section];
            if (!cfg) {
                $footer.hide();
                return;
            }

            if (section === 'sales') {
                const generalActive = document.querySelector('#pcg-sales-tabs .pcg-segment.active[data-sales-tab="general"]');
                if (!generalActive) {
                    $footer.hide();
                    return;
                }
            }

            const $root = $(cfg.visibleRoot);
            if (!$root.length || !$root.is(':visible')) {
                $footer.hide();
                return;
            }

            if (cfg.target && !$(cfg.target).length) {
                $footer.hide();
                return;
            }

            $actionBtn.text(cfg.label);
            $footer.show();
        }

        $mainBtn.on('click', function (e) {
            if (e && typeof e.preventDefault === 'function') e.preventDefault();
            if ($nav.hasClass('pcg-mobile-main-open')) closeAll();
            else openMain();
        });

        $sectionBtn.on('click', function () {
            if ($nav.hasClass('pcg-mobile-section-open')) closeAll();
            else openSection();
        });

        $overlay.on('click', closeAll);

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeAll();
        });

        $actionBtn.on('click', function () {
            const section = String($nav.data('pcgSection') || '').trim();
            if (section === 'students') {
                window.dispatchEvent(new CustomEvent('pcg:students-open-range'));
                closeAll();
                return;
            }
            if (section === 'sales') {
                window.dispatchEvent(new CustomEvent('pcg:sales-open-range'));
                closeAll();
                return;
            }
            const map = {
                'create-course': '#pcg-show-creator-form',
                'mis-escritos': '#pcg-show-escritos-form',
                'especializacion': '#pcg-show-specialization-form',
                'create-group': '#pcg-show-programa-form',
            };
            const sel = map[section];
            if (sel) $(sel).trigger('click');
            closeAll();
            setTimeout(function () {
                updateTitleFromContext();
                updateFooterAction();
                buildSectionMenu();
            }, 0);
        });

        $(document).on('click', [
            '#pcg-show-creator-form',
            '#pcg-btn-back-to-list',
            '#pcg-btn-cancel-edit',
            '#pcg-show-escritos-form',
            '#pcg-btn-back-to-escritos',
            '#pcg-show-specialization-form',
            '#pcg-btn-back-to-specializations',
            '#pcg-show-programa-form',
            '#pcg-btn-back-to-programas',
        ].join(','), function () {
            const sync = function () {
                updateTitleFromContext();
                updateFooterAction();
                buildSectionMenu();
            };
            // PCG uses fadeIn/fadeOut; run once immediately, then again after animation time.
            setTimeout(sync, 0);
            setTimeout(sync, 520);
        });

        $(document).on('click', [
            '#pcg-course-form-section .pcg-segment',
            '#pcg-specialization-form-section .pcg-spec-segment',
            '#pcg-programa-form-section .pcg-prog-segment',
        ].join(','), function () {
            setTimeout(function () {
                updateTitleFromContext();
                buildSectionMenu();
            }, 0);
        });

        $(document).on('input', [
            '#pcg-course-title',
            '#pcg-group-title',
            '#pcg-programa-title',
            '#pcg-escrito-title',
        ].join(','), function () {
            setTimeout(updateTitleFromContext, 0);
        });

        $(window).on('resize orientationchange', function () {
            setTimeout(function () {
                syncHeaderOffset();
                syncStackHeight();
                syncDrawerTop();
                updateFooterAction();
                // Keep course actions in the right place when switching between narrow/wide layouts.
                const $courseForm = $('#pcg-course-form-section');
                if ($courseForm.length && $courseForm.is(':visible')) {
                    const mode = $courseForm.find('.pcg-segment.active').data('value') || 'curso';
                    if (typeof placeCourseActions === 'function') {
                        placeCourseActions(mode);
                    }
                }
            }, 0);
        });

        window.addEventListener('pcg:sales-tab-changed', function () {
            setTimeout(function () {
                updateFooterAction();
                buildSectionMenu();
                updateTitleFromContext();
            }, 0);
        });

        // Keep the fixed topbar locked under BuddyBoss header while scrolling.
        (function bindScrollSync() {
            let raf = 0;
            const onScroll = () => {
                if (!isMobileLayout()) return;
                if (raf) return;
                raf = window.requestAnimationFrame(() => {
                    raf = 0;
                    syncHeaderOffset();
                    syncStackHeight();
                    if ($nav.hasClass('pcg-mobile-main-open') || $nav.hasClass('pcg-mobile-section-open')) {
                        syncDrawerTop();
                    }
                });
            };
            window.addEventListener('scroll', onScroll, { passive: true });
        })();

        function observeVisibility(selector) {
            if (!window.MutationObserver) return;
            const el = document.querySelector(selector);
            if (!el) return;

            const sync = function () {
                updateTitleFromContext();
                updateFooterAction();
                buildSectionMenu();
            };

            const obs = new MutationObserver(function () {
                // Defer to allow jQuery fade to finish toggling display/opacity.
                setTimeout(sync, 0);
            });
            obs.observe(el, { attributes: true, attributeFilter: ['style', 'class'] });
        }

        observeVisibility('#pcg-course-form-section');
        observeVisibility('#pcg-my-courses-section');
        observeVisibility('#pcg-specialization-form-section');
        observeVisibility('#pcg-my-specializations-section');
        observeVisibility('#pcg-programa-form-section');
        observeVisibility('#pcg-my-programas-section');
        observeVisibility('#pcg-escritos-form-section');
        observeVisibility('#pcg-my-escritos-section');

        // Initial render
        closeAll();
        syncHeaderOffset();
        syncStackHeight();
        syncDrawerTop();
        updateTitleFromContext();
        buildSectionMenu();
        updateFooterAction();
    })();
});
