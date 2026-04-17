/**
 * Course Creator Dashboard JS
 */
jQuery(document).ready(function ($) {
    console.log('Politeia Course Creator Dashboard Initialized');

    function t(key) {
        try {
            return (pcgCreatorData && pcgCreatorData.i18n && pcgCreatorData.i18n[key]) ? pcgCreatorData.i18n[key] : key;
        } catch (_) {
            return key;
        }
    }

    function formatPercent(value) {
        const num = typeof value === 'number' ? value : Number(value || 0);
        if (!isFinite(num)) return '0';
        const rounded = Math.round(num * 100) / 100;
        if (Math.abs(rounded - Math.round(rounded)) < 0.001) {
            return String(Math.round(rounded));
        }
        return String(rounded);
    }

    // Pending approvals index for the current user (receiver-side).
    // Structure: { group: { [containerId]: { snapshot_id, profit_percentage, created_by_name } }, program: { ... } }
    let pendingApprovalsIndex = { group: {}, program: {} };

    // ───────────────────────────────────────────────────────────
    // Mobile navigation (<= 850px)
    // ───────────────────────────────────────────────────────────
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
                    placeCourseActions(mode);
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

    // ───────────────────────────────────────────────────────────
    // Unified Learning Meta (Categories + Tags)
    // ───────────────────────────────────────────────────────────
    const plLearningMeta = (function () {
        const cache = {
            loaded: false,
            loading: null,
            categories: [],
            categoryList: [],
            tags: [],
            tagsById: new Map(),
        };

        const state = {
            course: { categoryIds: [], tags: [] },
            group: { categoryIds: [], tags: [] },
            programa: { categoryIds: [], tags: [] },
        };

        const dom = {
            course: {
                catL1: '#pcg-course-meta-cat-l1',
                catL2: '#pcg-course-meta-cat-l2',
                catL3: '#pcg-course-meta-cat-l3',
                tagChips: '#pcg-course-meta-tag-chips',
                tagInput: '#pcg-course-meta-tag-input',
                tagSuggestions: '#pcg-course-meta-tag-suggestions',
            },
            group: {
                catL1: '#pcg-group-meta-cat-l1',
                catL2: '#pcg-group-meta-cat-l2',
                catL3: '#pcg-group-meta-cat-l3',
                tagChips: '#pcg-group-meta-tag-chips',
                tagInput: '#pcg-group-meta-tag-input',
                tagSuggestions: '#pcg-group-meta-tag-suggestions',
            },
            programa: {
                catL1: '#pcg-programa-meta-cat-l1',
                catL2: '#pcg-programa-meta-cat-l2',
                catL3: '#pcg-programa-meta-cat-l3',
                tagChips: '#pcg-programa-meta-tag-chips',
                tagInput: '#pcg-programa-meta-tag-input',
                tagSuggestions: '#pcg-programa-meta-tag-suggestions',
            },
        };

        function ensureLoaded() {
            if (cache.loaded) {
                return $.Deferred().resolve().promise();
            }
            if (cache.loading) {
                return cache.loading;
            }

            cache.loading = $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_get_learning_meta_terms',
                    nonce: pcgCreatorData.nonce
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    return;
                }

                cache.categories = Array.isArray(response.data && response.data.categories) ? response.data.categories : [];
                cache.tags = Array.isArray(response.data && response.data.tags) ? response.data.tags : [];
                cache.tagsById = new Map();
                cache.tags.forEach(t => {
                    const id = Number(t.id) || 0;
                    if (!id) return;
                    cache.tagsById.set(id, { id, name: String(t.name || ''), slug: String(t.slug || '') });
                });

                cache.categoryList = buildCategoryList(cache.categories);
                cache.loaded = true;
            }).always(function () {
                cache.loading = null;
            });

            return cache.loading;
        }

        function buildCategoryList(categories) {
            const nodes = new Map();
            const children = new Map();
            (Array.isArray(categories) ? categories : []).forEach(c => {
                const id = Number(c.id) || 0;
                const parent = Number(c.parent) || 0;
                const name = String(c.name || '');
                if (!id || !name) return;
                nodes.set(id, { id, parent, name });
                if (!children.has(parent)) children.set(parent, []);
                children.get(parent).push(id);
            });

            // Sort children by name.
            children.forEach((ids) => {
                ids.sort((a, b) => {
                    const na = nodes.get(a)?.name || '';
                    const nb = nodes.get(b)?.name || '';
                    return na.localeCompare(nb);
                });
            });

            const out = [];
            const walk = (parentId, depth) => {
                const ids = children.get(parentId) || [];
                ids.forEach(id => {
                    const node = nodes.get(id);
                    if (!node) return;
                    out.push({ ...node, depth });
                    walk(id, depth + 1);
                });
            };

            walk(0, 0);
            // Also include any orphan terms (bad data) at root.
            nodes.forEach((node) => {
                if (node.parent !== 0 && !nodes.has(node.parent)) {
                    out.push({ ...node, depth: 0 });
                }
            });

            return out;
        }

        function categoriesIndex() {
            const byId = new Map();
            const byParent = new Map();
            (Array.isArray(cache.categories) ? cache.categories : []).forEach(c => {
                const id = Number(c.id) || 0;
                const parent = Number(c.parent) || 0;
                const name = String(c.name || '');
                const slug = String(c.slug || '');
                if (!id || !name) return;
                const node = { id, parent, name, slug };
                byId.set(id, node);
                if (!byParent.has(parent)) byParent.set(parent, []);
                byParent.get(parent).push(node);
            });
            byParent.forEach(list => list.sort((a, b) => a.name.localeCompare(b.name)));
            return { byId, byParent };
        }

        function computePath(leafId, byId) {
            const leaf = byId.get(Number(leafId) || 0);
            if (!leaf) return { l1: 0, l2: 0, l3: 0 };

            const p2 = leaf.parent ? byId.get(leaf.parent) : null;
            const p1 = p2 && p2.parent ? byId.get(p2.parent) : (leaf.parent ? byId.get(leaf.parent) : null);

            // If leaf is level1.
            if (!leaf.parent) {
                return { l1: leaf.id, l2: 0, l3: 0 };
            }

            // If leaf is level2 (parent is root).
            if (p2 && !p2.parent) {
                return { l1: p2.id, l2: leaf.id, l3: 0 };
            }

            // Leaf is level3+.
            if (p2 && p1) {
                return { l1: p1.id, l2: p2.id, l3: leaf.id };
            }

            return { l1: 0, l2: 0, l3: leaf.id };
        }

        function setSingleCategory(entity, termId) {
            const id = Number(termId) || 0;
            state[entity].categoryIds = id ? [id] : [];
        }

        function renderCategoryLevel($wrap, nodes, name, selectedId) {
            if (!$wrap || !$wrap.length) return;
            if (!Array.isArray(nodes) || nodes.length === 0) {
                $wrap.empty();
                return;
            }
            const html = nodes.map(n => {
                const checked = Number(selectedId) === Number(n.id) ? 'checked' : '';
                return `
                    <label class="pcg-meta-cat-option">
                        <input type="radio" name="${name}" value="${n.id}" ${checked} />
                        <span class="pcg-meta-cat-option__label">${n.name}</span>
                    </label>
                `;
            }).join('');
            $wrap.html(html);
        }

        function renderCategories(entity) {
            const cfg = dom[entity];
            if (!cfg) return;
            const $l1 = $(cfg.catL1);
            if (!$l1.length) return;

            const $l2 = $(cfg.catL2);
            const $l3 = $(cfg.catL3);

            const { byId, byParent } = categoriesIndex();

            const allowedRoots = new Set(['humanidades', 'ciencias-y-pensamiento-formal', 'saberes-practicos']);
            const roots = (byParent.get(0) || []).filter(n => allowedRoots.has(String(n.slug || '')));

            const leafId = Number((state[entity]?.categoryIds || [])[0] || 0) || 0;
            const path = leafId ? computePath(leafId, byId) : { l1: 0, l2: 0, l3: 0 };

            const l2Nodes = path.l1 ? (byParent.get(path.l1) || []) : [];
            const l3Nodes = path.l2 ? (byParent.get(path.l2) || []) : [];

            renderCategoryLevel($l1, roots, `pcg_meta_${entity}_l1`, path.l1);
            renderCategoryLevel($l2, l2Nodes, `pcg_meta_${entity}_l2`, path.l2);
            renderCategoryLevel($l3, l3Nodes, `pcg_meta_${entity}_l3`, path.l3);
        }

        function renderTags(entity) {
            const cfg = dom[entity];
            if (!cfg) return;
            const $chips = $(cfg.tagChips);
            if (!$chips.length) return;

            const tags = state[entity]?.tags || [];
            const html = tags.map(tag => `
                <span class="pcg-meta-chip" data-tag-id="${tag.id}">
                    <span class="pcg-meta-chip__label">${tag.name}</span>
                    <button type="button" class="pcg-meta-chip__remove" aria-label="${t('remove')}" title="${t('remove')}">&times;</button>
                </span>
            `).join('');
            $chips.html(html);
        }

        function hideSuggestions($wrap) {
            $wrap.hide().empty();
        }

        function showTagSuggestions(entity, query) {
            const cfg = dom[entity];
            if (!cfg) return;

            const $wrap = $(cfg.tagSuggestions);
            if (!$wrap.length) return;

            const q = String(query || '').trim().toLowerCase();
            if (!q) {
                hideSuggestions($wrap);
                return;
            }

            const selected = new Set((state[entity]?.tags || []).map(tg => Number(tg.id)));
            const matches = (cache.tags || [])
                .filter(tg => {
                    const name = String(tg.name || '').toLowerCase();
                    return name.includes(q);
                })
                .slice(0, 12)
                .map(tg => ({
                    id: Number(tg.id) || 0,
                    name: String(tg.name || ''),
                    slug: String(tg.slug || '')
                }))
                .filter(tg => tg.id && !selected.has(tg.id));

            const exact = (cache.tags || []).some(tg => String(tg.name || '').trim().toLowerCase() === q);
            const createRow = !exact ? `
                <div class="pcg-meta-suggestion pcg-meta-suggestion--create" data-create-name="${encodeURIComponent(query)}">
                    ${t('createTag') || 'Crear etiqueta'}
                    <span class="pcg-meta-suggestion__hint">"${query}"</span>
                </div>
            ` : '';

            const rows = matches.map(tg => `
                <div class="pcg-meta-suggestion" data-tag-id="${tg.id}">
                    ${tg.name}
                </div>
            `).join('');

            const html = (rows || createRow) ? `${createRow}${rows}` : '';
            if (!html) {
                hideSuggestions($wrap);
                return;
            }

            $wrap.html(html).show();
        }

        function parseTokens(raw) {
            const str = String(raw || '');
            return str
                .split(',')
                .map(s => s.trim())
                .filter(Boolean);
        }

        function addTokens(entity, raw) {
            const tokens = parseTokens(raw);
            if (!tokens.length) {
                return $.Deferred().resolve().promise();
            }

            // Create tags sequentially to keep UI stable and avoid request bursts.
            return tokens.reduce((p, token) => {
                return p.then(() => createTagAndAdd(entity, token));
            }, Promise.resolve());
        }

        function addTag(entity, tag) {
            const id = Number(tag && tag.id) || 0;
            const name = String(tag && tag.name || '').trim();
            if (!id || !name) return;

            const current = state[entity]?.tags || [];
            if (current.some(tg => Number(tg.id) === id)) return;
            state[entity].tags = [...current, { id, name, slug: String(tag.slug || '') }];
            renderTags(entity);
        }

        function removeTag(entity, tagId) {
            const id = Number(tagId) || 0;
            if (!id) return;
            state[entity].tags = (state[entity]?.tags || []).filter(tg => Number(tg.id) !== id);
            renderTags(entity);
        }

        function setSelection(entity, categoryIds = [], tags = []) {
            const ids = (Array.isArray(categoryIds) ? categoryIds : []).map(x => Number(x)).filter(Boolean);
            state[entity].categoryIds = ids.length ? [ids[0]] : [];

            const normalizedTags = (Array.isArray(tags) ? tags : [])
                .map(tg => ({
                    id: Number(tg.id || tg) || 0,
                    name: String(tg.name || ''),
                    slug: String(tg.slug || ''),
                }))
                .filter(tg => tg.id && tg.name);

            state[entity].tags = normalizedTags;
            if (cache.loaded) {
                renderCategories(entity);
                renderTags(entity);
            }
        }

        function reset(entity) {
            setSelection(entity, [], []);
            const cfg = dom[entity];
            if (cfg) {
                $(cfg.tagInput).val('');
                hideSuggestions($(cfg.tagSuggestions));
            }
        }

        function getPayload(entity) {
            return {
                category_ids: (state[entity]?.categoryIds || []).map(x => Number(x)).filter(Boolean),
                tag_ids: (state[entity]?.tags || []).map(tg => Number(tg.id)).filter(Boolean),
            };
        }

        function bindEntity(entity) {
            const cfg = dom[entity];
            if (!cfg) return;

            // Categories (cascading radios).
            $(document).on('change', `${cfg.catL1} input[type="radio"]`, function () {
                const id = Number($(this).val()) || 0;
                if (!id) return;
                setSingleCategory(entity, id);
                renderCategories(entity);
            });

            $(document).on('change', `${cfg.catL2} input[type="radio"]`, function () {
                const id = Number($(this).val()) || 0;
                if (!id) return;
                setSingleCategory(entity, id);
                renderCategories(entity);
            });

            $(document).on('change', `${cfg.catL3} input[type="radio"]`, function () {
                const id = Number($(this).val()) || 0;
                if (!id) return;
                setSingleCategory(entity, id);
                renderCategories(entity);
            });

            // Tags.
            $(document).on('input focus', cfg.tagInput, function () {
                const val = $(this).val();
                showTagSuggestions(entity, val);
            });
            $(document).on('keydown', cfg.tagInput, function (e) {
                if (e.key === 'Escape') {
                    $(this).val('');
                    hideSuggestions($(cfg.tagSuggestions));
                }
                if (e.key === 'Enter' || e.key === ',') {
                    const val = String($(this).val() || '');
                    const hasComma = e.key === ',' || val.includes(',');
                    const tokens = parseTokens(val);
                    if (!tokens.length) {
                        if (e.key === ',') {
                            e.preventDefault();
                        }
                        return;
                    }

                    e.preventDefault();
                    // If user hit Enter, treat whole input as token list; if comma, consume tokens up to commas.
                    addTokens(entity, val).then(() => {
                        $(this).val('');
                        hideSuggestions($(cfg.tagSuggestions));
                    });
                    return;
                }
            });

            // Also support pasting/typing multiple comma-separated tags.
            $(document).on('input', cfg.tagInput, function () {
                const val = String($(this).val() || '');
                if (!val.includes(',')) {
                    return;
                }
                // Consume immediately.
                addTokens(entity, val).then(() => {
                    $(this).val('');
                    hideSuggestions($(cfg.tagSuggestions));
                });
            });

            $(document).on('click', `${cfg.tagSuggestions} .pcg-meta-suggestion[data-tag-id]`, function () {
                const id = Number($(this).attr('data-tag-id')) || 0;
                if (!id) return;
                const tag = cache.tagsById.get(id);
                if (tag) {
                    addTag(entity, tag);
                }
                $(cfg.tagInput).val('').trigger('input');
                hideSuggestions($(cfg.tagSuggestions));
            });

            $(document).on('click', `${cfg.tagSuggestions} .pcg-meta-suggestion--create`, function () {
                const raw = $(this).attr('data-create-name') || '';
                const name = decodeURIComponent(raw);
                if (!name) return;
                createTagAndAdd(entity, name).always(() => {
                    $(cfg.tagInput).val('');
                    hideSuggestions($(cfg.tagSuggestions));
                });
            });

            $(document).on('click', `${cfg.tagChips} .pcg-meta-chip__remove`, function () {
                const id = Number($(this).closest('.pcg-meta-chip').attr('data-tag-id')) || 0;
                removeTag(entity, id);
            });
        }

        function createTagAndAdd(entity, name) {
            return $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_create_learning_tag',
                    nonce: pcgCreatorData.nonce,
                    name: name
                }
            }).done(function (response) {
                if (!response || !response.success) return;
                const tag = response.data;
                const id = Number(tag.id) || 0;
                if (!id) return;

                const normalized = { id, name: String(tag.name || name), slug: String(tag.slug || '') };
                if (!cache.tagsById.has(id)) {
                    cache.tagsById.set(id, normalized);
                    cache.tags.push(normalized);
                }
                addTag(entity, normalized);
            });
        }

        // Global click: close any open suggestion boxes.
        $(document).on('click', function (e) {
            const $t = $(e.target);
            const inside = $t.closest('.pcg-meta-tags').length > 0;
            if (inside) return;
            Object.keys(dom).forEach(k => {
                hideSuggestions($(dom[k].tagSuggestions));
            });
        });

        // Bind all entities once (if present).
        ['course', 'group', 'programa'].forEach(bindEntity);

        function render(entity) {
            return ensureLoaded().done(function () {
                renderCategories(entity);
                renderTags(entity);
            });
        }

        return {
            ensureLoaded,
            render,
            setSelection,
            reset,
            getPayload,
        };
    })();

    // ───────────────────────────────────────────────────────────
    // Specialization (LearnDash Group) Creator UI
    // ───────────────────────────────────────────────────────────
    (function initSpecializationCreator() {
        if (!$('#pcg-show-specialization-form').length) {
            return;
        }

        let currentGroupId = 0;
        let selectedCourseIds = [];
        let cachedCourses = [];
        let allCoursesPage = 1;
        const allCoursesPerPage = 10;
        let orderRequired = false;

        function resetSpecializationForm() {
            currentGroupId = 0;
            selectedCourseIds = [];
            allCoursesPage = 1;
            $('#pcg-current-group-id').val(0);
            $('#pcg-group-title').val('');
            $('#pcg-group-description').val('');
            $('#pcg-group-price').val('');
            $('#pcg-group-price-free-indicator').hide();
            $('#pcg-current-specialization-label').text('').hide();
            $('#pcg-spec-course-search').val('');

            $('.pcg-spec-segment').removeClass('active');
            $('.pcg-spec-segment[data-value="especializacion"]').addClass('active');
            $('#pcg-spec-mode-especializacion').show();
            $('#pcg-spec-mode-cursos').hide();
            $('#pcg-spec-mode-meta').hide();

            $('#pcg-spec-all-courses').html(`
                <div class="pcg-loading-placeholder">
                    <span class="dashicons dashicons-update spin"></span>
                    <p>${t('loadingCourses')}</p>
                </div>
            `);
            $('#pcg-spec-courses-pagination').hide();

            $('#pcg-spec-added-courses').html(`
                <div class="pcg-loading-placeholder">
                    <span class="dashicons dashicons-update spin"></span>
                    <p>${t('loadingCourses')}</p>
                </div>
            `);
            $('#pcg-spec-order-required').prop('checked', false);
            orderRequired = false;

            // Teachers tab
            const seed = getCurrentUserTeacherSeed();
            const $list = $('#pcg-group-teachers-list');
            if ($list.length) {
                populateTeachersList($list, [], seed);
            }

            plLearningMeta.reset('group');
        }

        function renderAddedCourses() {
            const $wrap = $('#pcg-spec-added-courses');

            if (!cachedCourses || cachedCourses.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noCoursesToAssign')}</p>`);
                return;
            }

            if (!selectedCourseIds || selectedCourseIds.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noCoursesAddedYet')}</p>`);
                return;
            }

            const items = selectedCourseIds
                .map(id => cachedCourses.find(c => Number(c.id) === Number(id)))
                .filter(Boolean);

            if (items.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noCoursesAddedYet')}</p>`);
                return;
            }

            const html = items.map(c => `
                <div class="pcg-spec-added-row" data-id="${c.id}">
                    <div class="pcg-spec-added-title">${c.title}</div>
                    <button type="button" class="pcg-btn-icon pcg-spec-remove-course" title="${t('remove')}">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `).join('');

            $wrap.html(html);
            initAddedCoursesSortable();
        }

        function initAddedCoursesSortable() {
            const $wrap = $('#pcg-spec-added-courses');
            if (!$wrap.length || !$.fn.sortable) {
                return;
            }

            $wrap.addClass('pcg-sort-enabled');

            // Destroy if already initialized
            try {
                if ($wrap.data('ui-sortable')) {
                    $wrap.sortable('destroy');
                }
            } catch (_) { }

            $wrap.sortable({
                axis: 'y',
                // Use a body-appended clone helper to avoid cursor offset issues caused by
                // transformed/positioned ancestors in the front-end layout.
                helper: 'clone',
                appendTo: 'body',
                containment: 'document',
                placeholder: 'pcg-sortable-placeholder',
                forcePlaceholderSize: true,
                cancel: 'button, .pcg-spec-remove-course',
                opacity: 0.9,
                tolerance: 'pointer',
                zIndex: 999999,
                start: function (event, ui) {
                    ui.helper.css({
                        width: ui.item.outerWidth(),
                        boxSizing: 'border-box'
                    });
                },
                update: function () {
                    const ids = [];
                    $wrap.find('.pcg-spec-added-row').each(function () {
                        const id = Number($(this).attr('data-id')) || 0;
                        if (id) ids.push(id);
                    });
                    selectedCourseIds = ids;
                }
            });
        }

        function addCourseToSpecialization(courseId) {
            const id = Number(courseId) || 0;
            if (!id) return;
            if (!selectedCourseIds.includes(id)) {
                selectedCourseIds.push(id);
            }

            const course = (cachedCourses || []).find(c => Number(c.id) === id);
            if (course && course.author_id) {
                ensureTeacherForUser($('#pcg-group-teachers-list'), {
                    id: Number(course.author_id),
                    name: course.author_name || '',
                    email: course.author_email || '',
                    avatar: course.author_avatar || ''
                });
            }

            $('#pcg-spec-course-search').val('');
            renderAddedCourses();
            renderAllCourses();
            syncQuizCoursePicker(cachedCourses);
        }

        function removeCourseFromSpecialization(courseId) {
            const id = Number(courseId) || 0;
            if (!id) return;
            selectedCourseIds = selectedCourseIds.filter(x => Number(x) !== id);
            renderAddedCourses();
            renderAllCourses();
            syncQuizCoursePicker(cachedCourses);
        }

        function getFilteredCourses() {
            const q = String($('#pcg-spec-course-search').val() || '').trim().toLowerCase();
            if (!q) {
                return cachedCourses || [];
            }
            return (cachedCourses || []).filter(c => String(c.title || '').toLowerCase().includes(q));
        }

        function renderAllCourses() {
            const $wrap = $('#pcg-spec-all-courses');
            const courses = getFilteredCourses();

            if (!courses || courses.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noCourses')}</p>`);
                $('#pcg-spec-courses-pagination').hide();
                return;
            }

            const totalPages = Math.max(1, Math.ceil(courses.length / allCoursesPerPage));
            if (allCoursesPage > totalPages) {
                allCoursesPage = totalPages;
            }
            if (allCoursesPage < 1) {
                allCoursesPage = 1;
            }

            const start = (allCoursesPage - 1) * allCoursesPerPage;
            const pageItems = courses.slice(start, start + allCoursesPerPage);
            const selected = new Set(selectedCourseIds.map(id => Number(id)));

            const html = pageItems.map(c => {
                const isAdded = selected.has(Number(c.id));
                return `
                    <div class="pcg-spec-all-row" data-id="${c.id}">
                        <div class="pcg-spec-all-title">${c.title}</div>
                        <button type="button" class="pcg-spec-add-btn" ${isAdded ? 'disabled' : ''}>
                            ${isAdded ? t('added') : t('add')}
                        </button>
                    </div>
                `;
            }).join('');

            $wrap.html(html);

            if (courses.length > allCoursesPerPage) {
                $('#pcg-spec-courses-pagination').show();
                $('#pcg-spec-page-info').text(`${allCoursesPage} / ${totalPages}`);
                $('#pcg-spec-page-prev').prop('disabled', allCoursesPage <= 1);
                $('#pcg-spec-page-next').prop('disabled', allCoursesPage >= totalPages);
            } else {
                $('#pcg-spec-courses-pagination').hide();
            }
        }

        function loadCoursesForSpecialization() {
            return $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_get_published_courses',
                    nonce: pcgCreatorData.nonce
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    $('#pcg-spec-all-courses').html(`<p class="pcg-empty-msg">${t('failedToLoadCourses')}</p>`);
                    $('#pcg-spec-added-courses').html(`<p class="pcg-empty-msg">${t('failedToLoadCourses')}</p>`);
                    $('#pcg-spec-courses-pagination').hide();
                    return;
                }

                const courses = response.data || [];
                cachedCourses = courses;
                renderAddedCourses();
                renderAllCourses();
                syncQuizCoursePicker(courses);
            });
        }

        function openSpecializationFormForEdit(groupId) {
            const id = Number(groupId) || 0;
            if (!id) return;

            $('#pcg-my-specializations-section').fadeOut(200, function () {
                resetSpecializationForm();
                $('#pcg-specialization-form-section').show();
                $('#pcg-specialization-form-section').append(`
                    <div id="pcg-spec-edit-loading" class="pcg-loading-placeholder">
                        <span class="dashicons dashicons-update spin"></span>
                        <p>${t('loadingSpecialization')}</p>
                    </div>
                `);

                $.ajax({
                    url: pcgCreatorData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pcg_get_specialization_for_edit',
                        nonce: pcgCreatorData.nonce,
                        group_id: id
                    }
                }).done(function (response) {
                    $('#pcg-spec-edit-loading').remove();
                    if (!response || !response.success) {
                        alert(t('errorLoadingSpecialization'));
                        $('#pcg-specialization-form-section').hide();
                        $('#pcg-my-specializations-section').show();
                        return;
                    }

                    const data = response.data;
                    currentGroupId = Number(data.id) || 0;
                    selectedCourseIds = (data.course_ids || []).map(x => Number(x));
                    orderRequired = Boolean(data.order_required);

                    $('#pcg-current-group-id').val(currentGroupId);
                    $('#pcg-group-title').val(data.title || '');
                    $('#pcg-group-description').val(data.description || '');
                    $('#pcg-group-price').val(data.price || '');
                    $('#pcg-spec-order-required').prop('checked', orderRequired);

                    plLearningMeta.setSelection('group', data.category_ids || [], data.tags || []);

                    if (data.title) {
                        $('#pcg-current-specialization-label').text(data.title).show();
                    }

                    const priceNum = parseFloat(String(data.price || '').replace(',', '.')) || 0;
                    if (priceNum === 0) {
                        $('#pcg-group-price-free-indicator').show();
                    }

                    // Default to ESPECIALIZACIÓN tab after load.
                    $('.pcg-spec-segment').removeClass('active');
                    $('.pcg-spec-segment[data-value="especializacion"]').addClass('active');
                    $('#pcg-spec-mode-especializacion').show();
                    $('#pcg-spec-mode-cursos').hide();
                    $('#pcg-spec-mode-meta').hide();

                    // Teachers
                    populateTeachersList($('#pcg-group-teachers-list'), data.teachers || [], {
                        id: Number(data.author_id || 0),
                        name: data.author_name || '',
                        avatar: data.author_avatar || ''
                    });
                    // Ensure all included course authors are present as participants.
                    (data.included_authors || []).forEach(a => {
                        ensureTeacherForUser($('#pcg-group-teachers-list'), {
                            id: Number(a.id),
                            name: a.name || '',
                            email: a.email || '',
                            avatar: a.avatar || ''
                        });
                    });

                    loadCoursesForSpecialization().done(function () {
                        // Ensure all selected course authors are included as participants.
                        (selectedCourseIds || []).forEach(cid => {
                            const course = (cachedCourses || []).find(c => Number(c.id) === Number(cid));
                            if (course && course.author_id) {
                                ensureTeacherForUser($('#pcg-group-teachers-list'), {
                                    id: Number(course.author_id),
                                    name: course.author_name || '',
                                    email: course.author_email || '',
                                    avatar: course.author_avatar || ''
                                });
                            }
                        });
                    });
                }).fail(function () {
                    $('#pcg-spec-edit-loading').remove();
                    alert(t('errorLoadingSpecializationGeneric'));
                    $('#pcg-specialization-form-section').hide();
                    $('#pcg-my-specializations-section').show();
                });
            });
        }

        function syncQuizCoursePicker() { }

        function getSpecializationPayload() {
            const meta = plLearningMeta.getPayload('group');
            return {
                id: currentGroupId,
                title: $('#pcg-group-title').val(),
                description: $('#pcg-group-description').val(),
                price: $('#pcg-group-price').val(),
                course_ids: selectedCourseIds,
                order_required: orderRequired ? 1 : 0,
                teachers: collectTeachers($('#pcg-group-teachers-list')),
                split_locked: Boolean($('#pcg-group-teachers-list').data('splitLocked')),
                category_ids: meta.category_ids,
                tag_ids: meta.tag_ids,
            };
        }

        // Open form
        $('#pcg-show-specialization-form').on('click', function () {
            $('#pcg-my-specializations-section').fadeOut(300, function () {
                resetSpecializationForm();
                $('#pcg-specialization-form-section').fadeIn(400);
                loadCoursesForSpecialization();
            });
        });

        // Back to list
        $('#pcg-btn-back-to-specializations').on('click', function () {
            $('#pcg-specialization-form-section').fadeOut(300, function () {
                $('#pcg-my-specializations-section').fadeIn();
                resetSpecializationForm();
            });
        });

        // Segment switcher
        $(document).on('click', '.pcg-spec-segment', function () {
            $('.pcg-spec-segment').removeClass('active');
            $(this).addClass('active');

            const mode = $(this).data('value');
            $('#pcg-spec-mode-especializacion').hide();
            $('#pcg-spec-mode-cursos').hide();
            $('#pcg-spec-mode-meta').hide();

            if (mode === 'especializacion') {
                $('#pcg-spec-mode-especializacion').fadeIn(200);
            } else if (mode === 'cursos') {
                $('#pcg-spec-mode-cursos').fadeIn(200);
                loadCoursesForSpecialization();
            } else if (mode === 'meta') {
                $('#pcg-spec-mode-meta').fadeIn(200);
                plLearningMeta.render('group');
            }
        });

        // Update label as user types
        $('#pcg-group-title').on('input', function () {
            const title = $(this).val();
            if (title) {
                $('#pcg-current-specialization-label').text(title).show();
            } else {
                $('#pcg-current-specialization-label').hide();
            }
        });

        // Free indicator
        $('#pcg-group-price').on('input change', function () {
            const price = parseFloat($(this).val()) || 0;
            if (price === 0) {
                $('#pcg-group-price-free-indicator').fadeIn(200);
            } else {
                $('#pcg-group-price-free-indicator').fadeOut(200);
            }
        });

        // Order required toggle
        $('#pcg-spec-order-required').on('change', function () {
            orderRequired = $(this).is(':checked');
        });

        // Filter + pagination in "Todos mis cursos"
        $('#pcg-spec-course-search').on('input', function () {
            allCoursesPage = 1;
            renderAllCourses();
        });

        $('#pcg-spec-course-search').on('keydown', function (e) {
            if (e.key === 'Escape') {
                $(this).val('');
                allCoursesPage = 1;
                renderAllCourses();
            }
        });

        $('#pcg-spec-page-prev').on('click', function () {
            allCoursesPage = Math.max(1, allCoursesPage - 1);
            renderAllCourses();
        });

        $('#pcg-spec-page-next').on('click', function () {
            allCoursesPage = allCoursesPage + 1;
            renderAllCourses();
        });

        $(document).on('click', '.pcg-spec-add-btn', function () {
            const courseId = $(this).closest('.pcg-spec-all-row').data('id');
            addCourseToSpecialization(courseId);
        });

        $(document).on('click', '.pcg-spec-remove-course', function () {
            const courseId = $(this).closest('.pcg-spec-added-row').data('id');
            removeCourseFromSpecialization(courseId);
        });

        // Save specialization
        $('.pcg-btn-save-specialization').on('click', function () {
            const $btn = $(this);
            const payload = getSpecializationPayload();

            if (!payload.title) {
                alert(t('pleaseEnterSpecializationName'));
                return;
            }

            $btn.addClass('loading').prop('disabled', true);

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_save_specialization',
                    nonce: pcgCreatorData.nonce,
                    group_data: payload
                },
                success: function (response) {
                    $btn.removeClass('loading');
                    if (response && response.success) {
                        currentGroupId = response.data.group_id;
                        $('#pcg-current-group-id').val(currentGroupId);
                        if (response.data && response.data.snapshot_status === 'pending') {
                            alert(t('approvalRequestSent'));
                        }
                        $btn.addClass('success');
                        refreshActiveList();
                        setTimeout(() => {
                            $btn.prop('disabled', false).removeClass('success');
                        }, 2000);
                    } else {
                        alert(t('errorPrefix') + (response && response.data && response.data.message ? response.data.message : t('unknownError')));
                        $btn.prop('disabled', false);
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    alert(t('errorSavingSpecialization'));
                    $btn.prop('disabled', false);
                }
            });
        });

        // Edit / Delete buttons from cards
        $(document).on('click', '.pcg-btn-edit-specialization', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const groupId = $(this).closest('.pcg-specialization-card').data('id');
            openSpecializationFormForEdit(groupId);
        });

        $(document).on('click', '.pcg-btn-delete-specialization', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const groupId = $(this).closest('.pcg-specialization-card').data('id');
            if (!groupId) return;
            if (!confirm(t('confirmDeleteSpecialization'))) return;

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_delete_specialization',
                    nonce: pcgCreatorData.nonce,
                    group_id: groupId
                },
                success: function (response) {
                    if (response && response.success) {
                        refreshActiveList();
                    } else {
                        alert(t('errorPrefix') + (response && response.data && response.data.message ? response.data.message : t('couldNotDelete')));
                    }
                },
                error: function () {
                    alert(t('errorDeletingSpecialization'));
                }
            });
        });
    })();

    // ───────────────────────────────────────────────────────────
    // Programas (course_program) Creator UI
    // ───────────────────────────────────────────────────────────
    (function initProgramasCreator() {
        if (!$('#pcg-show-programa-form').length) {
            return;
        }

        let currentProgramaId = 0;
        let selectedGroupIds = [];
        let cachedSpecializations = [];
        let specsPage = 1;
        const specsPerPage = 10;

        function resetProgramaForm() {
            currentProgramaId = 0;
            selectedGroupIds = [];
            cachedSpecializations = [];
            specsPage = 1;

            $('#pcg-current-programa-id').val(0);
            $('#pcg-programa-title').val('');
            $('#pcg-programa-description').val('');
            $('#pcg-programa-price').val('');
            $('#pcg-programa-price-free-indicator').hide();
            $('#pcg-current-programa-label').text('').hide();
            $('#pcg-prog-spec-search').val('');

            $('.pcg-prog-segment').removeClass('active');
            $('.pcg-prog-segment[data-value="programa"]').addClass('active');
            $('#pcg-prog-mode-programa').show();
            $('#pcg-prog-mode-especializaciones').hide();
            $('#pcg-prog-mode-meta').hide();

            $('#pcg-prog-all-specs').html(`
                <div class="pcg-loading-placeholder">
                    <span class="dashicons dashicons-update spin"></span>
                    <p>${t('loading')}</p>
                </div>
            `);
            $('#pcg-prog-added-specs').html(`
	                <div class="pcg-loading-placeholder">
	                    <span class="dashicons dashicons-update spin"></span>
	                    <p>${t('loading')}</p>
	                </div>
	            `);
            $('#pcg-prog-pagination').hide();

            // Teachers tab
            const seed = getCurrentUserTeacherSeed();
            const $list = $('#pcg-program-teachers-list');
            if ($list.length) {
                populateTeachersList($list, [], seed);
            }

            plLearningMeta.reset('programa');
        }

        function renderAddedSpecs() {
            const $wrap = $('#pcg-prog-added-specs');

            if (!cachedSpecializations || cachedSpecializations.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noSpecializationsYet')}</p>`);
                return;
            }

            if (!selectedGroupIds || selectedGroupIds.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noSpecializationsAddedYet')}</p>`);
                return;
            }

            const items = selectedGroupIds
                .map(id => cachedSpecializations.find(g => Number(g.id) === Number(id)))
                .filter(Boolean);

            if (items.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noSpecializationsAddedYet')}</p>`);
                return;
            }

            const html = items.map(g => `
                <div class="pcg-spec-added-row" data-id="${g.id}">
                    <div class="pcg-spec-added-title">${g.title}</div>
                    <button type="button" class="pcg-btn-icon pcg-prog-remove-spec" title="${t('remove')}">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `).join('');

            $wrap.html(html);
        }

        function getFilteredSpecs() {
            const q = String($('#pcg-prog-spec-search').val() || '').trim().toLowerCase();
            if (!q) return cachedSpecializations || [];
            return (cachedSpecializations || []).filter(g => String(g.title || '').toLowerCase().includes(q));
        }

        function renderAllSpecs() {
            const $wrap = $('#pcg-prog-all-specs');
            const specs = getFilteredSpecs();

            if (!specs || specs.length === 0) {
                $wrap.html(`<p class="pcg-empty-msg">${t('noSpecializations')}</p>`);
                $('#pcg-prog-pagination').hide();
                return;
            }

            const totalPages = Math.max(1, Math.ceil(specs.length / specsPerPage));
            if (specsPage > totalPages) specsPage = totalPages;
            if (specsPage < 1) specsPage = 1;

            const start = (specsPage - 1) * specsPerPage;
            const pageItems = specs.slice(start, start + specsPerPage);
            const selected = new Set(selectedGroupIds.map(id => Number(id)));

            const html = pageItems.map(g => {
                const isAdded = selected.has(Number(g.id));
                return `
                    <div class="pcg-prog-row" data-id="${g.id}">
                        <div class="pcg-prog-row-title">${g.title}</div>
                        <button type="button" class="pcg-spec-add-btn pcg-prog-add-spec" ${isAdded ? 'disabled' : ''}>
                            ${isAdded ? t('added') : t('add')}
                        </button>
                    </div>
                `;
            }).join('');

            $wrap.html(html);

            if (specs.length > specsPerPage) {
                $('#pcg-prog-pagination').show();
                $('#pcg-prog-page-info').text(`${specsPage} / ${totalPages}`);
                $('#pcg-prog-page-prev').prop('disabled', specsPage <= 1);
                $('#pcg-prog-page-next').prop('disabled', specsPage >= totalPages);
            } else {
                $('#pcg-prog-pagination').hide();
            }
        }

        function addSpecToPrograma(groupId) {
            const id = Number(groupId) || 0;
            if (!id) return;
            if (!selectedGroupIds.includes(id)) selectedGroupIds.push(id);

            const spec = (cachedSpecializations || []).find(g => Number(g.id) === id);
            if (spec && spec.author_id) {
                ensureTeacherForUser($('#pcg-program-teachers-list'), {
                    id: Number(spec.author_id),
                    name: spec.author_name || '',
                    email: spec.author_email || '',
                    avatar: spec.author_avatar || ''
                });
            }

            renderAddedSpecs();
            renderAllSpecs();
        }

        function removeSpecFromPrograma(groupId) {
            const id = Number(groupId) || 0;
            if (!id) return;
            selectedGroupIds = selectedGroupIds.filter(x => Number(x) !== id);
            renderAddedSpecs();
            renderAllSpecs();
        }

        function loadSpecializationsForPrograma() {
            return $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_get_published_specializations',
                    nonce: pcgCreatorData.nonce
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    $('#pcg-prog-all-specs').html(`<p class="pcg-empty-msg">${t('failedToLoadSpecializations')}</p>`);
                    $('#pcg-prog-added-specs').html(`<p class="pcg-empty-msg">${t('failedToLoadSpecializations')}</p>`);
                    $('#pcg-prog-pagination').hide();
                    return;
                }

                cachedSpecializations = response.data || [];
                renderAddedSpecs();
                renderAllSpecs();
            });
        }

        function openProgramaFormForEdit(programaId) {
            const id = Number(programaId) || 0;
            if (!id) return;

            $('#pcg-my-programas-section').fadeOut(200, function () {
                resetProgramaForm();
                $('#pcg-programa-form-section').show();
                $('#pcg-programa-form-section').append(`
                    <div id="pcg-prog-edit-loading" class="pcg-loading-placeholder">
                        <span class="dashicons dashicons-update spin"></span>
                        <p>${t('loadingProgram')}</p>
                    </div>
                `);

                $.ajax({
                    url: pcgCreatorData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pcg_get_programa_for_edit',
                        nonce: pcgCreatorData.nonce,
                        programa_id: id
                    }
                }).done(function (response) {
                    $('#pcg-prog-edit-loading').remove();
                    if (!response || !response.success) {
                        alert(t('errorLoadingProgram'));
                        $('#pcg-programa-form-section').hide();
                        $('#pcg-my-programas-section').show();
                        return;
                    }

                    const data = response.data;
                    currentProgramaId = Number(data.id) || 0;
                    selectedGroupIds = (data.group_ids || []).map(x => Number(x));

                    $('#pcg-current-programa-id').val(currentProgramaId);
                    $('#pcg-programa-title').val(data.title || '');
                    $('#pcg-programa-description').val(data.description || '');
                    $('#pcg-programa-price').val(data.price || '');

                    plLearningMeta.setSelection('programa', data.category_ids || [], data.tags || []);

                    if (data.title) {
                        $('#pcg-current-programa-label').text(data.title).show();
                    }

                    const priceNum = parseFloat(String(data.price || '').replace(',', '.')) || 0;
                    if (priceNum === 0) {
                        $('#pcg-programa-price-free-indicator').show();
                    }

                    $('.pcg-prog-segment').removeClass('active');
                    $('.pcg-prog-segment[data-value="programa"]').addClass('active');
                    $('#pcg-prog-mode-programa').show();
                    $('#pcg-prog-mode-especializaciones').hide();
                    $('#pcg-prog-mode-meta').hide();

                    // Teachers
                    populateTeachersList($('#pcg-program-teachers-list'), data.teachers || [], {
                        id: Number(data.author_id || 0),
                        name: data.author_name || '',
                        avatar: data.author_avatar || ''
                    });
                    (data.included_authors || []).forEach(a => {
                        ensureTeacherForUser($('#pcg-program-teachers-list'), {
                            id: Number(a.id),
                            name: a.name || '',
                            email: a.email || '',
                            avatar: a.avatar || ''
                        });
                    });

                    loadSpecializationsForPrograma().done(function () {
                        (selectedGroupIds || []).forEach(gid => {
                            const spec = (cachedSpecializations || []).find(g => Number(g.id) === Number(gid));
                            if (spec && spec.author_id) {
                                ensureTeacherForUser($('#pcg-program-teachers-list'), {
                                    id: Number(spec.author_id),
                                    name: spec.author_name || '',
                                    email: spec.author_email || '',
                                    avatar: spec.author_avatar || ''
                                });
                            }
                        });
                    });
                }).fail(function () {
                    $('#pcg-prog-edit-loading').remove();
                    alert(t('errorLoadingProgramGeneric'));
                    $('#pcg-programa-form-section').hide();
                    $('#pcg-my-programas-section').show();
                });
            });
        }

        function getProgramaPayload() {
            const meta = plLearningMeta.getPayload('programa');
            return {
                id: currentProgramaId,
                title: $('#pcg-programa-title').val(),
                description: $('#pcg-programa-description').val(),
                price: $('#pcg-programa-price').val(),
                group_ids: selectedGroupIds,
                teachers: collectTeachers($('#pcg-program-teachers-list')),
                split_locked: Boolean($('#pcg-program-teachers-list').data('splitLocked')),
                category_ids: meta.category_ids,
                tag_ids: meta.tag_ids,
            };
        }

        // Open form
        $('#pcg-show-programa-form').on('click', function () {
            $('#pcg-my-programas-section').fadeOut(300, function () {
                resetProgramaForm();
                $('#pcg-programa-form-section').fadeIn(400);
                loadSpecializationsForPrograma();
            });
        });

        // Back to list
        $('#pcg-btn-back-to-programas').on('click', function () {
            $('#pcg-programa-form-section').fadeOut(300, function () {
                $('#pcg-my-programas-section').fadeIn();
                resetProgramaForm();
            });
        });

        // Segment switcher
        $(document).on('click', '.pcg-prog-segment', function () {
            const $form = $('#pcg-programa-form-section');
            $form.find('.pcg-prog-segment').removeClass('active');
            $(this).addClass('active');

            const mode = $(this).data('value');
            $('#pcg-prog-mode-programa').hide();
            $('#pcg-prog-mode-especializaciones').hide();
            $('#pcg-prog-mode-meta').hide();

            if (mode === 'programa') {
                $('#pcg-prog-mode-programa').fadeIn(200);
            } else if (mode === 'especializaciones') {
                $('#pcg-prog-mode-especializaciones').fadeIn(200);
                loadSpecializationsForPrograma();
            } else if (mode === 'meta') {
                $('#pcg-prog-mode-meta').fadeIn(200);
                plLearningMeta.render('programa');
            }
        });

        // Update label as user types
        $('#pcg-programa-title').on('input', function () {
            const title = $(this).val();
            if (title) {
                $('#pcg-current-programa-label').text(title).show();
            } else {
                $('#pcg-current-programa-label').hide();
            }
        });

        // Free indicator
        $('#pcg-programa-price').on('input change', function () {
            const price = parseFloat($(this).val()) || 0;
            if (price === 0) {
                $('#pcg-programa-price-free-indicator').fadeIn(200);
            } else {
                $('#pcg-programa-price-free-indicator').fadeOut(200);
            }
        });

        // Filter + pagination
        $('#pcg-prog-spec-search').on('input', function () {
            specsPage = 1;
            renderAllSpecs();
        });

        $('#pcg-prog-spec-search').on('keydown', function (e) {
            if (e.key === 'Escape') {
                $(this).val('');
                specsPage = 1;
                renderAllSpecs();
            }
        });

        $('#pcg-prog-page-prev').on('click', function () {
            specsPage = Math.max(1, specsPage - 1);
            renderAllSpecs();
        });

        $('#pcg-prog-page-next').on('click', function () {
            specsPage = specsPage + 1;
            renderAllSpecs();
        });

        $(document).on('click', '.pcg-prog-add-spec', function () {
            const groupId = $(this).closest('.pcg-prog-row').data('id');
            addSpecToPrograma(groupId);
        });

        $(document).on('click', '.pcg-prog-remove-spec', function () {
            const groupId = $(this).closest('.pcg-spec-added-row').data('id');
            removeSpecFromPrograma(groupId);
        });

        // Save programa
        $('.pcg-btn-save-programa').on('click', function () {
            const $btn = $(this);
            const payload = getProgramaPayload();

            if (!payload.title) {
                alert(t('pleaseEnterProgramName'));
                return;
            }

            $btn.addClass('loading').prop('disabled', true);

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_save_programa',
                    nonce: pcgCreatorData.nonce,
                    programa_data: payload
                },
                success: function (response) {
                    $btn.removeClass('loading');
                    if (response && response.success) {
                        currentProgramaId = response.data.programa_id;
                        $('#pcg-current-programa-id').val(currentProgramaId);
                        if (response.data && response.data.snapshot_status === 'pending') {
                            alert(t('approvalRequestSent'));
                        }
                        $btn.addClass('success');
                        refreshActiveList();
                        setTimeout(() => {
                            $btn.prop('disabled', false).removeClass('success');
                        }, 2000);
                    } else {
                        alert(t('errorPrefix') + (response && response.data && response.data.message ? response.data.message : t('unknownError')));
                        $btn.prop('disabled', false);
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    alert(t('errorSavingProgram'));
                    $btn.prop('disabled', false);
                }
            });
        });

        // Edit / Delete from cards
        $(document).on('click', '.pcg-btn-edit-programa', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const programaId = $(this).closest('.pcg-programa-card').data('id');
            openProgramaFormForEdit(programaId);
        });

        $(document).on('click', '.pcg-btn-delete-programa', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const programaId = $(this).closest('.pcg-programa-card').data('id');
            if (!programaId) return;
            if (!confirm(t('confirmDeleteProgram'))) return;

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_delete_programa',
                    nonce: pcgCreatorData.nonce,
                    programa_id: programaId
                },
                success: function (response) {
                    if (response && response.success) {
                        refreshActiveList();
                    } else {
                        alert(t('errorPrefix') + (response && response.data && response.data.message ? response.data.message : t('couldNotDelete')));
                    }
                },
                error: function () {
                    alert(t('errorDeletingProgram'));
                }
            });
        });
    })();

    // ───────────────────────────────────────────────────────────
    // Escritos (Posts) Creator UI
    // ───────────────────────────────────────────────────────────
    (function initEscritosCreator() {
        if (!$('#pcg-show-escritos-form').length) {
            return;
        }

        // Helper to ensure editor focus
        function focusEditor() {
            const editor = document.getElementById('pcg-escrito-content-editor');
            if (editor && document.activeElement !== editor) {
                editor.focus();
            }
        }

        function getEditorEl() {
            return document.getElementById('pcg-escrito-content-editor');
        }

        let savedEditorRange = null;
        let isSelectionLocked = false;

        function isRangeInsideEditor(range, editor) {
            if (!range || !editor) return false;
            return editor.contains(range.startContainer) && editor.contains(range.endContainer);
        }

        function saveEditorSelection(force = false) {
            if (isSelectionLocked && !force) return;
            const editor = getEditorEl();
            if (!editor) return;
            const sel = window.getSelection ? window.getSelection() : null;
            if (!sel || sel.rangeCount === 0) return;
            const range = sel.getRangeAt(0);
            if (!isRangeInsideEditor(range, editor)) return;
            savedEditorRange = range.cloneRange();
        }

        function restoreEditorSelection() {
            const editor = getEditorEl();
            if (!editor) return false;

            if (savedEditorRange && isRangeInsideEditor(savedEditorRange, editor)) {
                const sel = window.getSelection ? window.getSelection() : null;
                if (!sel) return false;
                sel.removeAllRanges();
                sel.addRange(savedEditorRange);
                return true;
            }

            return false;
        }

        function placeCaretAtEnd(editor) {
            if (!editor || !window.getSelection || !document.createRange) return;
            const range = document.createRange();
            range.selectNodeContents(editor);
            range.collapse(false);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }

        function insertHtmlAtEditorSelection(html) {
            const editor = getEditorEl();
            if (!editor) return false;

            const sel = window.getSelection ? window.getSelection() : null;
            let range = (sel && sel.rangeCount > 0) ? sel.getRangeAt(0) : null;

            if (!isRangeInsideEditor(range, editor)) {
                range = (savedEditorRange && isRangeInsideEditor(savedEditorRange, editor)) ? savedEditorRange.cloneRange() : null;
            }
            if (!range) {
                placeCaretAtEnd(editor);
                if (sel && sel.rangeCount > 0) {
                    range = sel.getRangeAt(0);
                }
            }
            if (!range) return false;

            range.deleteContents();
            const container = document.createElement('div');
            container.innerHTML = html;
            const frag = document.createDocumentFragment();
            let lastNode = null;
            while (container.firstChild) {
                lastNode = frag.appendChild(container.firstChild);
            }
            range.insertNode(frag);

            if (sel && lastNode) {
                const next = document.createRange();
                next.setStartAfter(lastNode);
                next.collapse(true);
                sel.removeAllRanges();
                sel.addRange(next);
                savedEditorRange = next.cloneRange();
            }

            return true;
        }

        function getClosestEditorBlock(node, editor) {
            if (!node || !editor) return null;
            let current = node.nodeType === Node.ELEMENT_NODE ? node : node.parentNode;
            while (current && current !== editor) {
                if (
                    current.nodeType === Node.ELEMENT_NODE &&
                    /^(P|DIV|H1|H2|H3|H4|H5|H6)$/i.test(current.tagName)
                ) {
                    return current;
                }
                current = current.parentNode;
            }
            return null;
        }

        function replaceBlockTag(block, tagName) {
            if (!block || !block.parentNode || !tagName) return null;
            const nextTag = String(tagName).toUpperCase();
            if (block.tagName === nextTag) return block;

            const replacement = document.createElement(nextTag);
            replacement.innerHTML = block.innerHTML;

            // Preserve editor-specific metadata if ever added to these blocks.
            Array.from(block.attributes || []).forEach(function (attr) {
                if (attr && attr.name && attr.name !== 'style') {
                    replacement.setAttribute(attr.name, attr.value);
                }
            });

            block.parentNode.replaceChild(replacement, block);
            return replacement;
        }

        function applyBlockTagFallback(tag) {
            const editor = getEditorEl();
            if (!editor) return false;

            const sel = window.getSelection ? window.getSelection() : null;
            let range = (sel && sel.rangeCount > 0) ? sel.getRangeAt(0) : null;
            if (!isRangeInsideEditor(range, editor)) {
                range = (savedEditorRange && isRangeInsideEditor(savedEditorRange, editor)) ? savedEditorRange.cloneRange() : null;
            }
            if (!range) return false;

            const block = getClosestEditorBlock(range.startContainer, editor) || getClosestEditorBlock(range.commonAncestorContainer, editor);
            if (!block) return false;

            const replacement = replaceBlockTag(block, tag);
            if (!replacement || !sel) return false;

            const nextRange = document.createRange();
            nextRange.selectNodeContents(replacement);
            nextRange.collapse(false);
            sel.removeAllRanges();
            sel.addRange(nextRange);
            savedEditorRange = nextRange.cloneRange();
            return true;
        }

        // Expose helpers for inline toolbar buttons.
        window.pcgEscritoExec = function (cmd, value) {
            try {
                focusEditor();
                restoreEditorSelection();
                document.execCommand(cmd, false, value ?? null);
                saveEditorSelection(true);
            } catch (_) {
                // no-op
            }
        };

        window.pcgEscritoFormatBlock = function (tag) {
            const editor = document.getElementById('pcg-escrito-content-editor');
            if (!editor || !editor.innerHTML.trim()) {
                if (editor) editor.innerHTML = '<p><br></p>';
            }

            focusEditor();
            restoreEditorSelection();

            let applied = false;
            try {
                applied = document.execCommand('formatBlock', false, tag);
            } catch (err) {
                applied = false;
            }

            if (!applied) {
                try {
                    applied = document.execCommand('formatBlock', false, '<' + tag + '>');
                } catch (err) {
                    applied = false;
                }
            }

            if (!applied) {
                applyBlockTagFallback(tag);
            } else {
                saveEditorSelection(true);
            }
        };

        $(document).on('mousedown', '.pcg-toolbar-btn, .pcg-dropdown-content button', function (e) {
            saveEditorSelection(true);
            e.preventDefault();
        });

        $(document).on('click', '.pcg-toolbar-btn, .pcg-dropdown-content button', function () {
            if ($(this).attr('onclick')) {
                focusEditor();
                restoreEditorSelection();
            }
        });

        // robust placeholder logic for contenteditable
        function handlePlaceholder() {
            const $ed = $('#pcg-escrito-content-editor');
            if (!$ed.length) return;
            // Get raw text (ignores HTML tags)
            const text = $ed.text().trim();
            const html = $ed.html().trim().toLowerCase();
            const hasImages = html.includes('<img') || html.includes('<figure');
            // It's empty if there's no actual text, AND the HTML is either entirely empty or just browsers' default empty blocks
            const isEmpty = (!hasImages && text === '' && (html === '' || html === '<br>' || html === '<p><br></p>' || html === '<p></p>' || html === '<br><div></div>' || html.replace(/<[^>]*>/g, '').trim() === ''));
            $ed.toggleClass('pcg-is-empty', isEmpty);
            $('#pcg-editor-placeholder').toggle(isEmpty);
        }

        $(document).on('input keyup blur focus change', '#pcg-escrito-content-editor', handlePlaceholder);
        $(document).on('keyup mouseup focus blur input', '#pcg-escrito-content-editor', saveEditorSelection);
        $(document).on('selectionchange', function () {
            saveEditorSelection();
        });

        let currentEscritoId = 0;
        let escritoThumbnailId = 0;

        function resetEscritoForm() {
            currentEscritoId = 0;
            escritoThumbnailId = 0;
            $('#pcg-current-escrito-id').val(0);
            $('#pcg-escrito-title').val('').css('height', 'auto');
            $('#pcg-escrito-content-editor').html('');
            $('#pcg-escrito-content').val('');
            $('#pcg-escrito-excerpt').val('');
            $('#pcg-escrito-thumbnail-preview').hide().find('img').attr('src', '');
            $('#pcg-escrito-upload-ui').show();
            $('#pcg-current-escrito-label').text('').hide();
            $('#pcg-btn-preview-escrito').hide();
            handlePlaceholder();
        }

        $('#pcg-show-escritos-form').on('click', function () {
            $('#pcg-my-escritos-section').fadeOut(300, function () {
                resetEscritoForm();
                $('#pcg-escritos-form-section').fadeIn(300, function () {
                    initInlineImageMicroText();
                    normalizeInlineImages(getEditorEl());
                });
            });
        });

        $('#pcg-btn-back-to-escritos').on('click', function () {
            $('#pcg-escritos-form-section').fadeOut(300, function () {
                $('#pcg-my-escritos-section').fadeIn();
                resetEscritoForm();
            });
        });

        $(document).on('click', '.pcg-btn-save-escrito', function () {
            const $btn = $(this);
            const action = $btn.data('action') || 'publish';
            const content = $('#pcg-escrito-content-editor').html();
            const payload = {
                id: currentEscritoId,
                title: $('#pcg-escrito-title').val(),
                content: content,
                excerpt: $('#pcg-escrito-excerpt').val(),
                thumbnail_id: escritoThumbnailId,
                status: action
            };

            if (!payload.title) {
                alert(t('pleaseEnterEscritoTitle'));
                return;
            }

            $('.pcg-btn-save-escrito').prop('disabled', true);
            $btn.addClass('loading');

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_save_escrito',
                    nonce: pcgCreatorData.nonce,
                    escrito_data: payload
                },
                success: function (response) {
                    $btn.removeClass('loading');
                    $('.pcg-btn-save-escrito').prop('disabled', false);

                    if (response.success) {
                        currentEscritoId = response.data.escrito_id;
                        $('#pcg-current-escrito-id').val(currentEscritoId);
                        $btn.addClass('success');
                        refreshActiveList();

                        // Toggle logic for draft icon
                        if (action === 'draft') {
                            $('#pcg-publish-status-icon').show();
                        } else {
                            $('#pcg-publish-status-icon').hide();
                        }

                        if (response.data.permalink) {
                            $('#pcg-btn-preview-escrito').attr('href', response.data.permalink).show();
                        }

                        setTimeout(() => {
                            $btn.removeClass('success');
                        }, 2000);
                    } else {
                        alert(t('errorSavingEscrito') + ': ' + (response.data ? response.data.message : t('unknownError')));
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    $('.pcg-btn-save-escrito').prop('disabled', false);
                    alert(t('errorSavingEscrito'));
                }
            });
        });

        // Edit
        $(document).on('click', '.pcg-btn-edit-escrito', function () {
            const escritoId = $(this).closest('.pcg-course-card').data('id');
            if (!escritoId) return;

            resetEscritoForm();
            $('#pcg-my-escritos-section').hide();
            $('#pcg-escritos-form-section').show();

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_get_escrito_for_edit',
                    nonce: pcgCreatorData.nonce,
                    escrito_id: escritoId
                },
                success: function (response) {
                    if (response.success) {
                        const data = response.data;
                        currentEscritoId = data.id;
                        $('#pcg-current-escrito-id').val(data.id);
                        $('#pcg-escrito-title').val(data.title);
                        $('#pcg-escrito-content-editor').html(data.content);
                        $('#pcg-escrito-content').val(data.content);
                        $('#pcg-escrito-excerpt').val(data.excerpt);
                        $('#pcg-current-escrito-label').text(data.title).show();

                        escritoThumbnailId = data.thumbnail_id;
                        if (data.thumbnail_url) {
                            $('#pcg-escrito-thumbnail-preview img').attr('src', data.thumbnail_url);
                            $('#pcg-escrito-thumbnail-preview').show();
                            $('#pcg-escrito-upload-ui').hide();
                        }
                        handlePlaceholder();
                        initInlineImageMicroText();
                        normalizeInlineImages(getEditorEl());

                        if (data.permalink) {
                            $('#pcg-btn-preview-escrito').attr('href', data.permalink).show();
                        }

                        if (data.status === 'draft') {
                            $('#pcg-publish-status-icon').show();
                        } else {
                            $('#pcg-publish-status-icon').hide();
                        }

                        // Auto-resize title after loading
                        setTimeout(() => {
                            const $title = $('#pcg-escrito-title');
                            $title.css('height', 'auto').css('height', $title[0].scrollHeight + 'px');
                        }, 50);
                    } else {
                        alert(response.data.message);
                        $('#pcg-btn-back-to-escritos').trigger('click');
                    }
                }
            });
        });

        // Delete
        $(document).on('click', '.pcg-btn-delete-escrito', function () {
            const escritoId = $(this).closest('.pcg-course-card').data('id');
            if (!confirm(t('confirmDeleteCourse'))) return;

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcg_delete_escrito',
                    nonce: pcgCreatorData.nonce,
                    escrito_id: escritoId
                },
                success: function (response) {
                    if (response.success) {
                        refreshActiveList();
                    }
                }
            });
        });

        // Image upload
        $(document).on('click', '[data-upload="escrito-thumbnail"]', function () {
            PL_Cropper.open({
                width: 800,
                height: 300,
                title: t('uploadImage'),
                onSave: function (dataUrl) {
                    $.ajax({
                        url: pcgCreatorData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'pcg_upload_cropped_image',
                            nonce: pcgCreatorData.nonce,
                            image_data: dataUrl,
                            type: 'escrito'
                        },
                        success: function (response) {
                            if (response.success) {
                                escritoThumbnailId = response.data.id;
                                $('#pcg-escrito-thumbnail-preview img').attr('src', response.data.url);
                                $('#pcg-escrito-thumbnail-preview').show();
                                $('#pcg-escrito-upload-ui').hide();
                            } else {
                                alert(t('errorUploadingImage'));
                            }
                        }
                    });
                }
            });
        });

        $(document).on('click', '#pcg-remove-escrito-thumbnail', function () {
            escritoThumbnailId = 0;
            $('#pcg-escrito-thumbnail-preview').hide().find('img').attr('src', '');
            $('#pcg-escrito-upload-ui').show();
        });

        $(document).on('mousedown', '#pcg-btn-escrito-add-image', function () {
            saveEditorSelection(true);
        });

        // Inline Image Insertion via custom Cropper
        $(document).on('click', '#pcg-btn-escrito-add-image', function (e) {
            e.preventDefault();
            saveEditorSelection(true);
            isSelectionLocked = true;
            const markerId = 'pcg-insert-marker-' + Date.now();
            const preModalScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;

            const editor = getEditorEl();
            if (editor) {
                focusEditor();
                if (!restoreEditorSelection()) {
                    placeCaretAtEnd(editor);
                }
            }

            const markerInserted = insertHtmlAtEditorSelection(`<span id="${markerId}" class="pcg-insert-marker" contenteditable="false"></span>`);
            if (!markerInserted && editor) {
                editor.insertAdjacentHTML('beforeend', `<span id="${markerId}" class="pcg-insert-marker" contenteditable="false"></span>`);
            }

            PL_Cropper.open({
                width: 800,
                height: 600,
                freeCrop: true,
                title: t('uploadImage'),
                onCancel: function () {
                    isSelectionLocked = false;
                    $('#' + markerId).remove();
                },
                onSave: function (dataUrl) {
                    const tempId = 'pcg-loading-' + Date.now();
                    const $marker = $('#' + markerId);
                    if ($marker.length) {
                        $marker.replaceWith(`<span id="${tempId}" class="pcg-img-loading">Cargando imagen...</span>`);
                    } else if (editor) {
                        editor.insertAdjacentHTML('beforeend', `<span id="${tempId}" class="pcg-img-loading">Cargando imagen...</span>`);
                    }
                    isSelectionLocked = false;
                    saveEditorSelection(true);

                    $.ajax({
                        url: pcgCreatorData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'pcg_upload_cropped_image',
                            nonce: pcgCreatorData.nonce,
                            image_data: dataUrl,
                            type: 'inline',
                            entity_id: currentEscritoId
                        },
                        success: function (response) {
	                            if (response.success) {
	                                const loader = document.getElementById(tempId);
                                const attachmentId = response && response.data && response.data.id ? String(response.data.id) : '';
                                const imageId = attachmentId ? `pcg-inline-img-${attachmentId}` : `pcg-inline-img-${Date.now()}`;
                                const figureHtml = `
                                    <figure class="pcg-inline-figure">
                                        <img id="${imageId}" data-attachment-id="${attachmentId}" src="${response.data.url}" />
                                        <figcaption class="pcg-inline-caption" contenteditable="false" data-placeholder="Escribe un texto para esta imagen..." data-editing="false"></figcaption>
                                    </figure>
                                `.replace(/\s{2,}/g, ' ').trim();
	                                if (loader) {
	                                    $(loader).replaceWith(figureHtml);
	                                    const insertedFigure = document.querySelector('#pcg-escrito-content-editor figure.pcg-inline-figure:last-of-type');
	                                    if (insertedFigure) {
	                                        insertedFigure.scrollIntoView({ block: 'center', inline: 'nearest' });
                                            normalizeInlineImages(getEditorEl());
	                                    } else {
	                                        window.scrollTo(0, preModalScrollY);
	                                    }
	                                } else {
	                                    insertHtmlAtEditorSelection(figureHtml);
                                        normalizeInlineImages(getEditorEl());
	                                    window.scrollTo(0, preModalScrollY);
	                                }
	                            } else {
                                alert(t('errorUploadingImage'));
                                $('#' + tempId).remove();
                            }
                        },
                        error: function () {
                            alert(t('errorUploadingImage'));
                            $('#' + tempId).remove();
                        }
                    });
                }
            });
        });

        // Floating Remove Button for Inline Images
        let activeHoverImg = null;
        const $floatRemoveBtn = $('<button class="pcg-float-remove-btn" title="Remover imagen">&times;</button>').appendTo('body');

        $(document).on('click', '#pcg-escrito-content-editor img', function () {
            activeHoverImg = $(this);
            const offset = activeHoverImg.offset();
            $floatRemoveBtn.css({
                top: offset.top + 10,
                left: offset.left + activeHoverImg.width() - 40,
                display: 'flex'
            });
        });

        $(document).on('click', function (e) {
            if (!$floatRemoveBtn.is(':visible')) return;
            if ($(e.target).closest('#pcg-escrito-content-editor img').length) return;
            if ($(e.target).closest('.pcg-float-remove-btn').length) return;
            $floatRemoveBtn.hide();
            activeHoverImg = null;
        });

	        $floatRemoveBtn.on('click', function (e) {
	            e.preventDefault();
	            if (activeHoverImg) {
	                const $figure = activeHoverImg.closest('figure.pcg-inline-figure');
	                if ($figure.length) {
	                    $figure.remove();
	                } else {
	                    activeHoverImg.remove();
	                }
	                $floatRemoveBtn.hide();
	                activeHoverImg = null;
	            }
	        });

        function updateCaptionState(captionEl) {
            const caption = captionEl instanceof HTMLElement ? captionEl : null;
            if (!caption) return;
            const figure = caption.closest('figure.pcg-inline-figure');
            if (!figure) return;
	            const text = (caption.textContent || '').replace(/\u00a0/g, ' ').trim();
	            if (text.length === 0) {
	                // Remove stray <br> that browsers often insert into empty contenteditables
	                caption.innerHTML = '';
            }
            figure.classList.toggle('pcg-has-caption-text', text.length > 0);
        }

        let inlineImageObserverInitialized = false;
        let isNormalizingInlineImages = false;

        function ensureUniqueId(id, el) {
            if (!id) return '';
            let candidate = id;
            let i = 2;
            while (document.getElementById(candidate) && document.getElementById(candidate) !== el) {
                candidate = `${id}-${i++}`;
            }
            return candidate;
        }

        function normalizeInlineImages(editor) {
            if (!editor || isNormalizingInlineImages) return;
            isNormalizingInlineImages = true;
            try {
                const images = Array.from(editor.querySelectorAll('img'));
                images.forEach((img) => {
                    if (!(img instanceof HTMLElement)) return;

                    const attachmentId = (img.getAttribute('data-attachment-id') || '').trim();
                    if (!img.id) {
                        const baseId = attachmentId ? `pcg-inline-img-${attachmentId}` : `pcg-inline-img-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                        img.id = ensureUniqueId(baseId, img);
                    } else {
                        img.id = ensureUniqueId(img.id, img);
                    }

                    const existingFigure = img.closest('figure');
                    if (existingFigure && existingFigure.classList.contains('pcg-inline-figure')) {
                        let caption = existingFigure.querySelector('figcaption');
                        if (!caption) {
                            caption = document.createElement('figcaption');
                            existingFigure.appendChild(caption);
                        }
                        caption.classList.add('pcg-inline-caption');
                        caption.setAttribute('data-placeholder', caption.getAttribute('data-placeholder') || 'Escribe un texto para esta imagen...');
                        caption.setAttribute('data-editing', 'false');
                        caption.setAttribute('contenteditable', 'false');
                        updateCaptionState(caption);
                        return;
                    }

                    if (existingFigure && !existingFigure.classList.contains('pcg-inline-figure')) {
                        existingFigure.classList.add('pcg-inline-figure');
                        let caption = existingFigure.querySelector('figcaption');
                        if (!caption) {
                            caption = document.createElement('figcaption');
                            existingFigure.appendChild(caption);
                        }
                        caption.classList.add('pcg-inline-caption');
                        caption.setAttribute('data-placeholder', caption.getAttribute('data-placeholder') || 'Escribe un texto para esta imagen...');
                        caption.setAttribute('data-editing', 'false');
                        caption.setAttribute('contenteditable', 'false');
                        updateCaptionState(caption);
                        return;
                    }

                    // Avoid restructuring images inside tables.
                    if (img.closest('table')) return;

                    const parent = img.parentElement;
                    const shouldReplaceParent = parent && parent.tagName === 'P' && parent.childNodes.length === 1;
                    const figure = document.createElement('figure');
                    figure.className = 'pcg-inline-figure';

                    const caption = document.createElement('figcaption');
                    caption.className = 'pcg-inline-caption';
                    caption.setAttribute('contenteditable', 'false');
                    caption.setAttribute('data-placeholder', 'Escribe un texto para esta imagen...');
                    caption.setAttribute('data-editing', 'false');

                    if (shouldReplaceParent) {
                        parent.parentNode && parent.parentNode.replaceChild(figure, parent);
                    } else if (parent) {
                        parent.insertBefore(figure, img);
                    }

                    figure.appendChild(img);
                    figure.appendChild(caption);
                    updateCaptionState(caption);
                });
            } finally {
                isNormalizingInlineImages = false;
            }
        }

        function initInlineImageMicroText() {
            const editor = getEditorEl();
            if (!editor || inlineImageObserverInitialized || typeof MutationObserver === 'undefined') return;
            inlineImageObserverInitialized = true;

            const observer = new MutationObserver((mutations) => {
                if (isNormalizingInlineImages) return;
                let hasImageChange = false;
                for (const m of mutations) {
                    if (m.type !== 'childList') continue;
                    for (const node of Array.from(m.addedNodes || [])) {
                        if (!node || node.nodeType !== 1) continue;
                        const el = node;
                        if (el.tagName === 'IMG' || (el.querySelector && el.querySelector('img'))) {
                            hasImageChange = true;
                            break;
                        }
                    }
                    if (hasImageChange) break;
                }
                if (hasImageChange) normalizeInlineImages(editor);
            });

            observer.observe(editor, { childList: true, subtree: true });
            normalizeInlineImages(editor);
        }

        function focusCaptionAtEnd(caption) {
            if (!caption || !window.getSelection || !document.createRange) return;
            const range = document.createRange();
            range.selectNodeContents(caption);
            range.collapse(false);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            caption.focus();
        }

        $(document).on('click', '#pcg-escrito-content-editor figcaption.pcg-inline-caption', function (e) {
            const caption = this;
            const isEditing = caption.getAttribute('data-editing') === 'true';
            if (!isEditing) {
                e.preventDefault();
                caption.setAttribute('contenteditable', 'true');
                caption.setAttribute('data-editing', 'true');
                focusCaptionAtEnd(caption);
            }
        });

        $(document).on('keydown', '#pcg-escrito-content-editor figcaption.pcg-inline-caption', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.blur();
            }
        });

        $(document).on('input blur', '#pcg-escrito-content-editor figcaption.pcg-inline-caption', function (e) {
            if (e.type === 'blur') {
                const text = (this.textContent || '').replace(/\u00a0/g, ' ').trim();
                this.setAttribute('data-editing', 'false');
                this.setAttribute('contenteditable', 'false');
            }
            updateCaptionState(this);
        });

        // Auto-resize title
        $(document).on('input', '#pcg-escrito-title', function () {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Improved clean paste that preserves structure (p, h1-h4) but strips all garbage
        $(document).on('paste', '#pcg-escrito-content-editor', function (e) {
            e.preventDefault();
            let html = (e.originalEvent || e).clipboardData.getData('text/html');
            const text = (e.originalEvent || e).clipboardData.getData('text/plain');

            if (html) {
                // Pre-clean: Replace common block wrappers with P to avoid collapsing
                html = html.replace(/<div[^>]*>/gi, '<p>').replace(/<\/div>/gi, '</p>');

                const $temp = $('<div>').html(html);
                const allowedTags = ['P', 'H1', 'H2', 'H3', 'A', 'BR', 'UL', 'OL', 'LI', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'STRONG', 'EM', 'B', 'I', 'IMG', 'FIGURE', 'FIGCAPTION'];

                // Recursive cleanup
                function cleanNode(node) {
                    $(node).children().each(function () {
                        cleanNode(this);
                    });

                    const tag = node.tagName;
                    if (tag === 'DIV') { // Secondary check for nested divs
                        $(node).contents().unwrap();
                        return;
                    }

                    if (!allowedTags.includes(tag) && tag !== 'BODY' && tag !== 'HTML') {
                        $(node).contents().unwrap();
                    } else if (allowedTags.includes(tag)) {
                        const attributes = node.attributes;
                        let allowedAttrs = [];
                        if (tag === 'A') {
                            allowedAttrs = ['href', 'target'];
                        } else if (tag === 'IMG') {
                            allowedAttrs = ['src', 'alt', 'class', 'width', 'height', 'id', 'data-attachment-id'];
                        } else if (tag === 'FIGURE') {
                            allowedAttrs = ['class'];
                        } else if (tag === 'FIGCAPTION') {
                            allowedAttrs = ['class', 'data-placeholder', 'data-editing'];
                        }

                        for (let i = attributes.length - 1; i >= 0; i--) {
                            if (!allowedAttrs.includes(attributes[i].name)) {
                                node.removeAttribute(attributes[i].name);
                            }
                        }
                        $(node).removeAttr('style');
                    }
                }

                $temp.contents().each(function () {
                    if (this.nodeType === 1) cleanNode(this);
                });

                // Final pass: Remove empty paragraphs or weird artifacts
                $temp.find('p').each(function () {
                    if (!$(this).text().trim() && !$(this).find('br, img, iframe').length) {
                        $(this).remove();
                    }
                });

                document.execCommand('insertHTML', false, $temp.html());
            } else {
                // If only text, split by double newlines and wrap in P
                const paragraphs = text.trim().split(/\n\s*\n/);
                const cleanHTML = paragraphs.map(p => `<p>${p.replace(/\n/g, '<br>')}</p>`).join('');
                document.execCommand('insertHTML', false, cleanHTML);
            }
        });


    })();


	    let currentCourseId = 0;
	    let thumbnailId = 0;
	    let coverPhotoId = 0; // Added for cover photo
	    let certificateAttachmentId = 0;
		    let certificateLogoAttachmentId = 0;
		    let certificateSignatureAttachmentId = 0;
		    let currentCoursePermalink = '';
		    let currentCourseStatus = 'publish';

    const $list = $('#pcg-lessons-list');
    const $teachersList = $('#pcg-teachers-list');
    const $courseLabel = $('#pcg-current-course-label');
    const $previewBtn = $('#pcg-btn-preview-course');
    let editCourseRequest = null;

    // ── Tab Switcher ──
    $(document).on('click', '.pcg-desc-tab', function () {
        var target = $(this).data('target');
        $('.pcg-desc-tab').removeClass('active');
        $(this).addClass('active');
        $('.pcg-tab-content').removeClass('active');
        $('#' + target).addClass('active');
    });

    // ── Word Counter ──
    function countWords(text) {
        text = text.trim();
        if (!text) return 0;
        return text.split(/\s+/).length;
    }

    function updateWordCount(textareaId, counterId, maxWords) {
        var text = $(textareaId).val();
        var count = countWords(text);
        var $counter = $(counterId);
        $counter.text(count + ' / ' + maxWords + ' ' + t('words'));
        if (count > maxWords) {
            $counter.addClass('over-limit');
        } else {
            $counter.removeClass('over-limit');
        }
    }

    $(document).on('input', '#pcg-course-description', function () {
        updateWordCount('#pcg-course-description', '#pcg-desc-word-count', 700);
    });

    $(document).on('input', '#pcg-course-excerpt', function () {
        updateWordCount('#pcg-course-excerpt', '#pcg-excerpt-word-count', 50);
    });

		    function resetForm() {
		        currentCourseId = 0;
		        currentCourseStatus = 'publish';
		        $('#pcg-current-course-id').val(0);
	        thumbnailId = 0;
	        coverPhotoId = 0; // Reset cover photo ID
	        certificateAttachmentId = 0;
	        certificateLogoAttachmentId = 0;
	        certificateSignatureAttachmentId = 0;
	        $('#pcg-course-title').val('');
	        $('#pcg-course-description').val('');
	        $('#pcg-course-excerpt').val('');
        updateWordCount('#pcg-course-description', '#pcg-desc-word-count', 700);
        updateWordCount('#pcg-course-excerpt', '#pcg-excerpt-word-count', 50);
	        $('#pcg-course-price').val('');
	        $('#pcg-course-price-eval').val('');
	        $('#pcg-course-price-lessons').val('');
	        $('#pcg-course-price-meta').val('');
	        $('#pcg-course-price-cert').val('');
	        $('#pcg-thumbnail-preview').hide().find('img').attr('src', '');
	        $('#pcg-cover-preview').hide().find('img').attr('src', ''); // Reset cover preview
	        // Certificate template upload removed from UI.
	        $('#pcg-certificate-logo-preview').hide().find('img').attr('src', '');
	        $('#pcg-certificate-signature-preview').hide().find('img').attr('src', '');

	        $('#pcg-certificate-title').val('');
	        $('#pcg-certificate-congrats').val('');
	        // Claims removed from UI.
		        $('#pcg-cert-signature-label').val('');
		        updateWordCount('#pcg-certificate-congrats', '#pcg-cert-word-count', 50);
		        updateCertificatePreview();
		        updatePublishButton();

        $list.empty();
        $('#pcg-course-progression').prop('checked', false);
        $('.pcg-empty-lessons-state').show();
        $courseLabel.text('').hide();
        currentCoursePermalink = '';
        $previewBtn.hide();
		        $('#pcg-price-free-indicator').hide();
		        $('#pcg-price-free-indicator-eval').hide();
		        $('#pcg-price-free-indicator-lessons').hide();
		        $('#pcg-price-free-indicator-meta').hide();
		        $('#pcg-price-free-indicator-cert').hide();

        resetTeachersList($teachersList);

        plLearningMeta.reset('course');

        // Reset Tabs to "CURSO"
        $('.pcg-segment').removeClass('active');
        $('.pcg-segment[data-value="curso"]').addClass('active');
        $('.pcg-mode-content').hide();
        $('#pcg-mode-curso').show();
        placeCourseSidebar('curso');

        // Reset Desc Tabs
        $('.pcg-desc-tab').removeClass('active');
        $('.pcg-desc-tab[data-target="pcg-tab-description"]').addClass('active');
        $('.pcg-tab-content').removeClass('active');
        $('#pcg-tab-description').addClass('active');
    }

    // Show form when "CREATE COURSE" button is clicked
    $('#pcg-show-creator-form').on('click', function () {
        $('#pcg-my-courses-section').fadeOut(300, function () {
            resetForm();
            // Automatically add current user as main author
            addTeacherItem({
                user_id: pcgCreatorData.currentUserId,
                user_name: pcgCreatorData.currentUserName,
                avatar: pcgCreatorData.currentUserAvatar,
                is_main_author: true,
                role_slug: t('mainAuthorRoleSlug'),
                profit_percentage: 100
            }, $('#pcg-teachers-list'));
            $('#pcg-course-form-section').fadeIn(400);
        });
    });

    // Back to list / Cancel Edit
    $('#pcg-btn-back-to-list, #pcg-btn-cancel-edit').on('click', function () {
        $('#pcg-course-form-section').fadeOut(300, function () {
            $('#pcg-my-courses-section').fadeIn();
            resetForm();
        });
    });

    // Update label as user types
    $('#pcg-course-title').on('input', function () {
        const title = $(this).val();
        if (title) {
            $courseLabel.text(title).show();
        } else {
            $courseLabel.hide();
        }
    });

	    // Show/hide "Gratis" indicator based on price
	    $('#pcg-course-price').on('input change', function () {
	        const price = parseFloat($(this).val()) || 0;
	        const $freeIndicator = $('#pcg-price-free-indicator');
	        const $evalPrice = $('#pcg-course-price-eval');
	        const $evalIndicator = $('#pcg-price-free-indicator-eval');
	        const $lessonsPrice = $('#pcg-course-price-lessons');
	        const $lessonsIndicator = $('#pcg-price-free-indicator-lessons');
	        const $metaPrice = $('#pcg-course-price-meta');
	        const $metaIndicator = $('#pcg-price-free-indicator-meta');
	        const $certPrice = $('#pcg-course-price-cert');
	        const $certIndicator = $('#pcg-price-free-indicator-cert');

	        if ($evalPrice.length && $evalPrice.val() !== $(this).val()) {
	            $evalPrice.val($(this).val());
	        }
	        if ($lessonsPrice.length && $lessonsPrice.val() !== $(this).val()) {
	            $lessonsPrice.val($(this).val());
	        }
	        if ($metaPrice.length && $metaPrice.val() !== $(this).val()) {
	            $metaPrice.val($(this).val());
	        }
	        if ($certPrice.length && $certPrice.val() !== $(this).val()) {
	            $certPrice.val($(this).val());
	        }

	        if (price === 0) {
	            $freeIndicator.fadeIn(200);
	            if ($evalIndicator.length) {
	                $evalIndicator.fadeIn(200);
	            }
	            if ($lessonsIndicator.length) {
	                $lessonsIndicator.fadeIn(200);
	            }
	            if ($metaIndicator.length) {
	                $metaIndicator.fadeIn(200);
	            }
	            if ($certIndicator.length) {
	                $certIndicator.fadeIn(200);
	            }
	        } else {
	            $freeIndicator.fadeOut(200);
	            if ($evalIndicator.length) {
	                $evalIndicator.fadeOut(200);
	            }
	            if ($lessonsIndicator.length) {
	                $lessonsIndicator.fadeOut(200);
	            }
	            if ($metaIndicator.length) {
	                $metaIndicator.fadeOut(200);
	            }
	            if ($certIndicator.length) {
	                $certIndicator.fadeOut(200);
	            }
	        }
	    });

    // Mirror price input inside Evaluación aside to the main course price field
    $(document).on('input change', '#pcg-course-price-eval', function () {
        const val = $(this).val();
        const $main = $('#pcg-course-price');
        if ($main.length && $main.val() !== val) {
            $main.val(val).trigger('input');
        }
    });

	    // Mirror price input inside Lecciones aside to the main course price field
	    $(document).on('input change', '#pcg-course-price-lessons', function () {
	        const val = $(this).val();
	        const $main = $('#pcg-course-price');
	        if ($main.length && $main.val() !== val) {
	            $main.val(val).trigger('input');
	        }
	    });

	    // Mirror price input inside Meta aside to the main course price field
	    $(document).on('input change', '#pcg-course-price-meta', function () {
	        const val = $(this).val();
	        const $main = $('#pcg-course-price');
	        if ($main.length && $main.val() !== val) {
	            $main.val(val).trigger('input');
	        }
	    });

	    // Mirror price input inside Certificado aside to the main course price field
	    $(document).on('input change', '#pcg-course-price-cert', function () {
	        const val = $(this).val();
	        const $main = $('#pcg-course-price');
	        if ($main.length && $main.val() !== val) {
	            $main.val(val).trigger('input');
	        }
	    });

    function syncEvalPriceFromMain() {
        const $eval = $('#pcg-course-price-eval');
        const $main = $('#pcg-course-price');
        if (!$eval.length || !$main.length) return;
        $eval.val($main.val());
    }

	    function syncLessonsPriceFromMain() {
	        const $lessons = $('#pcg-course-price-lessons');
	        const $main = $('#pcg-course-price');
	        if (!$lessons.length || !$main.length) return;
	        $lessons.val($main.val());
	    }

	    function syncMetaPriceFromMain() {
	        const $meta = $('#pcg-course-price-meta');
	        const $main = $('#pcg-course-price');
	        if (!$meta.length || !$main.length) return;
	        $meta.val($main.val());
	    }

	    function syncCertPriceFromMain() {
	        const $cert = $('#pcg-course-price-cert');
	        const $main = $('#pcg-course-price');
	        if (!$cert.length || !$main.length) return;
	        $cert.val($main.val());
	    }

	    function placeCourseActions(mode) {
	        const $actions = $('#pcg-course-actions');
	        if (!$actions.length) return;

	        const $courseSidecardSection = $('#pcg-mode-curso .pcg-sidecard__section');
	        const $evalSlot = $('#pcg-mode-evaluacion .pcg-sidecard__actions-slot');
	        const $lessonsSlot = $('#pcg-mode-lecciones .pcg-sidecard__actions-slot');
	        const $metaSlot = $('#pcg-mode-meta .pcg-sidecard__actions-slot');
	        const $certSlot = $('#pcg-mode-certificado .pcg-sidecard__actions-slot');

        if (mode === 'evaluacion' && $evalSlot.length) {
            $evalSlot.append($actions);
            return;
        }

        if (mode === 'lecciones' && $lessonsSlot.length) {
            $lessonsSlot.append($actions);
            return;
        }

	        if (mode === 'meta' && $metaSlot.length) {
	            $metaSlot.append($actions);
	            return;
	        }

	        if (mode === 'certificado' && $certSlot.length) {
	            $certSlot.append($actions);
	            return;
	        }

		        if ($courseSidecardSection.length) {
		            $courseSidecardSection.prepend($actions);
		        }
		    }

	    function placeCourseChecklist(mode) {
	        const $checklist = $('#pcg-course-checklist');
	        if (!$checklist.length) return;

	        const $courseAside = $('#pcg-mode-curso .pcg-course-editor__right');
	        const $evalSlot = $('#pcg-mode-evaluacion .pcg-checklist-slot');
	        const $lessonsSlot = $('#pcg-mode-lecciones .pcg-checklist-slot');
	        const $metaSlot = $('#pcg-mode-meta .pcg-checklist-slot');
	        const $certSlot = $('#pcg-mode-certificado .pcg-checklist-slot');

        if (mode === 'evaluacion' && $evalSlot.length) {
            $evalSlot.append($checklist);
            return;
        }

        if (mode === 'lecciones' && $lessonsSlot.length) {
            $lessonsSlot.append($checklist);
            return;
        }

	        if (mode === 'meta' && $metaSlot.length) {
	            $metaSlot.append($checklist);
	            return;
	        }

	        if (mode === 'certificado' && $certSlot.length) {
	            $certSlot.append($checklist);
	            return;
	        }

	        if ($courseAside.length) {
	            $courseAside.append($checklist);
	        }
	    }

    function placeCourseSidebar(mode) {
        placeCourseActions(mode);
        placeCourseChecklist(mode);
    }

    function updateCourseChecklist() {
        const $root = $('#pcg-course-checklist');
        if (!$root.length) return;

        const setDone = (key, done) => {
            const $item = $root.find(`.pcg-checklist-item[data-check="${key}"]`);
            if (!$item.length) return;
            $item.toggleClass('is-done', Boolean(done));
        };

        const hasValue = (selector) => {
            const $el = $(selector);
            if (!$el.length) return false;
            return String($el.val() || '').trim().length > 0;
        };

        const hasImage = (wrapSel) => {
            const $wrap = $(wrapSel);
            if (!$wrap.length || !$wrap.is(':visible')) return false;
            const src = String($wrap.find('img').attr('src') || '').trim();
            return src.length > 0;
        };

        const hasTeachers = () => {
            const $list = $('#pcg-teachers-list');
            if (!$list.length) return false;
            return $list.find('.pcg-teacher-item').filter(function () {
                const uid = String($(this).attr('data-user-id') || '').trim();
                return uid.length > 0;
            }).length > 0;
        };

        const getQuizInfo = () => {
            const $editor = $('#pcg-quiz-creator-container .pqc-editor-container').first();
            const quizId = Number($editor.data('quiz-id') || 0) || 0;
            if (!$editor.length || !quizId) return { exists: false, count: 0 };
            const count = $editor.find('.pqc-slide').length;
            return { exists: true, count };
        };

        const getLessonsInfo = () => {
            const $list = $('#pcg-lessons-list');
            if (!$list.length) return { exists: false, count: 0 };
            const count = $list.find('.pcg-content-item').length;
            return { exists: count > 0, count };
        };

        setDone('title', hasValue('#pcg-course-title'));
        setDone('price', hasValue('#pcg-course-price'));
        setDone('description', hasValue('#pcg-course-description'));
        setDone('excerpt', hasValue('#pcg-course-excerpt'));
        setDone('thumbnail', hasImage('#pcg-thumbnail-preview'));
        setDone('cover', hasImage('#pcg-cover-preview'));
        setDone('teachers', hasTeachers());

        const lessons = getLessonsInfo();
        setDone('lessons', lessons.exists);
        const $lessonsCount = $('#pcg-check-lessons-count');
        if ($lessonsCount.length) {
            $lessonsCount.text(lessons.exists ? String(lessons.count) : '');
        }

        const quiz = getQuizInfo();
        setDone('evaluation', quiz.exists);
        const $evalCount = $('#pcg-check-eval-count');
        if ($evalCount.length) {
            $evalCount.text(quiz.exists ? String(quiz.count) : '');
        }
    }

    function initChecklistObservers() {
        if (!window.MutationObserver) return;

        let timer = null;
        const schedule = () => {
            if (timer) clearTimeout(timer);
            timer = setTimeout(updateCourseChecklist, 50);
        };

        const observe = (el) => {
            if (!el) return;
            const obs = new MutationObserver(schedule);
            obs.observe(el, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'src', 'class', 'data-user-id'] });
        };

        observe(document.getElementById('pcg-teachers-list'));
        observe(document.getElementById('pcg-quiz-creator-container'));
        observe(document.getElementById('pcg-thumbnail-preview'));
        observe(document.getElementById('pcg-cover-preview'));
        observe(document.getElementById('pcg-lessons-list'));
    }

    // Preview Button click
    $previewBtn.on('click', function () {
        if (currentCoursePermalink) {
            window.open(currentCoursePermalink, '_blank');
        }
    });

    // Toggle between Curso, Lecciones and Evaluaciones (course form only)
    $(document).on('click', '#pcg-course-form-section .pcg-segment', function () {
        const $form = $('#pcg-course-form-section');

        $form.find('.pcg-segment').removeClass('active');
        $(this).addClass('active');

        const mode = $(this).data('value');
        $form.find('.pcg-mode-content').hide();

        if (mode === 'curso') {
            $('#pcg-mode-curso').fadeIn(300);
            placeCourseSidebar('curso');
        } else if (mode === 'lecciones') {
            $('#pcg-mode-lecciones').fadeIn(300);
            initSortable();
            placeCourseSidebar('lecciones');
            syncLessonsPriceFromMain();
            $('#pcg-course-price').trigger('input');
        } else if (mode === 'evaluacion') {
            const courseId = typeof currentCourseId !== 'undefined' ? currentCourseId : 0;
            const $evalAside = $('#pcg-mode-evaluacion .pcg-eval-editor__right');
            if (courseId === 0) {
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
                syncEvalPriceFromMain();
                $('#pcg-course-price').trigger('input');
                // Dynamically refresh quiz module for current course
                $(document).trigger('pqc_refresh', { courseId: courseId });
            }
            placeCourseSidebar('evaluacion');
            $('#pcg-mode-evaluacion').fadeIn(300);
	        } else if (mode === 'meta') {
	            $('#pcg-mode-meta').fadeIn(300);
	            placeCourseSidebar('meta');
	            plLearningMeta.render('course');
	        } else if (mode === 'certificado') {
	            $('#pcg-mode-certificado').fadeIn(300);
	            placeCourseSidebar('certificado');
	        }
	    });

    // Ensure actions start in the correct aside on initial render
    const initialMode = $('#pcg-course-form-section .pcg-segment.active').data('value') || 'curso';
    placeCourseSidebar(initialMode);
	    if (initialMode === 'lecciones') {
	        syncLessonsPriceFromMain();
	        $('#pcg-course-price').trigger('input');
	    } else if (initialMode === 'evaluacion') {
	        syncEvalPriceFromMain();
	        $('#pcg-course-price').trigger('input');
	    } else if (initialMode === 'meta') {
	        plLearningMeta.render('course');
	    } else if (initialMode === 'certificado') {
	        placeCourseSidebar('certificado');
	    }
    initChecklistObservers();
    $(document).on('input change', '#pcg-course-title, #pcg-course-price, #pcg-course-description, #pcg-course-excerpt, #pcg-course-price-eval, #pcg-course-price-lessons', updateCourseChecklist);
    updateCourseChecklist();

    // ── Teacher Management Logic ──
    let teacherSearchTimeout = null;

    function normalizePercentInt(value) {
        const n = Math.round(parseFloat(String(value).replace(',', '.')) || 0);
        if (Number.isNaN(n)) return 0;
        return Math.min(100, Math.max(0, n));
    }

    function normalizeTeacherIdentity(rawName = '', rawEmail = '') {
        let name = (rawName || '').trim();
        let email = (rawEmail || '').trim();

        const match = name.match(/^(.*)\s+\(([^()]+@[^()]+)\)$/);
        if (match) {
            name = match[1].trim();
            if (!email) {
                email = match[2].trim();
            }
        }

        return { name, email };
    }

    function getCurrentUserTeacherSeed() {
        const id = Number(pcgCreatorData && pcgCreatorData.currentUserId ? pcgCreatorData.currentUserId : 0) || 0;
        const name = (pcgCreatorData && pcgCreatorData.currentUserFullNameEmail) ? String(pcgCreatorData.currentUserFullNameEmail) : '';
        const avatar = (pcgCreatorData && pcgCreatorData.currentUserAvatar) ? String(pcgCreatorData.currentUserAvatar) : '';
        return { id, name, avatar };
    }

    function ensureTeachersEmptyState($list) {
        if (!$list || !$list.length) return;
        if ($list.find('.pcg-empty-teachers-state').length) return;
        $list.append(`
	            <div class="pcg-empty-teachers-state">
	                <p>${t('noCollaboratorsAssigned')}</p>
	            </div>
	        `);
    }

    function resetTeachersList($list) {
        if (!$list || !$list.length) return;
        $list.empty();
        $list.data('splitLocked', false);
        ensureTeachersEmptyState($list);
        $list.find('.pcg-empty-teachers-state').show();
    }

    function isEqualSplit(teachers) {
        const items = Array.isArray(teachers) ? teachers : [];
        const n = items.length;
        if (n <= 0) return true;

        const base = Math.floor(10000 / n);
        const remainder = 10000 - (base * n);
        const expected = items.map((_, i) => (base + (i === 0 ? remainder : 0)) / 100);

        const actual = items.map(t => Number(normalizePercentInt(t.profit_percentage ?? 0)));
        expected.sort((a, b) => a - b);
        actual.sort((a, b) => a - b);
        for (let i = 0; i < n; i++) {
            if (Math.abs(Number(expected[i]) - Number(actual[i])) > 0.01) return false;
        }
        return true;
    }

    function rebalanceTeachersEqual($list) {
        if (!$list || !$list.length) return;
        const $items = $list.find('.pcg-teacher-item');
        const n = $items.length;
        if (n <= 0) return;

        const base = Math.floor(10000 / n);
        const remainder = 10000 - (base * n);

        $items.each(function (idx) {
            const val = (base + (idx === 0 ? remainder : 0)) / 100;
            const intValue = normalizePercentInt(val);
            $(this).find('.pcg-teacher-profit').val(intValue);
            $(this).find('.pcg-teacher-share-badge').text(`${intValue}%`);
        });
    }

    function rebalanceMainAuthorRemainder($list, $changedItem = null) {
        if (!$list || !$list.length) return;
        const $items = $list.find('.pcg-teacher-item');
        if (!$items.length) return;

        let $main = $list.find('.pcg-teacher-item[data-main="true"]').first();
        if (!$main.length) {
            $main = $items.first();
        }

        const isChangedMain = $changedItem && $changedItem.length && $changedItem.is($main);

        const getVal = ($item) => normalizePercentInt($item.find('.pcg-teacher-profit').val());

        const $nonMain = $items.not($main);
        if (!$nonMain.length) {
            $main.find('.pcg-teacher-profit').val(100);
            $main.find('.pcg-teacher-share-badge').text('100%');
            return;
        }

        if (isChangedMain) {
            // Main author is always the remainder, so recompute it.
            let sumOthers = 0;
            $nonMain.each(function () {
                sumOthers += getVal($(this));
            });
            const mainVal = Math.max(0, 100 - sumOthers);
            $main.find('.pcg-teacher-profit').val(mainVal);
            $main.find('.pcg-teacher-share-badge').text(`${mainVal}%`);
            return;
        }

        // Clamp changed item so total never exceeds 100.
        if ($changedItem && $changedItem.length) {
            let otherOthersSum = 0;
            $nonMain.not($changedItem).each(function () {
                otherOthersSum += getVal($(this));
            });
            const maxForChanged = Math.max(0, 100 - otherOthersSum);
            let changedVal = getVal($changedItem);
            if (changedVal > maxForChanged) {
                changedVal = maxForChanged;
                $changedItem.find('.pcg-teacher-profit').val(changedVal);
                $changedItem.find('.pcg-teacher-share-badge').text(`${changedVal}%`);
            }
        }

        let sumNonMain = 0;
        $nonMain.each(function () {
            sumNonMain += getVal($(this));
        });

        const mainVal = Math.max(0, 100 - sumNonMain);
        $main.find('.pcg-teacher-profit').val(mainVal);
        $main.find('.pcg-teacher-share-badge').text(`${mainVal}%`);
    }

    function ensureTeacherForUser($list, user) {
        if (!$list || !$list.length) return;
        const userId = Number(user && user.id ? user.id : 0) || 0;
        if (!userId) return;

        const exists = $list.find(`.pcg-teacher-item[data-user-id="${userId}"]`).length > 0;
        if (exists) return;

        const defaultPct = $list.data('splitLocked') ? 1 : 0;
        addTeacherItem({
            user_id: userId,
            user_name: String(user.name || ''),
            user_email: String(user.email || ''),
            avatar: String(user.avatar || ''),
            role_slug: t('mainAuthorRoleSlug'),
            profit_percentage: defaultPct,
            role_description: ''
        }, $list);

        if (!$list.data('splitLocked')) {
            rebalanceTeachersEqual($list);
        } else {
            rebalanceMainAuthorRemainder($list);
        }
    }

    function populateTeachersList($list, teachers, authorFallback) {
        resetTeachersList($list);

        const items = Array.isArray(teachers) ? teachers : [];
        if (items.length > 0) {
            $list.data('splitLocked', !isEqualSplit(items));
            items.forEach((teacher) => {
                addTeacherItem({
                    user_id: teacher.id,
                    user_name: teacher.name,
                    avatar: teacher.avatar || '',
                    role_slug: teacher.role_slug || '',
                    profit_percentage: teacher.profit_percentage || '0',
                    role_description: teacher.role_description || '',
                    is_main_author: teacher.is_main_author || false,
                    approval_status: teacher.approval_status || '',
                }, $list);
            });
            return;
        }

        if (authorFallback && authorFallback.id) {
            addTeacherItem({
                user_id: authorFallback.id,
                user_name: authorFallback.name,
                avatar: authorFallback.avatar || '',
                is_main_author: true,
                role_slug: t('mainAuthorRoleSlug'),
                profit_percentage: 100
            }, $list);
        }
    }

    function collectTeachers($list) {
        const teachers = [];
        if (!$list || !$list.length) return teachers;

        $list.find('.pcg-teacher-item').each(function () {
            const userId = $(this).attr('data-user-id');
            if (!userId) return;
            teachers.push({
                user_id: userId,
                role_slug: $(this).find('.pcg-teacher-role-slug').val(),
                profit_percentage: normalizePercentInt($(this).find('.pcg-teacher-profit').val()),
                role_description: $(this).find('.pcg-teacher-description').val()
            });
        });

        return teachers;
    }

    function addTeacherItem(data = {}, $targetList = null) {
        const $list = ($targetList && $targetList.length) ? $targetList : $('#pcg-teachers-list');
        $list.find('.pcg-empty-teachers-state').hide();

        const userId = data.user_id || '';
        const identity = normalizeTeacherIdentity(data.user_name || '', data.user_email || data.email || '');
        const userName = identity.name;
        const userEmail = identity.email;
        const avatarUrl = data.avatar || '';
        const roleSlug = data.role_slug || '';
        const roleDescription = data.role_description || '';
        const profitPercentage = String(normalizePercentInt(data.profit_percentage ?? 0));
        const isMainAuthor = data.is_main_author || false;
        const approvalStatus = String(data.approval_status || '');
        const hasSelectedUser = Boolean(userId && userName);

        const iconHtml = avatarUrl ? `<img src="${avatarUrl}" class="pcg-item-avatar">` : '<span class="dashicons dashicons-admin-users"></span>';

        const removeBtnHtml = isMainAuthor ? '' : `
	            <button type="button" class="pcg-item-btn-remove pcg-teacher-remove" title="${t('delete')}">
	                <span class="dashicons dashicons-trash"></span>
	            </button>
	        `;

        const itemHtml = `
		            <div class="pcg-content-item pcg-teacher-item" data-user-id="${userId}" ${isMainAuthor ? 'data-main="true"' : ''}>
	                <div class="pcg-item-header">
	                    <div class="pcg-item-expand" title="${t('viewDetails')}">
	                        <span class="dashicons dashicons-arrow-right-alt2"></span>
	                    </div>
                    <div class="pcg-item-icon">
                        ${iconHtml}
                    </div>
	                    <div class="pcg-item-input-wrapper">
	                        <input type="text" class="pcg-item-input pcg-teacher-name-input" 
	                               value="${hasSelectedUser ? '' : userName}" 
	                               placeholder="${t('searchCollaborator')}" 
	                               ${isMainAuthor ? 'readonly' : ''} 
	                               autocomplete="off"
	                               style="${hasSelectedUser ? 'display:none;' : ''}">
                        <div class="pcg-teacher-identity ${hasSelectedUser ? '' : 'pcg-teacher-identity-hidden'}">
                            <span class="pcg-teacher-full-name">${userName}</span>
                            <span class="pcg-teacher-email">${userEmail}</span>
                        </div>
                        <div class="pcg-search-results" style="display:none;"></div>
                    </div>
		                    <div class="pcg-item-actions">
		                        <span class="pcg-teacher-share-badge">${profitPercentage}%</span>
		                        ${approvalStatus === 'pending' ? `<span class="pcg-badge pcg-badge--pending pcg-badge--teacher">${t('waitingApproval')}</span>` : ''}
		                        ${isMainAuthor ? `<span class="pcg-badge-main-author">${t('mainAuthor')}</span>` : ''}
		                        ${removeBtnHtml}
		                    </div>
	                </div>
	                <div class="pcg-item-details" style="display:none;">
	                    <div class="pcg-detail-row">
	                        <div class="pcg-detail-field">
	                            <label>${t('role')}</label>
	                            <input type="text" class="pcg-teacher-role-slug" value="${roleSlug}" placeholder="${t('roleSlugPlaceholder')}">
	                        </div>
	                        <div class="pcg-detail-field">
	                            <label>${t('participationLabel')}</label>
	                            <input type="number" class="pcg-teacher-profit" value="${profitPercentage}" min="0" max="100" step="1">
	                        </div>
	                    </div>
	                    <div class="pcg-detail-row">
	                        <div class="pcg-detail-field" style="flex:1;">
	                            <label>${t('roleDescriptionLabel')}</label>
	                            <textarea class="pcg-teacher-description" placeholder="${t('describeResponsibilities')}">${roleDescription}</textarea>
	                        </div>
	                    </div>
	                </div>
	            </div>
	        `;

        const $newItem = $(itemHtml);
        $list.append($newItem);
        if (!userName) $newItem.find('.pcg-teacher-name-input').focus();
    }

    // Add Teacher PLUS button
    $(document).on('click', '.pcg-btn-add-teacher, #pcg-btn-add-teacher', function () {
        const targetSel = $(this).attr('data-target') || '#pcg-teachers-list';
        addTeacherItem({}, $(targetSel));
    });

    // Remove Teacher
    $(document).on('click', '.pcg-teacher-remove', function () {
        const $item = $(this).closest('.pcg-teacher-item');
        const $list = $item.closest('.pcg-items-list');
        $item.fadeOut(300, function () {
            $(this).remove();
            if ($list.children('.pcg-teacher-item').length === 0) {
                $list.find('.pcg-empty-teachers-state').fadeIn(300);
            } else if (!$list.data('splitLocked')) {
                rebalanceTeachersEqual($list);
            } else {
                rebalanceMainAuthorRemainder($list);
            }
        });
    });

    // Teacher input search logic
    $(document).on('input', '.pcg-teacher-name-input', function () {
        const $input = $(this);
        const $wrapper = $input.closest('.pcg-item-input-wrapper');
        const $results = $wrapper.find('.pcg-search-results');
        const query = $input.val().trim();

        clearTimeout(teacherSearchTimeout);
        if (query.length < 2) {
            $results.hide().empty();
            return;
        }

        teacherSearchTimeout = setTimeout(() => {
            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: pcgCreatorData.teacherSearchAction,
                    nonce: pcgCreatorData.teacherSearchNonce,
                    q: query
                },
                success: function (response) {
                    if (response.success && response.data.length > 0) {
                        $results.empty().show();
                        response.data.forEach(user => {
                            const $resItem = $(`
                                <div class="pcg-search-result-item">
                                    <img src="${user.avatar}" class="pcg-result-avatar">
                                    <div class="pcg-result-info">
                                        <span class="pcg-result-name">${user.name}</span>
                                    </div>
                                </div>
                            `);
                            $resItem.on('click', function () {
                                const selectedIdentity = normalizeTeacherIdentity(user.name || '', user.email || '');
                                $input.val('');
                                const $item = $input.closest('.pcg-teacher-item');
                                const $list = $item.closest('.pcg-items-list');
                                $item.attr('data-user-id', user.id);
                                $item.find('.pcg-item-icon').html(`<img src="${user.avatar}" class="pcg-item-avatar">`);
                                $item.find('.pcg-teacher-full-name').text(selectedIdentity.name);
                                $item.find('.pcg-teacher-email').text(selectedIdentity.email);
                                $item.find('.pcg-teacher-identity').removeClass('pcg-teacher-identity-hidden');
                                $input.hide();
                                $results.hide().empty();

                                if (!$list.data('splitLocked')) {
                                    rebalanceTeachersEqual($list);
                                } else {
                                    rebalanceMainAuthorRemainder($list, $item);
                                }
                            });
                            $results.append($resItem);
                        });
                    } else {
                        $results.hide().empty();
                    }
                }
            });
        }, 300);
    });

    // Hide teacher search results when clicking outside
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.pcg-item-input-wrapper').length) {
            $('.pcg-teacher-item .pcg-search-results').hide().empty();
        }
    });

    $(document).on('input change', '.pcg-teacher-profit', function () {
        const intValue = normalizePercentInt($(this).val());
        $(this).val(intValue);
        const $list = $(this).closest('.pcg-items-list');
        $list.data('splitLocked', true);
        $(this).closest('.pcg-teacher-item').find('.pcg-teacher-share-badge').text(`${intValue}%`);
        rebalanceMainAuthorRemainder($list, $(this).closest('.pcg-teacher-item'));
    });

    function initSortable() {
        if ($.fn.sortable) {
            $('#pcg-lessons-list').sortable({
                axis: 'y',
                containment: 'parent',
                placeholder: 'pcg-sortable-placeholder',
                forcePlaceholderSize: true,
                cancel: 'input, button, .pcg-item-btn-remove',
                opacity: 0.8,
                tolerance: 'pointer',
                refreshPositions: true,
                start: function (e, ui) {
                    ui.placeholder.height(ui.item.outerHeight());
                }
            });
        }
    }

    // Show/Hide Add Dropdown
    $('#pcg-btn-add-content').on('click', function (e) {
        e.stopPropagation();
        $('#pcg-add-dropdown').fadeToggle(200);
    });

    $(document).on('click', function () {
        $('#pcg-add-dropdown').fadeOut(200);
    });

    // Add Lesson or Section
    $('.pcg-add-option').on('click', function () {
        const type = $(this).data('type');
        addContentItem(type);
        $('#pcg-add-dropdown').fadeOut(200);
    });

    function addContentItem(type, data = {}) {
        $('.pcg-empty-lessons-state').hide();

        const iconClass = type === 'section' ? 'dashicons-menu' : 'dashicons-media-text';
        const typeLabel = type === 'section' ? t('newSection') : t('newLesson');
        const itemClass = type === 'section' ? 'item-section' : 'item-lesson';

        const title = typeof data === 'string' ? data : (data.title || '');
        const videoUrl = data.video_url || '';
        const availableDate = data.available_date || '';

        let expandHtml = '';
        let detailsHtml = '';

        if (type === 'lesson') {
            expandHtml = `
	                <div class="pcg-item-expand" title="${t('expandDetails')}">
	                    <span class="dashicons dashicons-arrow-right-alt2"></span>
	                </div>
	            `;
            detailsHtml = `
	                <div class="pcg-item-details" style="display:none;">
	                    <div class="pcg-detail-row">
	                        <div class="pcg-detail-field">
	                            <label>${t('youtubeUrl')}</label>
	                            <input type="text" class="pcg-lesson-video-url" value="${videoUrl}" placeholder="https://youtube.com/watch?v=...">
	                        </div>
	                        <div class="pcg-detail-field">
	                            <label>${t('availableOn')}</label>
	                            <input type="date" class="pcg-lesson-available-date" value="${availableDate}">
	                        </div>
	                    </div>
	                    <div class="pcg-detail-actions">
	                        <button type="button" class="pcg-btn-add-text">${t('addText')}</button>
	                    </div>
	                </div>
	            `;
        }

        const itemHtml = `
            <div class="pcg-content-item ${itemClass}" data-type="${type}">
                <div class="pcg-item-header">
                    ${expandHtml}
                    <div class="pcg-item-icon">
                        <span class="dashicons ${iconClass}"></span>
                    </div>
                    <div class="pcg-item-input-wrapper">
                        <input type="text" class="pcg-item-input" value="${title}" placeholder="${typeLabel}...">
                    </div>
	                    <div class="pcg-item-actions">
	                        <button type="button" class="pcg-item-btn-remove" title="${t('removeItem')}">
	                            <span class="dashicons dashicons-trash"></span>
	                        </button>
	                        <div class="pcg-item-drag-handle">
	                            <span class="dashicons dashicons-menu"></span>
	                        </div>
	                    </div>
                </div>
                ${detailsHtml}
            </div>
        `;

        const $newItem = $(itemHtml);
        $('#pcg-lessons-list').append($newItem);
        if (!title) $newItem.find('.pcg-item-input').focus();
        initSortable();
    }

    // Toggle Details
    $(document).on('click', '.pcg-item-expand', function (e) {
        e.stopPropagation();
        const $item = $(this).closest('.pcg-content-item');
        const $details = $item.find('.pcg-item-details');
        const $icon = $(this).find('.dashicons');

        $details.slideToggle(300);
        $icon.toggleClass('expanded');
    });

    // Remove item
    $(document).on('click', '.pcg-item-btn-remove', function () {
        $(this).closest('.pcg-content-item').fadeOut(300, function () {
            $(this).remove();
            if ($('#pcg-lessons-list').children('.pcg-content-item').length === 0) {
                $('.pcg-empty-lessons-state').fadeIn(300);
            }
        });
    });

    function openThumbnailUploader() {
        PL_Cropper.open({
            title: t('courseCover'),
            width: 360,
            height: 238,
            onSave: function (dataUrl) {
                saveCroppedImage(dataUrl, 'thumbnail');
            }
        });
    }

	    function openCoverUploader() {
	        PL_Cropper.open({
	            title: t('coverPhoto'),
	            width: 1024,
	            height: 768,
	            onSave: function (dataUrl) {
	                saveCroppedImage(dataUrl, 'cover');
	            }
	        });
	    }

	    function openCertificateUploader() {}

	    function openCertificateLogoUploader() {
	        PL_Cropper.open({
	            title: 'Logo',
	            width: 600,
	            height: 200,
	            onSave: function (dataUrl) {
	                saveCroppedImage(dataUrl, 'certificate_logo');
	            }
	        });
	    }

	    function openCertificateSignatureUploader() {
	        PL_Cropper.open({
	            title: 'Firma',
	            width: 600,
	            height: 200,
	            onSave: function (dataUrl) {
	                saveCroppedImage(dataUrl, 'certificate_signature');
	            }
	        });
	    }

	    // Media Uploader: click empty placeholders
	    $(document).on('click', '#pcg-course-form-section .pcg-media-card__empty', function (e) {
	        e.preventDefault();
	        const type = $(this).attr('data-upload') || '';
	        if (type === 'thumbnail') openThumbnailUploader();
	        if (type === 'cover') openCoverUploader();
	        // Certificate template upload removed from UI.
	        if (type === 'certificate_logo') openCertificateLogoUploader();
	        if (type === 'certificate_signature') openCertificateSignatureUploader();
	    });

	    // Keyboard support for empty placeholders (Enter / Space)
	    $(document).on('keydown', '#pcg-course-form-section .pcg-media-card__empty[role="button"]', function (e) {
	        if (e.key === 'Enter' || e.key === ' ') {
	            e.preventDefault();
	            $(this).trigger('click');
	        }
	    });

    function saveCroppedImage(dataUrl, type) {
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_upload_cropped_image',
                nonce: pcgCreatorData.nonce,
                image_data: dataUrl,
                type: type
            },
	            success: function (response) {
	                if (response.success) {
	                    const attachment = response.data;
	                    if (type === 'thumbnail') {
	                        thumbnailId = attachment.id;
	                        $('#pcg-thumbnail-preview img').attr('src', attachment.url);
	                        $('#pcg-thumbnail-preview').fadeIn();
	                    } else if (type === 'cover') {
	                        coverPhotoId = attachment.id;
	                        $('#pcg-cover-preview img').attr('src', attachment.url);
	                        $('#pcg-cover-preview').fadeIn();
	                    } else if (type === 'certificate_logo') {
	                        certificateLogoAttachmentId = attachment.id;
	                        $('#pcg-certificate-logo-preview img').attr('src', attachment.url);
	                        $('#pcg-certificate-logo-preview').fadeIn();
	                        updateCertificatePreview();
	                    } else if (type === 'certificate_signature') {
	                        certificateSignatureAttachmentId = attachment.id;
	                        $('#pcg-certificate-signature-preview img').attr('src', attachment.url);
	                        $('#pcg-certificate-signature-preview').fadeIn();
	                        updateCertificatePreview();
	                    }
	                } else {
	                    alert(t('errorPrefix') + response.data.message);
	                }
	            },
            error: function () {
                alert(t('errorUploadingImage'));
            }
        });
    }

    $('#pcg-remove-thumbnail').on('click', function () {
        thumbnailId = 0;
        $('#pcg-thumbnail-preview').fadeOut();
    });

	    $('#pcg-remove-cover').on('click', function () {
	        coverPhotoId = 0;
	        $('#pcg-cover-preview').fadeOut();
	    });

	    // Certificate template upload removed from UI.

	    $('#pcg-remove-certificate-logo').on('click', function () {
	        certificateLogoAttachmentId = 0;
	        $('#pcg-certificate-logo-preview').fadeOut();
	        updateCertificatePreview();
	    });

	    $('#pcg-remove-certificate-signature').on('click', function () {
	        certificateSignatureAttachmentId = 0;
	        $('#pcg-certificate-signature-preview').fadeOut();
	        updateCertificatePreview();
	    });

		    function escapeText(s) {
		        return (s || '').toString();
		    }

		    function updateCertificatePreview() {}

		    function updatePublishButton() {
		        const $btn = $('#pcg-btn-toggle-publish-course');
		        if (!$btn.length) return;
		        const isPublished = currentCourseStatus === 'publish';
		        $btn.attr('data-status', currentCourseStatus);
		        $btn.toggleClass('is-unpublish', isPublished);
		        $btn.text(isPublished ? 'UNPUBLISH' : 'PUBLISH');
		    }

		    $(document).on('click', '#pcg-btn-toggle-publish-course', function () {
		        if (!currentCourseId) return;
		        currentCourseStatus = currentCourseStatus === 'publish' ? 'draft' : 'publish';
		        updatePublishButton();
		        $('.pcg-btn-save-course').trigger('click');
		    });

		    updatePublishButton();

	    $(document).on('input', '#pcg-certificate-title, #pcg-certificate-congrats, #pcg-cert-signature-label', function () {
	        if (this.id === 'pcg-certificate-congrats') {
	            updateWordCount('#pcg-certificate-congrats', '#pcg-cert-word-count', 50);
	        }
	        updateCertificatePreview();
	    });

	    // Handle Enter key on inputs to "save" (blur)
	    $(document).on('keypress', '.pcg-item-input', function (e) {
	        if (e.which === 13) {
	            $(this).blur();
        }
    });

    // Save Course Logic
    $('.pcg-btn-save-course').on('click', function () {
        function setSaveButtonState($button, state) {
            const $icon = $button.find('.dashicons').first();
            if ($icon.length) {
                $icon.removeClass('dashicons-saved dashicons-update dashicons-yes-alt dashicons-warning');
                if (state === 'loading') $icon.addClass('dashicons-update');
                else if (state === 'success') $icon.addClass('dashicons-yes-alt');
                else if (state === 'error') $icon.addClass('dashicons-warning');
                else $icon.addClass('dashicons-saved');
            }
        }

        // Trigger Quiz Save if the editor is active
        try {
            $(document).trigger('pqc_save', [{ silent: true }]);
        } catch (_) {}

        const $btn = $(this);

	        const meta = plLearningMeta.getPayload('course');
			        const courseData = {
			            id: currentCourseId,
			            status: currentCourseStatus,
			            title: $('#pcg-course-title').val(),
			            description: $('#pcg-course-description').val(),
			            excerpt: $('#pcg-course-excerpt').val(),
			            price: $('#pcg-course-price').val(),
		            thumbnail_id: thumbnailId,
		            cover_photo_id: coverPhotoId,
		            certificate_attachment_id: certificateAttachmentId,
			            certificate_title: $('#pcg-certificate-title').val(),
			            certificate_congrats: $('#pcg-certificate-congrats').val(),
			            certificate_logo_attachment_id: certificateLogoAttachmentId,
			            certificate_signature_attachment_id: certificateSignatureAttachmentId,
			            certificate_signature_label: $('#pcg-cert-signature-label').val(),
			            progression: $('#pcg-course-progression').is(':checked') ? 'on' : '',
	            teachers: [],
	            content: [],
	            category_ids: meta.category_ids,
	            tag_ids: meta.tag_ids,
	        };

        courseData.teachers = collectTeachers($('#pcg-teachers-list'));

        $('#pcg-lessons-list .pcg-content-item').each(function () {
            courseData.content.push({
                type: $(this).data('type'),
                title: $(this).find('.pcg-item-input').val(),
                video_url: $(this).find('.pcg-lesson-video-url').val() || '',
                available_date: $(this).find('.pcg-lesson-available-date').val() || ''
            });
        });

        if (!courseData.title) {
            alert(t('pleaseEnterCourseTitle'));
            setSaveButtonState($btn, 'default');
            return;
        }

        setSaveButtonState($btn, 'loading');
        $btn.addClass('loading').prop('disabled', true);

        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_save_course',
                nonce: pcgCreatorData.nonce,
                course_data: courseData
            },
            success: function (response) {
                $btn.removeClass('loading');
                if (response.success) {
                    currentCourseId = response.data.course_id;
                    if (response.data && response.data.status) {
                        currentCourseStatus = response.data.status;
                        updatePublishButton();
                    }
                    $('#pcg-current-course-id').val(currentCourseId);
                    setSaveButtonState($btn, 'success');
                    $btn.addClass('success');
                    refreshActiveList();
                    setTimeout(() => {
                        $btn.prop('disabled', false).removeClass('success');
                        setSaveButtonState($btn, 'default');
                    }, 2000);

                    if (response.data.permalink) {
                        currentCoursePermalink = response.data.permalink;
                        $previewBtn.fadeIn();
                    }
                } else {
                    alert(t('errorPrefix') + response.data.message);
                    setSaveButtonState($btn, 'error');
                    $btn.prop('disabled', false);
                    setTimeout(() => setSaveButtonState($btn, 'default'), 2000);
                }
            },
            error: function () {
                $btn.removeClass('loading');
                alert(t('errorSavingCourse'));
                setSaveButtonState($btn, 'error');
                $btn.prop('disabled', false);
                setTimeout(() => setSaveButtonState($btn, 'default'), 2000);
            }
        });
    });



    // Load My Courses
    function loadMyCourses() {
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_get_my_courses',
                nonce: pcgCreatorData.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderCourses(response.data);
                }
            }
        });
    }

    function getListContext() {
        if ($('#specialization-grid').length > 0) return 'specializations';
        if ($('#programas-grid').length > 0) return 'programas';
        if ($('#pcg-my-escritos-grid').length > 0) return 'escritos';
        return 'courses';
    }

    function getActiveGrid() {
        const context = getListContext();
        if (context === 'specializations') return $('#specialization-grid');
        if (context === 'programas') return $('#programas-grid');
        if (context === 'escritos') return $('#pcg-my-escritos-grid');
        return $('#pcg-my-courses-grid');
    }

    function refreshActiveList() {
        const context = getListContext();
        if (context === 'specializations') return loadMySpecializations();
        if (context === 'programas') return loadMyProgramas();
        if (context === 'escritos') return loadMyEscritos();
        return loadMyCourses();
    }

    // Load My Specializations (LearnDash Groups)
    function loadMySpecializations() {
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_get_my_specializations',
                nonce: pcgCreatorData.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderSpecializations(response.data);
                }
            }
        });
    }

    function loadMyProgramas() {
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_get_my_programas',
                nonce: pcgCreatorData.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderProgramas(response.data);
                }
            }
        });
    }

    function loadMyEscritos() {
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_get_my_escritos',
                nonce: pcgCreatorData.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderEscritos(response.data);
                }
            }
        });
    }

    function renderEscritos(escritos) {
        const $grid = getActiveGrid();
        $grid.empty();

        if (escritos.length === 0) {
            $grid.append(`<p class="pcg-empty-msg">${t('noEscritosYet')}</p>`);
            return;
        }

        escritos.forEach(escrito => {
            const thumb = escrito.thumbnail_url || '';
            const permalinkHtml = escrito.permalink ? `onclick="window.open('${escrito.permalink}', '_blank');"` : '';
            const trimTitle = (text, max) => {
                const s = String(text ?? '');
                const m = Number(max) || 50;
                if (s.length <= m) return s;
                return s.slice(0, m) + '...';
            };

            const hasThumb = !!thumb;
            const cardClass = hasThumb ? 'pcg-course-card pcg-escrito-card' : 'pcg-course-card pcg-escrito-card pcg-escrito-card--no-thumb';
            const titleText = hasThumb ? trimTitle(escrito.title, 50) : String(escrito.title ?? '');
            const cardHtml = `
	                <div class="${cardClass}" data-id="${escrito.id}">
	                    ${hasThumb ? `
	                        <div class="pcg-course-thumb" ${permalinkHtml} style="cursor: pointer;">
	                            <img src="${thumb}" alt="${escrito.title}">
	                        </div>
	                    ` : ''}
	                    <div class="pcg-course-content">
	                        <h4 class="pcg-course-title" ${permalinkHtml} style="cursor: pointer;">${titleText}</h4>
	                        <div class="pcg-course-meta">
	                            <span class="pcg-escrito-date">${escrito.date}</span>
	                            ${escrito.status === 'draft' ? `<span class="pcg-badge" style="background:#e5e7eb; color:#475569; font-size:10px; margin-left:8px; padding:2px 8px; border-radius:10px;">Borrador</span>` : ''}
	                            <div class="pcg-course-actions">
	                                <button class="pcg-btn-edit-escrito pcg-card-action-edit" title="${t('edit')}" type="button">EDITAR</button>
	                                <button class="pcg-btn-delete-escrito pcg-card-action-delete" aria-label="Delete" title="${t('delete')}" type="button">
	                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
	                                        <path d="M3 6h18"></path>
	                                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
	                                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
	                                        <line x1="10" y1="11" x2="10" y2="17"></line>
	                                        <line x1="14" y1="11" x2="14" y2="17"></line>
	                                    </svg>
	                                </button>
	                            </div>
	                        </div>
	                    </div>
	                </div>
	            `;
            $grid.append(cardHtml);
        });
    }

    function renderCourses(courses) {
        const $grid = getActiveGrid();
        $grid.empty();

        if (courses.length === 0) {
            $grid.append(`<p class="pcg-empty-msg">${t('noPublishedCoursesYet')}</p>`);
            return;
        }

        courses.forEach(course => {
            const thumb = course.thumbnail_url || '';
            const thumbClass = thumb ? '' : ' pcg-course-thumb--no-image';
            const cardHtml = `
                <div class="pcg-course-card" data-id="${course.id}">
                    <div class="pcg-course-thumb${thumbClass}">
                        ${thumb ? `<img src="${thumb}" alt="${course.title}">` : ''}
                        <div class="pcg-course-badges">
                            <span class="pcg-badge pcg-badge-count">${course.lesson_count} ${t('lessons')}</span>
                        </div>
                    </div>
                    <div class="pcg-course-content">
                        <h4>${course.title}</h4>
                        <div class="pcg-course-meta">
                            <span class="pcg-course-price">${course.price}</span>
                            <div class="pcg-course-actions">
                                <button class="pcg-btn-edit-course pcg-card-action-edit" title="${t('edit')}" type="button">EDITAR</button>
                                <button class="pcg-btn-delete-course pcg-card-action-delete" aria-label="Delete" title="${t('delete')}" type="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 6h18"></path>
                                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $grid.append(cardHtml);
        });
    }

    function renderSpecializations(groups) {
        const $grid = getActiveGrid();
        $grid.empty();

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        if (!groups || groups.length === 0) {
            $grid.append(`
                <div class="pcg-specialization-card pcg-specialization-card--empty">
                    <div class="pcg-specialization-thumb pcg-course-thumb">
                        <div class="pcg-empty-specialization-message">${t('createYourSpecialization')}</div>
                    </div>
                    <div class="pcg-specialization-content pcg-course-content">
                        <div class="pcg-specialization-meta pcg-course-meta"></div>
                    </div>
                </div>
            `);
            return;
        }

        groups.forEach(group => {
            const isPending = Boolean(group.is_pending_approval);
            const approval = (pendingApprovalsIndex.group && pendingApprovalsIndex.group[Number(group.id)]) ? pendingApprovalsIndex.group[Number(group.id)] : null;
            const canEdit = group.can_edit !== undefined ? Boolean(group.can_edit) : true;
            const countLabel = (group.course_count === 1) ? `1 ${t('courseSingular')}` : `${group.course_count} ${t('coursesPlural')}`;
            const thumb = group.thumbnail_url || '';
            const thumbClass = thumb ? '' : ' pcg-course-thumb--no-image';
            const permalink = group.permalink || '';
            const canDelete = Boolean(group.can_delete);
            const deleteBtnHtml = canDelete ? `
                <button class="pcg-btn-delete-specialization pcg-card-action-delete" aria-label="Delete" title="${t('delete')}" type="button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </button>
            ` : '';
            const courseTitles = Array.isArray(group.course_titles) ? group.course_titles : [];
            const courseListHtml = courseTitles.length ? `
                <ul class="pcg-specialization-course-list">
                    ${courseTitles.map(t => `<li>${escapeHtml(t)}</li>`).join('')}
                </ul>
            ` : '';

            const approvalActionsHtml = approval ? `
                <div class="pcg-card-approval-actions" data-snapshot-id="${approval.snapshot_id}">
                    <span class="pcg-card-approval-pct">${formatPercent(approval.profit_percentage)}%</span>
                    <button type="button" class="pcg-btn-outline pcg-btn-outline--small pcg-card-approval-approve">${t('approve')}</button>
                    <button type="button" class="pcg-btn-outline pcg-btn-outline--small pcg-btn-outline--danger pcg-card-approval-reject">${t('reject')}</button>
                </div>
            ` : '';

            const editBtnHtml = canEdit ? `
	                    <button class="pcg-btn-edit-specialization pcg-card-action-edit" title="${t('edit')}" type="button">EDITAR</button>
	            ` : '';

            const cardHtml = `
	                <div class="pcg-specialization-card${isPending ? ' pcg-card--pending' : ''}" data-id="${group.id}" data-permalink="${permalink}" data-pending="${isPending ? 1 : 0}">
	                    <div class="pcg-specialization-thumb pcg-course-thumb${thumbClass}">
	                        ${thumb ? `<img src="${thumb}" alt="${escapeHtml(group.title)}">` : ''}
		                        <div class="pcg-course-badges">
		                            <span class="pcg-badge pcg-badge-count">${countLabel}</span>
		                            ${isPending ? `<span class="pcg-badge pcg-badge--pending">${t('pendingApproval')}</span>` : ''}
		                        </div>
		                        <div class="pcg-specialization-thumb-actions">
		                            ${editBtnHtml}
		                            ${deleteBtnHtml}
		                        </div>
	                    </div>
                    <div class="pcg-specialization-content pcg-course-content">
                        <h4>${escapeHtml(group.title)}</h4>
                        ${courseListHtml}
                        ${approvalActionsHtml}
                        <div class="pcg-specialization-meta pcg-course-meta">
                            <span class="pcg-course-price"></span>
                            <div class="pcg-course-actions"></div>
                        </div>
                    </div>
                </div>
            `;
            $grid.append(cardHtml);
        });
    }

    function renderProgramas(programas) {
        const $grid = getActiveGrid();
        $grid.empty();

        if (!programas || programas.length === 0) {
            $grid.append(`
	                <div class="pcg-course-card pcg-course-card--empty">
	                    <div class="pcg-course-thumb">
	                        <div class="pcg-empty-specialization-message">${t('createYourProgram')}</div>
	                    </div>
	                    <div class="pcg-course-content">
	                        <div class="pcg-course-meta"></div>
	                    </div>
	                </div>
            `);
            return;
        }

        programas.forEach(programa => {
            const isPending = Boolean(programa.is_pending_approval);
            const approval = (pendingApprovalsIndex.program && pendingApprovalsIndex.program[Number(programa.id)]) ? pendingApprovalsIndex.program[Number(programa.id)] : null;
            const canEdit = programa.can_edit !== undefined ? Boolean(programa.can_edit) : true;
            const countLabel = (programa.group_count === 1) ? `1 ${t('groupSingular')}` : `${programa.group_count} ${t('groupsPlural')}`;
            const thumb = programa.thumbnail_url || '';
            const thumbClass = thumb ? '' : ' pcg-course-thumb--no-image';
            const permalink = programa.permalink || '';
            const price = programa.price ? programa.price : '';
            const canDelete = Boolean(programa.can_delete);
            const deleteBtnHtml = canDelete ? `
	                <button class="pcg-btn-delete-programa pcg-card-action-delete" aria-label="Delete" title="${t('delete')}" type="button">
	                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
	                        <path d="M3 6h18"></path>
	                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
	                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
	                        <line x1="10" y1="11" x2="10" y2="17"></line>
	                        <line x1="14" y1="11" x2="14" y2="17"></line>
	                    </svg>
	                </button>
	            ` : '';
            const editBtnHtml = canEdit ? `
		                                <button class="pcg-btn-edit-programa pcg-card-action-edit" title="${t('edit')}" type="button">EDITAR</button>
	            ` : '';

            const approvalActionsHtml = approval ? `
                    <div class="pcg-card-approval-actions" data-snapshot-id="${approval.snapshot_id}">
                        <span class="pcg-card-approval-pct">${formatPercent(approval.profit_percentage)}%</span>
                        <button type="button" class="pcg-btn-outline pcg-btn-outline--small pcg-card-approval-approve">${t('approve')}</button>
                        <button type="button" class="pcg-btn-outline pcg-btn-outline--small pcg-btn-outline--danger pcg-card-approval-reject">${t('reject')}</button>
                    </div>
                ` : '';

            const cardHtml = `
	                <div class="pcg-programa-card pcg-course-card${isPending ? ' pcg-card--pending' : ''}" data-id="${programa.id}" data-permalink="${permalink}" data-pending="${isPending ? 1 : 0}">
	                    <div class="pcg-course-thumb${thumbClass}">
	                        ${thumb ? `<img src="${thumb}" alt="${programa.title}">` : ''}
	                        <div class="pcg-course-badges">
	                            <span class="pcg-badge pcg-badge-count">${countLabel}</span>
	                            ${isPending ? `<span class="pcg-badge pcg-badge--pending">${t('pendingApproval')}</span>` : ''}
	                        </div>
	                    </div>
	                    <div class="pcg-course-content">
	                        <h4>${programa.title}</h4>
                            ${approvalActionsHtml}
	                        <div class="pcg-course-meta">
		                            <span class="pcg-course-price">${price}</span>
		                            <div class="pcg-course-actions">
		                                ${editBtnHtml}
		                                ${deleteBtnHtml}
		                            </div>
		                        </div>
	                    </div>
                </div>
            `;
            $grid.append(cardHtml);
        });
    }

    // Programa card navigation (open permalink when clicking the card)
    $(document).on('click', '.pcg-programa-card', function (e) {
        if ($(e.target).closest('button, a, input, select, textarea').length) {
            return;
        }

        const isPending = Number($(this).attr('data-pending') || 0) === 1;
        if (isPending) {
            alert(t('pendingApprovalNotice'));
            return;
        }

        const permalink = $(this).attr('data-permalink') || '';
        if (permalink) window.location.href = permalink;
    });

    // Specialization card navigation (open permalink when clicking the card)
    $(document).on('click', '.pcg-specialization-card', function (e) {
        if ($(e.target).closest('button, a, input, select, textarea').length) {
            return;
        }

        const isPending = Number($(this).attr('data-pending') || 0) === 1;
        if (isPending) {
            alert(t('pendingApprovalNotice'));
            return;
        }

        const permalink = $(this).attr('data-permalink') || '';
        if (permalink) window.location.href = permalink;
    });

    function showEditLoadingState() {
        resetForm();
        $('#pcg-my-courses-section').hide();
        $('#pcg-course-form-section').show();
        $('.pcg-mode-content').hide();

        if (!$('#pcg-edit-loading').length) {
            $('#pcg-course-form-section').append(`
	                <div id="pcg-edit-loading" class="pcg-loading-placeholder">
	                    <span class="dashicons dashicons-update spin"></span>
	                    <p>${t('loadingCourse')}</p>
	                </div>
	            `);
        }

        $('#pcg-edit-loading').show();
    }

    function hideEditLoadingState() {
        $('#pcg-edit-loading').hide();
        $('#pcg-mode-curso').show();
    }

    // Edit Course
    $(document).on('click', '.pcg-btn-edit-course', function () {
        const $editBtn = $(this);
        const courseId = $editBtn.closest('.pcg-course-card').data('id');
        if (!courseId) return;

        showEditLoadingState();
        $editBtn.prop('disabled', true);

        if (editCourseRequest && editCourseRequest.readyState !== 4) {
            editCourseRequest.abort();
        }

        editCourseRequest = $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_get_course_for_edit',
                nonce: pcgCreatorData.nonce,
                course_id: courseId
            },
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    currentCourseId = data.id;
                    $('#pcg-current-course-id').val(currentCourseId);
                    $('#pcg-course-title').val(data.title);
                    $('#pcg-course-description').val(data.description);
                    $('#pcg-course-excerpt').val(data.excerpt || '');
                    updateWordCount('#pcg-course-description', '#pcg-desc-word-count', 700);
                    updateWordCount('#pcg-course-excerpt', '#pcg-excerpt-word-count', 50);
	                    $('#pcg-course-price').val(data.price);
	                    $('#pcg-course-price').trigger('input');
	                    syncLessonsPriceFromMain();
	                    syncEvalPriceFromMain();
	                    syncMetaPriceFromMain();
	                    syncCertPriceFromMain();
	                    currentCourseStatus = data.status || 'publish';
	                    updatePublishButton();
	                    thumbnailId = data.thumbnail_id;
                    if (data.thumbnail_url) {
                        $('#pcg-thumbnail-preview img').attr('src', data.thumbnail_url);
                        $('#pcg-thumbnail-preview').show();
                    } else {
                        $('#pcg-thumbnail-preview').hide();
                    }

	                    coverPhotoId = data.cover_photo_id;
	                    if (data.cover_photo_url) {
	                        $('#pcg-cover-preview img').attr('src', data.cover_photo_url);
	                        $('#pcg-cover-preview').show();
	                    } else {
	                        $('#pcg-cover-preview').hide();
	                    }

	                    certificateAttachmentId = Number(data.certificate_attachment_id || 0) || 0;

	                    $('#pcg-certificate-title').val(data.certificate_title || '');
		                    $('#pcg-certificate-congrats').val(data.certificate_congrats || '');
		                    updateWordCount('#pcg-certificate-congrats', '#pcg-cert-word-count', 50);
		                    // Claims removed from UI.
		                    $('#pcg-cert-signature-label').val(data.certificate_signature_label || '');

	                    certificateLogoAttachmentId = Number(data.certificate_logo_attachment_id || 0) || 0;
	                    if (data.certificate_logo_url) {
	                        $('#pcg-certificate-logo-preview img').attr('src', data.certificate_logo_url);
	                        $('#pcg-certificate-logo-preview').show();
	                    } else {
	                        $('#pcg-certificate-logo-preview').hide();
	                    }

	                    certificateSignatureAttachmentId = Number(data.certificate_signature_attachment_id || 0) || 0;
	                    if (data.certificate_signature_url) {
	                        $('#pcg-certificate-signature-preview img').attr('src', data.certificate_signature_url);
	                        $('#pcg-certificate-signature-preview').show();
	                    } else {
	                        $('#pcg-certificate-signature-preview').hide();
	                    }

	                    updateCertificatePreview();

                    if (data.permalink) {
                        currentCoursePermalink = data.permalink;
                        $previewBtn.show();
                    } else {
                        $previewBtn.hide();
                    }

                    $courseLabel.text(data.title).show();

                    $('#pcg-course-progression').prop('checked', data.progression === 'on');

                    $('#pcg-lessons-list').empty();
                    if (data.content.length > 0) {
                        $('.pcg-empty-lessons-state').hide();
                        data.content.forEach(item => {
                            addContentItem(item.type, item);
                        });
                    } else {
                        $('.pcg-empty-lessons-state').show();
                    }

                    // Populate Teachers
                    populateTeachersList($('#pcg-teachers-list'), data.teachers || [], {
                        id: Number(data.author_id || 0),
                        name: data.author_name || '',
                        avatar: data.author_avatar || ''
                    });

                    plLearningMeta.setSelection('course', data.category_ids || [], data.tags || []);

                    // Reset Tabs to "CURSO"
                    $('.pcg-segment').removeClass('active');
                    $('.pcg-segment[data-value="curso"]').addClass('active');
                    $('.pcg-mode-content').hide();
                    $('#pcg-mode-curso').show();

                    hideEditLoadingState();
                } else {
                    $('#pcg-course-form-section').hide();
                    $('#pcg-my-courses-section').show();
                    alert(t('errorGettingCourseData') + (response.data ? response.data.message : t('unknownError')));
                }
            },
            error: function (jqXHR, textStatus) {
                if (textStatus === 'abort') {
                    return;
                }
                $('#pcg-course-form-section').hide();
                $('#pcg-my-courses-section').show();
                alert(t('errorLoadingCourseGeneric'));
            },
            complete: function () {
                $editBtn.prop('disabled', false);
            }
        });
    });

    // Delete Course
    $(document).on('click', '.pcg-btn-delete-course', function () {
        const $card = $(this).closest('.pcg-course-card');
        const courseId = $card.data('id');
        if (!confirm(t('confirmDeleteCourse'))) return;
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_delete_course',
                nonce: pcgCreatorData.nonce,
                course_id: courseId
            },
            success: function (response) {
                if (response.success) {
                    // If we are currently editing THIS course, reset form
                    if (currentCourseId === courseId) {
                        currentCourseId = 0;
                        $('#pcg-current-course-id').val(0);
                        $('#pcg-course-form-section').hide();
                        $('#pcg-my-courses-section').fadeIn();
                    }

                    $card.fadeOut(400, function () {
                        $(this).remove();
                        if (getActiveGrid().children().length === 0) refreshActiveList();
                    });
                }
            }
        });
    });

    function indexPendingApprovals(items) {
        pendingApprovalsIndex = { group: {}, program: {} };
        (Array.isArray(items) ? items : []).forEach(item => {
            const type = String(item.container_type || '');
            const id = Number(item.container_id || 0);
            const snapshotId = Number(item.snapshot_id || 0);
            if (!type || !id || !snapshotId) return;
            if (!pendingApprovalsIndex[type]) pendingApprovalsIndex[type] = {};
            pendingApprovalsIndex[type][id] = {
                snapshot_id: snapshotId,
                profit_percentage: item.profit_percentage,
                created_by_name: item.created_by_name
            };
        });
    }

    function fetchPendingApprovals() {
        return $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_get_my_pending_approvals',
                nonce: pcgCreatorData.nonce
            },
            success: function (response) {
                if (response && response.success) {
                    const items = response.data || [];
                    indexPendingApprovals(items);
                } else {
                    indexPendingApprovals([]);
                }
            }
        });
    }

    // Approve/Reject actions directly from cards (specializations/programs).
    $(document).on('click', '.pcg-card-approval-approve', function () {
        const snapshotId = Number($(this).closest('.pcg-card-approval-actions').data('snapshot-id')) || 0;
        if (!snapshotId) return;
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_approve_inclusion_snapshot',
                nonce: pcgCreatorData.nonce,
                snapshot_id: snapshotId
            },
            success: function (response) {
                if (response && response.success) {
                    fetchPendingApprovals().always(function () {
                        refreshActiveList();
                    });
                } else {
                    alert(t('approvalActionFailed'));
                }
            }
        });
    });

    $(document).on('click', '.pcg-card-approval-reject', function () {
        const snapshotId = Number($(this).closest('.pcg-card-approval-actions').data('snapshot-id')) || 0;
        if (!snapshotId) return;
        if (!confirm(t('confirmReject'))) return;
        $.ajax({
            url: pcgCreatorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pcg_reject_inclusion_snapshot',
                nonce: pcgCreatorData.nonce,
                snapshot_id: snapshotId
            },
            success: function (response) {
                if (response && response.success) {
                    fetchPendingApprovals().always(function () {
                        refreshActiveList();
                    });
                } else {
                    alert(t('approvalActionFailed'));
                }
            }
        });
    });

    fetchPendingApprovals().always(function () {
        refreshActiveList();
    });
});
