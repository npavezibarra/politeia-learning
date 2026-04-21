/**
 * Shared Logic for Politeia Dashboard
 * Centralizes Taxonomies and Teacher/Author management.
 */

// Teacher search debounce timer
let teacherSearchTimeout = 0;

/**
 * Taxonomies Engine (Categories & Tags)
 */
window.plLearningMeta = (function () {
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
            return jQuery.Deferred().resolve().promise();
        }
        if (cache.loading) {
            return cache.loading;
        }

        cache.loading = jQuery.ajax({
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
        if (!leaf.parent) return { l1: leaf.id, l2: 0, l3: 0 };
        if (p2 && !p2.parent) return { l1: p2.id, l2: leaf.id, l3: 0 };
        if (p2 && p1) return { l1: p1.id, l2: p2.id, l3: leaf.id };
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
        const $l1 = jQuery(cfg.catL1);
        if (!$l1.length) return;
        const $l2 = jQuery(cfg.catL2);
        const $l3 = jQuery(cfg.catL3);
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
        const $chips = jQuery(cfg.tagChips);
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
        const $wrap = jQuery(cfg.tagSuggestions);
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
        return str.split(',').map(s => s.trim()).filter(Boolean);
    }

    function addTokens(entity, raw) {
        const tokens = parseTokens(raw);
        if (!tokens.length) return jQuery.Deferred().resolve().promise();
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

    function createTagAndAdd(entity, name) {
        return jQuery.ajax({
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

    function bindEntity(entity) {
        const cfg = dom[entity];
        if (!cfg) return;

        jQuery(document).on('change', `${cfg.catL1} input[type="radio"]`, function () {
            const id = Number(jQuery(this).val()) || 0;
            if (!id) return;
            setSingleCategory(entity, id);
            renderCategories(entity);
        });

        jQuery(document).on('change', `${cfg.catL2} input[type="radio"]`, function () {
            const id = Number(jQuery(this).val()) || 0;
            if (!id) return;
            setSingleCategory(entity, id);
            renderCategories(entity);
        });

        jQuery(document).on('change', `${cfg.catL3} input[type="radio"]`, function () {
            const id = Number(jQuery(this).val()) || 0;
            if (!id) return;
            setSingleCategory(entity, id);
            renderCategories(entity);
        });

        jQuery(document).on('input focus', cfg.tagInput, function () {
            const val = jQuery(this).val();
            showTagSuggestions(entity, val);
        });

        jQuery(document).on('keydown', cfg.tagInput, function (e) {
            if (e.key === 'Escape') {
                jQuery(this).val('');
                hideSuggestions(jQuery(cfg.tagSuggestions));
            }
            if (e.key === 'Enter' || e.key === ',') {
                const val = String(jQuery(this).val() || '');
                const tokens = parseTokens(val);
                if (!tokens.length) {
                    if (e.key === ',') e.preventDefault();
                    return;
                }
                e.preventDefault();
                addTokens(entity, val).then(() => {
                    jQuery(this).val('');
                    hideSuggestions(jQuery(cfg.tagSuggestions));
                });
                return;
            }
        });

        jQuery(document).on('input', cfg.tagInput, function () {
            const val = String(jQuery(this).val() || '');
            if (!val.includes(',')) return;
            addTokens(entity, val).then(() => {
                jQuery(this).val('');
                hideSuggestions(jQuery(cfg.tagSuggestions));
            });
        });

        jQuery(document).on('click', `${cfg.tagSuggestions} .pcg-meta-suggestion[data-tag-id]`, function () {
            const id = Number(jQuery(this).attr('data-tag-id')) || 0;
            if (!id) return;
            const tag = cache.tagsById.get(id);
            if (tag) addTag(entity, tag);
            jQuery(cfg.tagInput).val('').trigger('input');
            hideSuggestions(jQuery(cfg.tagSuggestions));
        });

        jQuery(document).on('click', `${cfg.tagSuggestions} .pcg-meta-suggestion--create`, function () {
            const raw = jQuery(this).attr('data-create-name') || '';
            const name = decodeURIComponent(raw);
            if (!name) return;
            createTagAndAdd(entity, name).always(() => {
                jQuery(cfg.tagInput).val('');
                hideSuggestions(jQuery(cfg.tagSuggestions));
            });
        });

        jQuery(document).on('click', `${cfg.tagChips} .pcg-meta-chip__remove`, function () {
            const id = Number(jQuery(this).closest('.pcg-meta-chip').attr('data-tag-id')) || 0;
            removeTag(entity, id);
        });
    }

    jQuery(document).on('click', function (e) {
        const inside = jQuery(e.target).closest('.pcg-meta-tags').length > 0;
        if (inside) return;
        Object.keys(dom).forEach(k => {
            hideSuggestions(jQuery(dom[k].tagSuggestions));
        });
    });

    ['course', 'group', 'programa'].forEach(bindEntity);

    return {
        ensureLoaded,
        render: (entity) => ensureLoaded().done(() => { renderCategories(entity); renderTags(entity); }),
        setSelection,
        reset,
        getPayload,
    };
})();

/**
 * Teacher/Author Helpers
 */

function normalizePercentInt(value) {
    const v = parseInt(String(value || 0).replace(/\D/g, ''), 10) || 0;
    return Math.min(100, Math.max(0, v));
}

function normalizeTeacherIdentity(rawName = '', rawEmail = '') {
    let name = String(rawName || '').trim();
    let email = String(rawEmail || '').trim();
    if (!name && email) name = email;
    if (name && !email && name.includes('@')) email = name;
    return { name, email };
}

function getCurrentUserTeacherSeed() {
    if (!window.pcgCreatorData || !window.pcgCreatorData.currentUser) return null;
    const u = window.pcgCreatorData.currentUser;
    return {
        id: Number(u.id) || 0,
        name: String(u.displayName || ''),
        avatar: String(u.avatar || '')
    };
}

function resetTeachersList($list) {
    if (!$list || !$list.length) return;
    $list.empty();
    $list.append(`<div class="pcg-empty-teachers-state pcg-empty-msg">${t('noCollaboratorsYet')}</div>`);
    $list.data('splitLocked', false);
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
        jQuery(this).find('.pcg-teacher-profit').val(intValue);
        jQuery(this).find('.pcg-teacher-share-badge').text(`${intValue}%`);
    });
}

function rebalanceMainAuthorRemainder($list, $changedItem = null) {
    if (!$list || !$list.length) return;
    const $items = $list.find('.pcg-teacher-item');
    if (!$items.length) return;
    let $main = $list.find('.pcg-teacher-item[data-main="true"]').first();
    if (!$main.length) $main = $items.first();
    const isChangedMain = $changedItem && $changedItem.length && $changedItem.is($main);
    const getVal = ($item) => normalizePercentInt($item.find('.pcg-teacher-profit').val());
    const $nonMain = $items.not($main);
    if (!$nonMain.length) {
        $main.find('.pcg-teacher-profit').val(100);
        $main.find('.pcg-teacher-share-badge').text('100%');
        return;
    }
    if (isChangedMain) {
        let sumOthers = 0;
        $nonMain.each(function () { sumOthers += getVal(jQuery(this)); });
        const mainVal = Math.max(0, 100 - sumOthers);
        $main.find('.pcg-teacher-profit').val(mainVal);
        $main.find('.pcg-teacher-share-badge').text(`${mainVal}%`);
        return;
    }
    if ($changedItem && $changedItem.length) {
        let otherOthersSum = 0;
        $nonMain.not($changedItem).each(function () { otherOthersSum += getVal(jQuery(this)); });
        const maxForChanged = Math.max(0, 100 - otherOthersSum);
        let changedVal = getVal($changedItem);
        if (changedVal > maxForChanged) {
            changedVal = maxForChanged;
            $changedItem.find('.pcg-teacher-profit').val(changedVal);
            $changedItem.find('.pcg-teacher-share-badge').text(`${changedVal}%`);
        }
    }
    let sumNonMain = 0;
    $nonMain.each(function () { sumNonMain += getVal(jQuery(this)); });
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
        const userId = jQuery(this).attr('data-user-id');
        if (!userId) return;
        teachers.push({
            user_id: userId,
            role_slug: jQuery(this).find('.pcg-teacher-role-slug').val(),
            profit_percentage: normalizePercentInt(jQuery(this).find('.pcg-teacher-profit').val()),
            role_description: jQuery(this).find('.pcg-teacher-description').val()
        });
    });
    return teachers;
}

