<?php
if (!defined('ABSPATH')) exit;
?>

<script>
    (function() {
        // --- Data Layer ---
        const portfolioSettings = <?php echo json_encode($portfolio_settings); ?>;
	        const serverView = <?php echo json_encode($server_view); ?>;
	        const profileContainerClass = <?php echo json_encode($pl_profile_content_container_class); ?>;
	        const profileUrls = {
	            friends: <?php echo json_encode($friends_url); ?>,
	            notifications: <?php echo json_encode($notifications_url); ?>,
	        };
        const userdata = {
            display_name: '<?php echo esc_js($display_name); ?>',
            description: '<?php echo esc_js(get_user_meta($user_id, 'description', true)); ?>'
        };

	        const allMenuItems = [
	            { id: 'main', label: 'Inicio', icon: 'home' },
	            { id: 'courses', label: 'Mis Cursos', icon: 'graduation-cap' },
	            { id: 'writings', label: 'Escritos', icon: 'book-open' },
	            { id: 'specializations', label: 'Especializaciones', icon: 'award' },
	            { id: 'thoughts', label: 'Feed de Pensamientos', icon: 'message-circle' },
	            { id: 'plans', label: 'Planes', icon: 'list-checks' },
	            { id: 'book', label: 'Libros', icon: 'book' },
	            <?php if ($is_own_profile) : ?>
	            { id: 'connections', label: <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Conexiones' : 'Connections'); ?>, materialIcon: 'diversity_3' },
	            { id: 'friends', label: 'Friends', icon: 'users' },
	            { id: 'notifications', label: 'Notifications', icon: 'bell' }
	            <?php endif; ?>
	        ];

	        const allowedTabs = <?php echo json_encode($pl_allowed_tabs); ?>;

	        // Filter menu items based on relationship policy + privacy
	        const menuItems = allMenuItems.filter(item => {
	            if (Array.isArray(allowedTabs) && allowedTabs.length > 0 && !allowedTabs.includes(item.id)) {
	                return false;
	            }
	            if (portfolioSettings[item.id] && portfolioSettings[item.id].is_private == 1) {
	                return false;
	            }
	            return true;
	        });

	        const courses = <?php echo json_encode($user_courses); ?>;
	        const articles = <?php echo json_encode($user_writings); ?>;
	        const specializations = <?php echo json_encode($user_specs); ?>;

	        const thoughts = <?php echo json_encode($book_thoughts); ?>;
	        const connectionsData = <?php echo json_encode($pl_connections_data); ?>;
	        const ppsCancelUrl = <?php echo json_encode(rest_url('politeia/v1/subscriptions/cancel')); ?>;
	        const ppsRestNonce = <?php echo json_encode(wp_create_nonce('wp_rest')); ?>;
	        const respondNonce = <?php echo json_encode($pl_relationship_respond_nonce); ?>;
	        const blockNonce = <?php echo json_encode($pl_relationship_block_nonce); ?>;
	        const coursePartnerInviteNonce = <?php echo json_encode($pl_course_partner_invite_nonce); ?>;
	        const adminPostUrl = <?php echo json_encode(admin_url('admin-post.php')); ?>;

	        const books = [
	            { id: 1, title: 'The Architect\'s Mind', price: '$24.00', img: 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=400&q=80' },
	            { id: 2, title: 'Visual Poetry', price: '$32.50', img: 'https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=400&q=80' }
        ];

        // --- Core Logic ---
        let currentTab = <?php echo json_encode($initial_tab); ?>;
        let currentConnectionsView = <?php echo json_encode($initial_connections_view); ?>;

	        window.toggleSidebar = function() {
	            document.getElementById('politeia-profile-sidebar').classList.toggle('open');
	        };

        window.showToast = function(message) {
            const t = document.getElementById('pcg-toast');
            if (t) {
                t.innerText = message;
                t.classList.add('show');
                setTimeout(() => t.classList.remove('show'), 3000);
            }
        };

        window.toggleCommentForm = function(id) {
            const form = document.getElementById(`comment-form-${id}`);
            if (form) {
                form.classList.toggle('hidden');
                if (!form.classList.contains('hidden')) {
                    form.querySelector('textarea').focus();
                }
            }
        };

        window.publishComment = function(id) {
            const form = document.getElementById(`comment-form-${id}`);
            const textarea = form.querySelector('textarea');
            if (textarea.value.trim() === '') return;

            showToast('Comment published for moderation');
            textarea.value = '';
            form.classList.add('hidden');
        };

        window.switchTab = function(tabId) {
            currentTab = tabId;
            const label = document.getElementById('pcg-current-tab-label');
            const item = menuItems.find(m => m.id === tabId);
            if (label && item) label.innerText = item.label;
            renderSidebar();
            renderContent();
            if (window.innerWidth < 800) document.getElementById('politeia-profile-sidebar').classList.remove('open');
        };

        window.switchConnectionsView = function(viewId) {
            currentConnectionsView = viewId;
            if (currentTab === 'connections') {
                renderContent();
            }
        };

	        function renderSidebar() {
	            const nav = document.getElementById('pcg-nav-menu');
	            if (!nav) return;
	            nav.innerHTML = menuItems.map(item => `
	                <button onclick="switchTab('${item.id}')" 
	                        class="pcg-nav-item ${currentTab === item.id ? 'active' : ''} gap-4 px-6 py-3 text-neutral-500 hover:text-black hover:bg-neutral-100 group">
	                    ${item.materialIcon ? `<span class="material-symbols-outlined" style="font-size:18px;line-height:1;">${item.materialIcon}</span>` : `<i data-lucide="${item.icon}" size="18"></i>`}
	                    <span class="font-semibold text-sm">${item.label}</span>
	                </button>
	            `).join('');
	            if (window.lucide) lucide.createIcons();
	        }

        function renderContent() {
            const container = document.getElementById('pcg-content-area');
            if (!container) return;

            // Set dynamic background for thoughts feed
            container.style.backgroundColor = (currentTab === 'thoughts') ? '#f1f1f1' : 'white';

	            container.innerHTML = '';
	            
	            const wrapper = document.createElement('div');
	            wrapper.className = `${profileContainerClass} card-transition`;

	            switch (currentTab) {
		                case 'connections': {
		                    const title = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Conexiones' : 'Connections'); ?>;
		                    const summary = connectionsData && connectionsData.summary ? connectionsData.summary : {};
		                    const pendingReceivedCount = Number(summary.pending_received || 0);
		                    const pendingSentCount = Number(summary.pending_sent || 0);
		                    const activeProjectsCount = Number(summary.active_projects || 0);
		                    const membershipsCount = Number(summary.memberships || 0);
		                    const historyCount = Number(summary.history || 0);
		                    const emptyText = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'No hay conexiones registradas todavía.' : 'No connections have been recorded yet.'); ?>;
		                    const pendingReceived = Array.isArray(connectionsData.pending_received) ? connectionsData.pending_received : [];
		                    const pendingSent = Array.isArray(connectionsData.pending_sent) ? connectionsData.pending_sent : [];
		                    const activeProjects = Array.isArray(connectionsData.active_projects) ? connectionsData.active_projects : [];
		                    const memberships = Array.isArray(connectionsData.memberships) ? connectionsData.memberships : [];
		                    const history = Array.isArray(connectionsData.history) ? connectionsData.history : [];

		                    const pendingItems = pendingReceived.concat(pendingSent);
		                    const projectItems = activeProjects.filter(item => String((item && item.group) || '') === 'project');
		                    const communityItems = activeProjects.filter(item => String((item && item.group) || '') === 'community');
		                    const connectionViews = [
		                        { id: 'pending', label: <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Pendientes' : 'Pending'); ?>, count: pendingReceivedCount + pendingSentCount },
		                        { id: 'projects', label: <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Proyectos' : 'Projects'); ?>, count: projectItems.length },
		                        { id: 'community', label: <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Comunidad' : 'Community'); ?>, count: communityItems.length },
		                        { id: 'memberships', label: <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Membresías' : 'Memberships'); ?>, count: membershipsCount },
		                        { id: 'history', label: <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Historial' : 'History'); ?>, count: historyCount },
		                    ];
		                    const viewIds = connectionViews.map(view => view.id);
		                    if (!viewIds.includes(currentConnectionsView)) {
		                        currentConnectionsView = 'pending';
		                    }

		                    const noData = (pendingItems.length + projectItems.length + communityItems.length + memberships.length + history.length) === 0;
		                    if (noData) {
		                        wrapper.innerHTML = `
		                            <div class="p-8 bg-white border border-neutral-200 rounded-[6px] shadow-sm">
		                                <h3 class="text-xl font-semibold text-neutral-900 mb-2">${title}</h3>
		                                <p class="text-sm text-neutral-600">${emptyText}</p>
		                            </div>
		                        `;
		                        break;
		                    }

		                    const stateBadge = (state, label) => {
		                        const normalized = String(state || '').toLowerCase();
		                        let classes = 'border-neutral-200 text-neutral-700 bg-white';
		                        if (normalized === 'active') {
		                            classes = 'border-black bg-black text-white';
		                        } else if (normalized === 'pending') {
		                            classes = 'border-neutral-300 text-neutral-800 bg-neutral-50';
		                        } else if (normalized === 'revoked' || normalized === 'rejected' || normalized === 'expired') {
		                            classes = 'border-neutral-200 text-neutral-400 bg-neutral-100';
		                        }
		                        return `<span class="inline-flex items-center px-2.5 py-1 text-[10px] uppercase tracking-[0.08em] font-semibold border rounded-[4px] ${classes}">${String(label || state || '')}</span>`;
		                    };

		                    const itemTitle = (item) => {
		                        const objectTitle = item && item.object && item.object.title ? String(item.object.title) : '';
		                        if (objectTitle) return objectTitle;
		                        return String(item && item.title ? item.title : '');
		                    };

		                    const itemSubtitle = (item) => {
		                        const objectType = String(item && item.object && item.object.type ? item.object.type : item && item.object_type ? item.object_type : '');
		                        if (objectType === 'course') return <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Curso' : 'Course'); ?>;
		                        if (objectType === 'reading_plan') return <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Plan de lectura' : 'Reading plan'); ?>;
		                        if (String(item && item.kind || '') === 'subscription') return <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Membresía pagada' : 'Paid membership'); ?>;
		                        if (String(item && item.kind || '') === 'relationship') {
		                            const relType = String(item.rel_type || '');
		                            if (relType === 'friend') return <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Amistad' : 'Friendship'); ?>;
		                            return relType || <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Relación' : 'Relationship'); ?>;
		                        }
		                        return String(item && item.subtitle ? item.subtitle : '');
		                    };

		                    const itemDate = (item) => {
		                        const raw = String(item && (item.created_at || item.expires_at) ? (item.created_at || item.expires_at) : '');
		                        return raw ? `<span class="text-xs text-neutral-400">${raw}</span>` : '';
		                    };

		                    const itemAvatar = (user) => {
		                        const avatar = user && user.avatar_url ? String(user.avatar_url) : '';
		                        const name = user && user.name ? String(user.name) : 'User';
		                        return avatar
		                            ? `<img src="${avatar}" alt="" class="w-10 h-10 rounded-full object-cover border border-neutral-200 bg-white" />`
		                            : `<div class="w-10 h-10 rounded-full bg-neutral-200 border border-neutral-200 flex items-center justify-center text-[10px] font-semibold text-neutral-500 uppercase">${name.split(' ').map(part => part.charAt(0)).join('').slice(0, 2)}</div>`;
		                    };

		                    const itemActions = (item) => {
		                        const acceptLabel = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Aceptar' : 'Accept'); ?>;
		                        const rejectLabel = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Rechazar' : 'Reject'); ?>;
		                        const blockLabel = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Bloquear' : 'Block'); ?>;
		                        const openLabel = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Ver' : 'View'); ?>;
		                        const itemKind = String(item && item.kind ? item.kind : '');
		                        const itemState = String(item && item.status ? item.status : '');
		                        const linkUrl = item && item.object && item.object.url ? String(item.object.url) : '';
		                        const cancelNowLabel = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Cancelar ahora' : 'Cancel now'); ?>;
		                        const cancelEndLabel = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Cancelar al fin del período' : 'Cancel at period end'); ?>;

		                        if (itemState === 'pending' && itemKind === 'relationship') {
		                            const direction = String(item.direction || '');
		                            if (direction !== 'received') {
		                                return `
		                                    <span class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] border border-neutral-200 text-neutral-500 bg-neutral-50">${<?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Enviada' : 'Sent'); ?>}</span>
		                                `;
		                            }
		                            const reqId = Number(item.id) || 0;
		                            const fromUserId = Number(item.user && item.user.user_id ? item.user.user_id : 0);
		                            const nonceInput = respondNonce ? `<input type="hidden" name="_wpnonce" value="${respondNonce}">` : '';
		                            const blockNonceInput = blockNonce ? `<input type="hidden" name="_wpnonce" value="${blockNonce}">` : '';
		                            const redirectTo = (() => {
		                                try {
		                                    const url = new URL(window.location.href);
		                                    url.searchParams.set('tab', 'connections');
		                                    url.searchParams.set('connections_view', 'pending');
		                                    return url.toString();
		                                } catch (e) {
		                                    return window.location.href;
		                                }
		                            })();
		                            const redirectInput = `<input type="hidden" name="redirect_to" value="${redirectTo.replace(/\"/g, '&quot;')}">`;
		                            return `
		                                <div class="flex flex-wrap items-center gap-2">
		                                    <form method="post" action="${adminPostUrl}" class="m-0">
		                                        ${nonceInput}
		                                        ${redirectInput}
		                                        <input type="hidden" name="action" value="pl_relationship_respond">
		                                        <input type="hidden" name="request_id" value="${reqId}">
		                                        <input type="hidden" name="decision" value="accept">
		                                        <button type="submit" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] bg-black text-white hover:bg-neutral-800">${acceptLabel}</button>
		                                    </form>
		                                    <form method="post" action="${adminPostUrl}" class="m-0">
		                                        ${nonceInput}
		                                        ${redirectInput}
		                                        <input type="hidden" name="action" value="pl_relationship_respond">
		                                        <input type="hidden" name="request_id" value="${reqId}">
		                                        <input type="hidden" name="decision" value="reject">
		                                        <button type="submit" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] border border-neutral-200 text-neutral-700 hover:bg-white">${rejectLabel}</button>
		                                    </form>
		                                    <form method="post" action="${adminPostUrl}" class="m-0">
		                                        ${blockNonceInput}
		                                        ${redirectInput}
		                                        <input type="hidden" name="action" value="pl_relationship_block">
		                                        <input type="hidden" name="blocked_user_id" value="${fromUserId}">
		                                        <button type="submit" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] border border-red-200 text-red-600 hover:bg-white">${blockLabel}</button>
		                                    </form>
		                                </div>
		                            `;
		                        }

		                        if (itemKind === 'subscription' && itemState === 'active') {
		                            const direction = String(item.direction || '');
		                            if (direction !== 'outgoing') {
		                                return '';
		                            }
		                            const gateway = String(item.gateway || 'mercadopago');
		                            const mpId = String(item.mp_preapproval_id || '');
		                            const flowId = String(item.flow_subscription_id || '');
		                            const cancelScheduled = !!item.cancel_at_period_end;
		                            const subId = Number(item.id) || 0;
		                            const payloadAttr = gateway === 'flow'
		                                ? `data-pps-flow-subscription-id="${flowId.replace(/\"/g, '&quot;')}"`
		                                : `data-pps-mp-preapproval-id="${mpId.replace(/\"/g, '&quot;')}"`;

		                            const scheduledTag = cancelScheduled
		                                ? `<span class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] border border-neutral-200 text-neutral-500 bg-neutral-50">${<?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Cancelación programada' : 'Cancellation scheduled'); ?>}</span>`
		                                : '';

		                            return `
		                                <div class="flex flex-wrap items-center gap-2">
		                                    ${scheduledTag}
		                                    <button type="button"
		                                        class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] border border-neutral-200 text-neutral-700 hover:bg-white"
		                                        data-pps-cancel
		                                        data-pps-subscription-id="${subId}"
		                                        data-pps-gateway="${gateway}"
		                                        data-pps-at-period-end="1"
		                                        ${payloadAttr}
		                                    >${cancelEndLabel}</button>
		                                    <button type="button"
		                                        class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] bg-black text-white hover:bg-neutral-800"
		                                        data-pps-cancel
		                                        data-pps-subscription-id="${subId}"
		                                        data-pps-gateway="${gateway}"
		                                        data-pps-at-period-end="0"
		                                        ${payloadAttr}
		                                    >${cancelNowLabel}</button>
		                                </div>
		                            `;
		                        }

		                        if (itemState === 'pending' && (itemKind === 'partnership' || itemKind === 'reading_plan_invite')) {
		                            const direction = String(item.direction || '');
		                            if (itemKind === 'reading_plan_invite') {
		                                return `
		                                    <a href="${linkUrl || '#'}" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] bg-black text-white hover:bg-neutral-800 no-underline">${<?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Ver plan' : 'View plan'); ?>}</a>
		                                `;
		                            }
		                            if (direction !== 'received') {
		                                return `
		                                    <span class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] border border-neutral-200 text-neutral-500 bg-neutral-50">${<?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Enviada' : 'Sent'); ?>}</span>
		                                `;
		                            }
		                            const inviteId = Number(item.id) || 0;
		                            const source = String(item.source || 'partnerships');
		                            const nonceInput = coursePartnerInviteNonce ? `<input type="hidden" name="_wpnonce" value="${coursePartnerInviteNonce}">` : '';
		                            const redirectInput = `<input type="hidden" name="redirect_to" value="${window.location.href.replace(/\"/g, '&quot;')}">`;
		                            return `
		                                <div class="flex flex-wrap items-center gap-2">
		                                    <form method="post" action="${adminPostUrl}" class="m-0">
		                                        ${nonceInput}
		                                        ${redirectInput}
		                                        <input type="hidden" name="action" value="pl_course_partner_invite_respond">
		                                        <input type="hidden" name="invite_id" value="${inviteId}">
		                                        <input type="hidden" name="source" value="${source}">
		                                        <input type="hidden" name="decision" value="accept">
		                                        <button type="submit" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] bg-black text-white hover:bg-neutral-800">${acceptLabel}</button>
		                                    </form>
		                                    <form method="post" action="${adminPostUrl}" class="m-0">
		                                        ${nonceInput}
		                                        ${redirectInput}
		                                        <input type="hidden" name="action" value="pl_course_partner_invite_respond">
		                                        <input type="hidden" name="invite_id" value="${inviteId}">
		                                        <input type="hidden" name="source" value="${source}">
		                                        <input type="hidden" name="decision" value="reject">
		                                        <button type="submit" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] border border-neutral-200 text-neutral-700 hover:bg-white">${rejectLabel}</button>
		                                    </form>
		                                </div>
		                            `;
		                        }

		                        if (linkUrl) {
		                            return `<a href="${linkUrl}" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] bg-black text-white hover:bg-neutral-800 no-underline">${openLabel}</a>`;
		                        }

		                        return '';
		                    };

		                    const renderCard = (item) => {
		                        const user = item && item.user ? item.user : {};
		                        const itemState = String(item && item.status ? item.status : '');
		                        const title = itemTitle(item);
		                        const subtitle = itemSubtitle(item);
		                        const dateMarkup = itemDate(item);
		                        const roleLabel = String(item && item.role ? item.role : '');
		                        const stateLabel = String(item && item.state_label ? item.state_label : itemState);
		                        return `
		                            <div class="p-4 sm:p-5 border border-neutral-200 rounded-[6px] bg-white">
		                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
		                                    <div class="min-w-0 flex items-start gap-3">
		                                        ${itemAvatar(user)}
		                                        <div class="min-w-0">
		                                            <div class="flex flex-wrap items-center gap-2">
		                                                <h4 class="text-sm sm:text-base font-semibold text-neutral-900 truncate">${title || <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Sin título' : 'Untitled'); ?>}</h4>
		                                                ${stateBadge(itemState, stateLabel)}
		                                            </div>
		                                            <p class="mt-1 text-xs sm:text-sm text-neutral-500">${subtitle || ''}${roleLabel ? ` • ${roleLabel}` : ''}</p>
		                                            <p class="mt-1 text-[11px] uppercase tracking-widest text-neutral-400">${String(user && user.name ? user.name : '')}${dateMarkup ? ` • ${String(item.created_at || item.expires_at || '')}` : ''}</p>
		                                        </div>
		                                    </div>
		                                    <div class="flex flex-wrap items-center gap-2 shrink-0">
		                                        ${itemActions(item)}
		                                    </div>
		                                </div>
		                            </div>
		                        `;
		                    };

		                    const renderItemList = (items, emptyMsg) => {
		                        const list = Array.isArray(items) ? items : [];
		                        if (list.length === 0) {
		                            return `
		                                <div class="p-6 border border-dashed border-neutral-200 rounded-[6px] text-sm text-neutral-500 bg-neutral-50">
		                                    ${emptyMsg}
		                                </div>
		                            `;
		                        }
		                        return `<div class="space-y-3">${list.map(renderCard).join('')}</div>`;
		                    };

		                    const connectionsViewButtons = connectionViews.map(view => `
		                        <button type="button" onclick="switchConnectionsView('${view.id}')"
		                            class="flex-none inline-flex items-center gap-2 px-4 py-2.5 text-xs sm:text-[11px] uppercase tracking-[0.18em] font-semibold border rounded-[6px] ${currentConnectionsView === view.id ? 'bg-black text-white border-black' : 'bg-white text-neutral-500 border-neutral-200 hover:border-black hover:text-black'}">
		                            <span>${view.label}</span>
		                            <span class="inline-flex min-w-6 justify-center rounded-full bg-white/15 px-1.5 py-0.5 text-[10px] leading-none ${currentConnectionsView === view.id ? 'text-white border border-white/20' : 'text-neutral-500 border border-neutral-200'}">${Number(view.count || 0)}</span>
		                        </button>
		                    `).join('');

		                    const pendingEmpty = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'No tienes conexiones pendientes.' : 'No pending connections.'); ?>;
		                    const projectEmpty = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'No tienes proyectos conectados todavía.' : 'You have no connected projects yet.'); ?>;
		                    const communityEmpty = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'No hay conexiones de comunidad activas.' : 'No active community connections.'); ?>;
		                    const membershipsEmpty = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'No tienes membresías activas.' : 'No active memberships.'); ?>;
		                    const historyEmpty = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Todavía no hay historial de conexiones.' : 'There is no connection history yet.'); ?>;

		                    let viewMarkup = '';
		                    if (currentConnectionsView === 'pending') {
		                        const received = pendingReceived.filter(item => String(item.status || '') === 'pending');
		                        const sent = pendingSent.filter(item => String(item.status || '') === 'pending');
		                        viewMarkup = `
		                            <div class="space-y-6">
		                                <div>
		                                    <h3 class="text-lg font-semibold text-neutral-900 mb-3">${<?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Recibidas' : 'Received'); ?>}</h3>
		                                    ${renderItemList(received, pendingEmpty)}
		                                </div>
		                                <div>
		                                    <h3 class="text-lg font-semibold text-neutral-900 mb-3">${<?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Enviadas' : 'Sent'); ?>}</h3>
		                                    ${renderItemList(sent, pendingEmpty)}
		                                </div>
		                            </div>
		                        `;
		                    } else if (currentConnectionsView === 'projects') {
		                        viewMarkup = renderItemList(projectItems, projectEmpty);
		                    } else if (currentConnectionsView === 'community') {
		                        viewMarkup = renderItemList(communityItems, communityEmpty);
		                    } else if (currentConnectionsView === 'memberships') {
		                        viewMarkup = renderItemList(memberships, membershipsEmpty);
		                    } else {
		                        viewMarkup = renderItemList(history, historyEmpty);
		                    }

		                    wrapper.innerHTML = `
		                        <div class="space-y-6">
		                            <div class="p-5 sm:p-6 bg-white border border-neutral-200 rounded-[6px] shadow-sm">
		                                <div class="flex flex-col gap-5">
		                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
		                                        <div class="rounded-[6px] border border-neutral-200 bg-neutral-50 p-4">
		                                            <p class="text-[10px] uppercase tracking-widest text-neutral-500"><?php echo esc_js((strpos(get_locale(), 'es') !== false) ? 'Pendientes' : 'Pending'); ?></p>
		                                            <p class="mt-2 text-2xl font-semibold text-neutral-900">${pendingReceivedCount + pendingSentCount}</p>
		                                        </div>
		                                        <div class="rounded-[6px] border border-neutral-200 bg-neutral-50 p-4">
		                                            <p class="text-[10px] uppercase tracking-widest text-neutral-500"><?php echo esc_js((strpos(get_locale(), 'es') !== false) ? 'Activas' : 'Active'); ?></p>
		                                            <p class="mt-2 text-2xl font-semibold text-neutral-900">${activeProjectsCount}</p>
		                                        </div>
		                                        <div class="rounded-[6px] border border-neutral-200 bg-neutral-50 p-4">
		                                            <p class="text-[10px] uppercase tracking-widest text-neutral-500"><?php echo esc_js((strpos(get_locale(), 'es') !== false) ? 'Comunidad' : 'Community'); ?></p>
		                                            <p class="mt-2 text-2xl font-semibold text-neutral-900">${communityItems.length}</p>
		                                        </div>
		                                        <div class="rounded-[6px] border border-neutral-200 bg-neutral-50 p-4">
		                                            <p class="text-[10px] uppercase tracking-widest text-neutral-500"><?php echo esc_js((strpos(get_locale(), 'es') !== false) ? 'Membresías' : 'Memberships'); ?></p>
		                                            <p class="mt-2 text-2xl font-semibold text-neutral-900">${membershipsCount}</p>
		                                        </div>
		                                        <div class="rounded-[6px] border border-neutral-200 bg-neutral-50 p-4">
		                                            <p class="text-[10px] uppercase tracking-widest text-neutral-500"><?php echo esc_js((strpos(get_locale(), 'es') !== false) ? 'Historial' : 'History'); ?></p>
		                                            <p class="mt-2 text-2xl font-semibold text-neutral-900">${historyCount}</p>
		                                        </div>
		                                    </div>

		                                    <div class="flex gap-2 overflow-x-auto pb-1 no-scrollbar">
		                                        ${connectionsViewButtons}
		                                    </div>
		                                </div>
		                            </div>

		                            <div class="space-y-4">
		                                <div class="flex items-center justify-between gap-3">
		                                    <div>
		                                        <h3 class="text-xl font-semibold text-neutral-900">${title}</h3>
		                                        <p class="text-sm text-neutral-500 mt-1">
		                                            ${currentConnectionsView === 'pending' ? <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Revisa lo que está esperando tu respuesta.' : 'Review what is waiting for your response.'); ?> : <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Explora el estado de tus conexiones.' : 'Explore the status of your connections.'); ?>}
		                                        </p>
		                                    </div>
		                                </div>
		                                ${viewMarkup}
		                            </div>
		                        </div>
		                    `;

		                    break;
		                }
	                case 'friends':
	                    wrapper.innerHTML = `
	                        <div class="py-20 text-center p-8 bg-neutral-50 rounded-[6px] border border-neutral-200">
	                            <div class="w-16 h-16 bg-neutral-100 rounded-full flex items-center justify-center mx-auto mb-6">
	                                <i data-lucide="users" class="text-neutral-400" size="32"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-neutral-900 mb-2">Friends</h3>
                            <p class="text-neutral-500 mb-8 max-w-md mx-auto">Open your Friends page to see all your connections.</p>
                            <a href="${profileUrls.friends || '#'}" class="inline-flex py-3 px-8 gold-gradient text-black font-semibold rounded-[6px] shadow-sm hover:shadow-lg transition-all no-underline text-sm uppercase tracking-widest">
                                View Friends
                            </a>
                        </div>
                    `;
                    break;

                case 'notifications':
                    wrapper.innerHTML = `
                        <div class="py-20 text-center p-8 bg-neutral-50 rounded-[6px] border border-neutral-200">
                            <div class="w-16 h-16 bg-neutral-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i data-lucide="bell" class="text-neutral-400" size="32"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-neutral-900 mb-2">Notifications</h3>
                            <p class="text-neutral-500 mb-8 max-w-md mx-auto">Open your Notifications page to see unread and read notifications.</p>
                            <a href="${profileUrls.notifications || '#'}" class="inline-flex py-3 px-8 gold-gradient text-black font-semibold rounded-[6px] shadow-sm hover:shadow-lg transition-all no-underline text-sm uppercase tracking-widest">
                                View Notifications
                            </a>
                        </div>
                    `;
                    break;

                case 'main':
                    wrapper.innerHTML = `
                        <div class="space-y-8">
                            <div class="p-8 rounded-[6px] bg-neutral-50 border border-neutral-200 shadow-sm">
                                <h1 class="text-3xl font-semibold text-neutral-900 mb-4">Perfil de <span class="gold-text">${userdata.display_name}</span></h1>
                                <p class="text-neutral-600 max-w-2xl leading-relaxed text-sm">${userdata.description || 'Welcome to this Curiosity Profile.'}</p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="p-6 bg-white border border-neutral-200 rounded-[6px] shadow-sm">
                                    <h3 class="text-lg font-semibold text-neutral-900 mb-2">Learning Progress</h3>
                                    <div class="w-full bg-neutral-200 h-2 rounded-full mt-4">
                                        <div class="gold-gradient h-full w-3/4 rounded-full"></div>
                                    </div>
                                    <p class="text-neutral-500 text-xs mt-3 font-semibold">Active in 3 courses</p>
                                </div>
                                <div class="p-6 bg-white border border-neutral-200 rounded-[6px] shadow-sm flex items-center justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold text-neutral-900">Notifications</h3>
                                        <p class="text-neutral-500 text-sm">2 unread messages</p>
                                    </div>
                                    <div class="p-3 bg-[#8A6B1E]/10 text-[#8A6B1E] rounded-[6px]">
                                        <i data-lucide="bell-ring" size="20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    break;

                case 'courses':
                    if (courses.length === 0) {
                        wrapper.innerHTML = `<div class="py-20 text-center text-neutral-400 font-semibold">No published courses yet.</div>`;
                    } else {
                        wrapper.className += ' grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6';
                        wrapper.innerHTML = courses.map(c => `
                            <a href="${c.link}" class="bg-white rounded-[6px] overflow-hidden border border-neutral-200 group hover:border-[#8A6B1E] hover:shadow-xl transition-all block text-inherit no-underline">
                                <div class="aspect-video overflow-hidden">
                                    <img src="${c.img}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                                </div>
                                <div class="p-5">
                                    <h3 class="text-neutral-900 font-semibold text-base mb-1">${c.title}</h3>
                                    <p class="text-[#8A6B1E] font-semibold text-sm">${c.price}</p>
                                </div>
                            </a>
                        `).join('');
                    }
                    break;

                case 'writings':
                    if (articles.length === 0) {
                        wrapper.innerHTML = `<div class="py-20 text-center text-neutral-400 font-semibold">No published writings yet.</div>`;
                    } else {
                        wrapper.className += ' grid grid-cols-1 md:grid-cols-2 gap-6';
                        wrapper.innerHTML = articles.map(a => `
                            <div class="flex bg-white rounded-[6px] overflow-hidden border border-neutral-200 hover:border-[#8A6B1E] hover:shadow-lg transition-all group">
                                <a href="${a.link}" class="w-1/3 shrink-0 block">
                                    <img src="${a.img}" class="w-full h-full object-cover">
                                </a>
                                <div class="p-5 flex flex-col justify-between">
                                    <div>
                                        <span class="text-[10px] font-semibold uppercase tracking-wider text-[#8A6B1E] mb-2 block">${a.category}</span>
                                        <h3 class="text-base font-semibold text-neutral-900 leading-tight">${a.title}</h3>
                                    </div>
                                    <a href="${a.link}" class="text-[#8A6B1E] text-xs font-semibold flex items-center gap-2 group-hover:gap-3 transition-all mt-4">
                                        Read Full Article <i data-lucide="chevron-right" size="12"></i>
                                    </a>
                                </div>
                            </div>
                        `).join('');
                    }
                    break;

                case 'specializations':
                    if (specializations.length === 0) {
                        wrapper.innerHTML = `<div class="py-20 text-center text-neutral-400 font-semibold">No specialized works yet.</div>`;
                    } else {
                        wrapper.className += ' grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6';
                        wrapper.innerHTML = specializations.map(s => `
                            <a href="${s.link}" class="bg-white rounded-[6px] overflow-hidden border border-neutral-200 group hover:border-[#8A6B1E] hover:shadow-xl transition-all block text-inherit no-underline">
                                <div class="aspect-video overflow-hidden">
                                    <img src="${s.img}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                                </div>
                                <div class="p-5">
                                    <h3 class="text-neutral-900 font-semibold text-base mb-1">${s.title}</h3>
                                    <p class="text-[#8A6B1E] font-semibold text-sm">Specialization</p>
                                </div>
                            </a>
                        `).join('');
                    }
                    break;

                case 'thoughts':
                    if (thoughts.length === 0) {
                        wrapper.innerHTML = `<div class="py-20 text-center text-neutral-400 font-semibold">No thoughts shared yet.</div>`;
                    } else {
                        wrapper.className += ' max-w-2xl mx-auto space-y-6';
                        wrapper.innerHTML = thoughts.map(t => `
                            <div class="flex flex-col">
                                <!-- Hybrid Executive Header Bar -->
                                <div class="hybrid-container">
                                    <!-- Profile Section -->
                                    <div class="flex-1 flex items-center px-6 gap-4">
                                        <div class="relative">
                                            <img src="${t.avatar}" class="w-14 h-14 rounded-full object-cover border-2 border-slate-50">
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-lg font-semibold text-slate-800 leading-none tracking-tight">${t.user}</span>
                                            <span class="accent-gradient text-xs font-bold mt-1 opacity-90">commented on...</span>
                                        </div>
                                    </div>

                                    <!-- Decorative Connector -->
                                    <div class="hidden md:flex items-center gap-1 opacity-10 mx-4">
                                        <div class="w-1 h-1 rounded-full bg-slate-600"></div>
                                        <div class="w-1 h-1 rounded-full bg-slate-600"></div>
                                        <div class="w-1 h-1 rounded-full bg-slate-600"></div>
                                    </div>

                                    <!-- Book Section -->
                                    <div class="hybrid-book-section">
                                        <div class="relative shrink-0">
                                            <img src="${t.cover}" class="h-16 w-11 object-cover rounded-[2px] border border-slate-100">
                                            <!-- SVG Bookmark Icon with Gradient Fill -->
                                            <svg class="hybrid-bookmark-icon w-3.5 h-3.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <defs>
                                                    <linearGradient id="goldGradient-${t.id}" x1="0%" y1="0%" x2="100%" y2="100%">
                                                        <stop offset="0%" style="stop-color:#8A6B1E;stop-opacity:1" />
                                                        <stop offset="50%" style="stop-color:#C79F32;stop-opacity:1" />
                                                        <stop offset="100%" style="stop-color:#E9D18A;stop-opacity:1" />
                                                    </linearGradient>
                                                </defs>
                                                <path fill="url(#goldGradient-${t.id})" d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                            </svg>
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="hybrid-book-title truncate">${t.book}</div>
                                            <div class="hybrid-book-author truncate">${t.author}</div>
                                            <div class="hybrid-catalog-tag accent-gradient">Published ${t.book_year}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Note Content Box -->
                                <div class="hybrid-content-box">
                                    <div class="hybrid-note-text">
                                        ${t.content}
                                    </div>
                                    
                                    <div class="mt-6 pt-6 border-t border-slate-100 flex items-center gap-6" style="font-family: 'Poppins', sans-serif;">
                                        <button onclick="toggleCommentForm(${t.id})" class="pcg-minimal-button flex items-center gap-2 text-xs font-semibold text-neutral-400 hover:text-[#8A6B1E] transition-colors" style="font-family: inherit;">
                                            <i data-lucide="message-square" size="14"></i> Comment
                                        </button>
                                        <span class="text-[10px] text-neutral-300 font-semibold uppercase tracking-widest ml-auto" style="font-family: inherit;">${t.time}</span>
                                    </div>

                                    <!-- Comment Form (Hidden by default) -->
                                    <div id="comment-form-${t.id}" class="hidden mt-4 pt-4 border-t border-slate-100">
                                        <textarea class="w-full p-3 border border-slate-200 rounded-[6px] text-sm focus:outline-none focus:border-[#8A6B1E] bg-white" rows="3" placeholder="Write a comment..."></textarea>
                                        <div class="flex justify-end mt-2">
                                            <button onclick="publishComment(${t.id})" class="py-2 px-6 bg-neutral-800 text-white rounded-[6px] text-sm font-semibold hover:bg-black active:scale-95 transition-all border-0 outline-none">Publish</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `).join('');
                    }
                    break;

                case 'book':
                    wrapper.className += ' grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8';
                    wrapper.innerHTML = books.map(b => `
                        <div class="bg-white p-5 rounded-[6px] border border-neutral-200 text-center shadow-sm">
                            <div class="aspect-[3/4] mb-4 shadow-lg overflow-hidden rounded-[6px]">
                                <img src="${b.img}" class="w-full h-full object-cover">
                            </div>
                            <h3 class="text-lg font-semibold text-neutral-900 mb-1">${b.title}</h3>
                            <p class="text-[#8A6B1E] font-semibold text-xl mb-4">${b.price}</p>
                            <button onclick="showToast('Added to cart')" class="w-full py-2 px-4 gold-gradient text-black font-semibold rounded-[6px] flex items-center justify-center gap-2 shadow-sm text-sm">
                                <i data-lucide="shopping-cart" size="16"></i> Add to Cart
                            </button>
                        </div>
                    `).join('');
                    break;
                
                case 'plans':
                    wrapper.innerHTML = `
                        <div class="py-20 text-center p-8 bg-neutral-50 rounded-[6px] border border-neutral-200">
                            <div class="w-16 h-16 bg-neutral-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i data-lucide="list-checks" class="text-neutral-400" size="32"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-neutral-900 mb-2">Planes de Lectura</h3>
                            <p class="text-neutral-500 mb-8 max-w-md mx-auto">Visualiza todos tus planes de formación de hábitos y lectura de libros.</p>
                            <a href="/members/<?php echo esc_js($userdata->user_nicename); ?>/my-plans-ver-2" class="inline-flex py-3 px-8 gold-gradient text-black font-semibold rounded-[6px] shadow-sm hover:shadow-lg transition-all no-underline text-sm uppercase tracking-widest">
                                Manage My Plans
                            </a>
                        </div>
                    `;
                    break;

                default:
                    wrapper.innerHTML = `<div class="py-20 text-center text-neutral-400 font-semibold">Section details coming soon...</div>`;
            }

            container.appendChild(wrapper);
            if (window.lucide) lucide.createIcons();
        }

	        function init() {
	            renderSidebar();
	            renderContent();
	            if (window.lucide) lucide.createIcons();
	        }

	        async function cancelMembership(btn) {
	            if (!btn) return;
	            if (!ppsCancelUrl || !ppsRestNonce) {
	                window.showToast && window.showToast('Cancelación no disponible.');
	                return;
	            }

	            const gateway = String(btn.getAttribute('data-pps-gateway') || '');
	            const atPeriodEnd = Number(btn.getAttribute('data-pps-at-period-end') || '0');
	            const mpPreapprovalId = String(btn.getAttribute('data-pps-mp-preapproval-id') || '');
	            const flowSubscriptionId = String(btn.getAttribute('data-pps-flow-subscription-id') || '');

	            const confirmMsg = atPeriodEnd === 1
	                ? <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? '¿Programar cancelación al final del período actual?' : 'Schedule cancellation at the end of the current period?'); ?>
	                : <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? '¿Cancelar la suscripción ahora?' : 'Cancel the subscription now?'); ?>;

	            if (!window.confirm(confirmMsg)) return;

	            btn.disabled = true;
	            btn.classList.add('opacity-60');

	            try {
	                const payload = {
	                    gateway: gateway || (flowSubscriptionId ? 'flow' : 'mercadopago'),
	                    at_period_end: atPeriodEnd,
	                };
	                if (payload.gateway === 'flow') {
	                    payload.flow_subscription_id = flowSubscriptionId;
	                } else {
	                    payload.mp_preapproval_id = mpPreapprovalId;
	                }

	                const res = await fetch(String(ppsCancelUrl), {
	                    method: 'POST',
	                    headers: {
	                        'Content-Type': 'application/json',
	                        'X-WP-Nonce': String(ppsRestNonce),
	                    },
	                    body: JSON.stringify(payload),
	                });
	                const data = await res.json().catch(() => null);
	                if (!res.ok || (data && data.error)) {
	                    const msg = data && (data.message || data.error) ? String(data.message || data.error) : 'No se pudo cancelar.';
	                    window.showToast && window.showToast(msg);
	                    btn.disabled = false;
	                    btn.classList.remove('opacity-60');
	                    return;
	                }

	                window.showToast && window.showToast(<?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Suscripción actualizada.' : 'Subscription updated.'); ?>);
	                window.location.reload();
	            } catch (e) {
	                window.showToast && window.showToast('No se pudo cancelar.');
	                btn.disabled = false;
	                btn.classList.remove('opacity-60');
	            }
	        }

        // Use DOMContentLoaded to ensure we run after standard WP init if needed
	        if (document.readyState === 'loading') {
	            document.addEventListener('DOMContentLoaded', init);
	        } else {
	            init();
	        }

	        document.addEventListener('click', function (e) {
	            const btn = e.target && e.target.closest ? e.target.closest('[data-pps-cancel]') : null;
	            if (!btn) return;
	            e.preventDefault();
	            cancelMembership(btn);
	        });
    })();
</script>