function addTeacherItem(data = {}, $targetList = null) {
    const $list = ($targetList && $targetList.length) ? $targetList : jQuery('#pcg-teachers-list');
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
    const removeBtnHtml = isMainAuthor ? '' : `<button type="button" class="pcg-item-btn-remove pcg-teacher-remove" title="${t('delete')}"><span class="dashicons dashicons-trash"></span></button>`;
    const itemHtml = `
        <div class="pcg-content-item pcg-teacher-item" data-user-id="${userId}" ${isMainAuthor ? 'data-main="true"' : ''}>
            <div class="pcg-item-header">
                <div class="pcg-item-expand" title="${t('viewDetails')}"><span class="dashicons dashicons-arrow-right-alt2"></span></div>
                <div class="pcg-item-icon">${iconHtml}</div>
                <div class="pcg-item-input-wrapper">
                    <input type="text" class="pcg-item-input pcg-teacher-name-input" value="${hasSelectedUser ? '' : userName}" placeholder="${t('searchCollaborator')}" ${isMainAuthor ? 'readonly' : ''} autocomplete="off" style="${hasSelectedUser ? 'display:none;' : ''}">
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
    const $newItem = jQuery(itemHtml);
    $list.append($newItem);
}

// Teacher Global Event Handlers
jQuery(document).ready(function ($) {
    $(document).on('click', '.pcg-btn-add-teacher, #pcg-btn-add-teacher', function () {
        const targetSel = $(this).attr('data-target') || '#pcg-teachers-list';
        addTeacherItem({}, $(targetSel));
    });

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
                                if (!$list.data('splitLocked')) rebalanceTeachersEqual($list);
                                else rebalanceMainAuthorRemainder($list, $item);
                            });
                            $results.append($resItem);
                        });
                    } else $results.hide().empty();
                }
            });
        }, 300);
    });

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
});
