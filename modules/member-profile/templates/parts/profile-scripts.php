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
	            { id: 'requests', label: <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Solicitudes' : 'Requests'); ?>, materialIcon: 'diversity_3' },
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
	        const followRequests = <?php echo json_encode($pl_pending_follow_requests); ?>;
	        const coursePartnerInvites = <?php echo json_encode(array_values($pl_pending_course_partner_invites)); ?>;
	        const recentAcceptedPartnerInvite = <?php echo json_encode($pl_recent_course_partner_accept); ?>;
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
		                case 'requests': {
		                    const title = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Solicitudes de Follow' : 'Follow Requests'); ?>;
		                    const partnerTitle = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Invitaciones de Partner de Curso' : 'Course Partner Invitations'); ?>;
		                    const emptyText = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'No tienes solicitudes pendientes.' : 'No pending requests.'); ?>;
		                    const acceptLabel = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Aceptar' : 'Accept'); ?>;
		                    const rejectLabel = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Rechazar' : 'Reject'); ?>;
		                    const blockLabel = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Bloquear' : 'Block'); ?>;
		                    const partnerKindLabel = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'partner de curso' : 'course partner'); ?>;
		                    const goToCourseLabel = <?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Ir al curso' : 'Go to course'); ?>;

		                    const hasFollow = Array.isArray(followRequests) && followRequests.length > 0;
		                    const hasPartnerInvites = Array.isArray(coursePartnerInvites) && coursePartnerInvites.length > 0;
		                    if (!hasFollow && !hasPartnerInvites) {
		                        wrapper.innerHTML = `
		                            <div class="p-8 bg-white border border-neutral-200 rounded-[6px] shadow-sm">
		                                <h3 class="text-xl font-semibold text-neutral-900 mb-2">${<?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Solicitudes' : 'Requests'); ?>}</h3>
		                                <p class="text-sm text-neutral-600">${emptyText}</p>
		                            </div>
		                        `;
		                        break;
		                    }

		                    const sections = [];

		                    if (recentAcceptedPartnerInvite && recentAcceptedPartnerInvite.course_id) {
		                        const inv = recentAcceptedPartnerInvite;
		                        const courseTitle = String(inv.course_title || '');
		                        const acceptedAt = inv.accepted_at ? `<span class="text-xs text-neutral-400">${String(inv.accepted_at)}</span>` : '';
		                        const courseUrl = String(inv.course_url || '#');
		                        const me = inv.me || {};
		                        const other = inv.other || {};

		                        const dropdown = `
		                            <div class="pl-course-partner-invite-dropdown mt-4 border-t border-neutral-200 pt-4">
		                                <div class="space-y-4">
		                                    <div class="flex items-center gap-3">
		                                        ${other.avatar_url ? `<img src="${String(other.avatar_url)}" alt="" class="w-9 h-9 rounded-full object-cover border border-neutral-200 bg-white" />` : `<div class="w-9 h-9 rounded-full bg-neutral-200 border border-neutral-200"></div>`}
		                                        <div class="min-w-0 flex-1">
		                                            <div class="flex items-center justify-between gap-3">
		                                                <p class="text-sm font-semibold text-neutral-900 truncate">${String(other.name || 'User')}</p>
		                                                <span class="text-xs text-neutral-500">${Number(other.percent || 0)}%</span>
		                                            </div>
		                                            <div class="w-full bg-neutral-200 h-2 rounded-full mt-2 overflow-hidden">
		                                                <div class="bg-black h-full rounded-full" style="width:${Math.max(0, Math.min(100, Number(other.percent || 0)))}%"></div>
		                                            </div>
		                                        </div>
		                                    </div>
		                                    <div class="flex items-center gap-3">
		                                        ${me.avatar_url ? `<img src="${String(me.avatar_url)}" alt="" class="w-9 h-9 rounded-full object-cover border border-neutral-200 bg-white" />` : `<div class="w-9 h-9 rounded-full bg-neutral-200 border border-neutral-200"></div>`}
		                                        <div class="min-w-0 flex-1">
		                                            <div class="flex items-center justify-between gap-3">
		                                                <p class="text-sm font-semibold text-neutral-900 truncate">${String(me.name || 'You')}</p>
		                                                <div class="flex items-center gap-3 shrink-0">
		                                                    <span class="text-xs text-neutral-500">${Number(me.percent || 0)}%</span>
		                                                    <a href="${courseUrl}" class="text-[10px] font-semibold uppercase tracking-widest text-neutral-700 hover:text-black no-underline">${goToCourseLabel}</a>
		                                                </div>
		                                            </div>
		                                            <div class="w-full bg-neutral-200 h-2 rounded-full mt-2 overflow-hidden">
		                                                <div class="bg-black h-full rounded-full" style="width:${Math.max(0, Math.min(100, Number(me.percent || 0)))}%"></div>
		                                            </div>
		                                        </div>
		                                    </div>
		                                </div>
		                            </div>
		                        `;

		                        sections.push(`
		                            <div class="space-y-4">
		                                <div class="p-6 bg-white border border-neutral-200 rounded-[6px] shadow-sm">
		                                    <h3 class="text-xl font-semibold text-neutral-900">${<?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Partner de Curso' : 'Course Partner'); ?>}</h3>
		                                    <p class="text-sm text-neutral-500 mt-1">${<?php echo json_encode((strpos(get_locale(), 'es') !== false) ? 'Aceptado recientemente' : 'Recently accepted'); ?>}</p>
		                                </div>
		                                <div class="space-y-3">
		                                    <div class="pl-course-partner-invite-item is-accepted flex items-center justify-between gap-4 p-4 border border-neutral-200 rounded-[6px] bg-neutral-50" data-course-id="${Number(inv.course_id) || 0}">
		                                        <div class="min-w-0 flex items-center gap-3">
		                                            ${other.avatar_url ? `<img src="${String(other.avatar_url)}" alt="" class="w-10 h-10 rounded-full object-cover border border-neutral-200 bg-white" />` : `<div class="w-10 h-10 rounded-full bg-neutral-200 border border-neutral-200"></div>`}
		                                            <div class="min-w-0">
		                                                <div class="flex items-center gap-2">
		                                                    <p class="text-sm font-semibold text-neutral-900 truncate">${courseTitle}</p>
		                                                    ${acceptedAt}
		                                                </div>
		                                                <p class="text-xs text-neutral-500">${partnerKindLabel} • ${String(other.name || '')}</p>
		                                                ${dropdown}
		                                            </div>
		                                        </div>
		                                        <div class="flex items-center gap-2 shrink-0">
		                                            <a href="${courseUrl}" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] bg-black text-white hover:bg-neutral-800 no-underline">${goToCourseLabel}</a>
		                                        </div>
		                                    </div>
		                                </div>
		                            </div>
		                        `);
		                    }

		                    if (hasPartnerInvites) {
		                        let redirectToRequests = window.location.href;
		                        try {
		                            const u = new URL(window.location.href);
		                            u.searchParams.set('tab', 'requests');
		                            redirectToRequests = u.toString();
		                        } catch (e) {}

		                        const itemsHtml = coursePartnerInvites.map(inv => {
		                            const fromName = String(inv.from_name || 'User');
		                            const avatarUrl = String(inv.from_avatar_url || '');
		                            const created = inv.created_at ? `<span class="text-xs text-neutral-400">${String(inv.created_at)}</span>` : '';
		                            const courseTitle = String(inv.course_title || '');
		                            const inviteId = Number(inv.id) || 0;
		                            const source = String(inv.source || 'partnerships');
		                            const nonceInput = coursePartnerInviteNonce ? `<input type="hidden" name="_wpnonce" value="${coursePartnerInviteNonce}">` : '';
		                            const redirectInput = `<input type="hidden" name="redirect_to" value="${redirectToRequests.replace(/\"/g, '&quot;')}">`;

		                            return `
		                                <div class="pl-course-partner-invite-item flex items-center justify-between gap-4 p-4 border border-neutral-200 rounded-[6px] bg-neutral-50" data-course-id="${Number(inv.course_id) || 0}">
		                                    <div class="min-w-0 flex items-center gap-3">
		                                        ${avatarUrl ? `<img src="${avatarUrl}" alt="" class="w-10 h-10 rounded-full object-cover border border-neutral-200 bg-white" />` : `<div class="w-10 h-10 rounded-full bg-neutral-200 border border-neutral-200"></div>`}
		                                        <div class="min-w-0">
		                                            <div class="flex items-center gap-2">
		                                                <p class="text-sm font-semibold text-neutral-900 truncate">${courseTitle}</p>
		                                                ${created}
		                                            </div>
		                                            <p class="text-xs text-neutral-500">${partnerKindLabel} • ${fromName}</p>
		                                        </div>
		                                    </div>
		                                    <div class="flex items-center gap-2 shrink-0">
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
		                                </div>
		                            `;
		                        }).join('');

		                        sections.push(`
		                            <div class="space-y-4">
		                                <div class="p-6 bg-white border border-neutral-200 rounded-[6px] shadow-sm">
		                                    <h3 class="text-xl font-semibold text-neutral-900">${partnerTitle}</h3>
		                                    <p class="text-sm text-neutral-500 mt-1">${coursePartnerInvites.length} ${coursePartnerInvites.length === 1 ? 'request' : 'requests'}</p>
		                                </div>
		                                <div class="space-y-3">${itemsHtml}</div>
		                            </div>
		                        `);
		                    }

		                    if (hasFollow) {
		                        const itemsHtml = followRequests.map(req => {
		                            const name = String(req.from_name || 'User');
		                            const avatarUrl = String(req.from_avatar_url || '');
		                            const created = req.created_at ? `<span class="text-xs text-neutral-400">${String(req.created_at)}</span>` : '';
		                            const reqId = Number(req.id) || 0;
		                            const fromUserId = Number(req.from_user_id) || 0;
		                            const nonceInput = respondNonce ? `<input type="hidden" name="_wpnonce" value="${respondNonce}">` : '';
		                            const blockNonceInput = blockNonce ? `<input type="hidden" name="_wpnonce" value="${blockNonce}">` : '';
		                            return `
		                                <div class="flex items-center justify-between gap-4 p-4 border border-neutral-200 rounded-[6px] bg-neutral-50">
		                                    <div class="min-w-0 flex items-center gap-3">
		                                        ${avatarUrl ? `<img src="${avatarUrl}" alt="" class="w-10 h-10 rounded-full object-cover border border-neutral-200 bg-white" />` : `<div class="w-10 h-10 rounded-full bg-neutral-200 border border-neutral-200"></div>`}
		                                        <div class="min-w-0">
		                                            <div class="flex items-center gap-2">
		                                                <p class="text-sm font-semibold text-neutral-900 truncate">${name}</p>
		                                                ${created}
		                                            </div>
		                                            <p class="text-xs text-neutral-500">follow</p>
		                                        </div>
		                                    </div>
		                                    <div class="flex items-center gap-2 shrink-0">
		                                        <form method="post" action="${adminPostUrl}" class="m-0">
		                                            ${nonceInput}
		                                            <input type="hidden" name="action" value="pl_relationship_respond">
		                                            <input type="hidden" name="request_id" value="${reqId}">
		                                            <input type="hidden" name="decision" value="accept">
		                                            <button type="submit" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] bg-black text-white hover:bg-neutral-800">${acceptLabel}</button>
		                                        </form>
		                                        <form method="post" action="${adminPostUrl}" class="m-0">
		                                            ${nonceInput}
		                                            <input type="hidden" name="action" value="pl_relationship_respond">
		                                            <input type="hidden" name="request_id" value="${reqId}">
		                                            <input type="hidden" name="decision" value="reject">
		                                            <button type="submit" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] border border-neutral-200 text-neutral-700 hover:bg-white">${rejectLabel}</button>
		                                        </form>
		                                        <form method="post" action="${adminPostUrl}" class="m-0">
		                                            ${blockNonceInput}
		                                            <input type="hidden" name="action" value="pl_relationship_block">
		                                            <input type="hidden" name="blocked_user_id" value="${fromUserId}">
		                                            <button type="submit" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-[6px] border border-red-200 text-red-600 hover:bg-white">${blockLabel}</button>
		                                        </form>
		                                    </div>
		                                </div>
		                            `;
		                        }).join('');

		                        sections.push(`
		                            <div class="space-y-4">
		                                <div class="p-6 bg-white border border-neutral-200 rounded-[6px] shadow-sm">
		                                    <h3 class="text-xl font-semibold text-neutral-900">${title}</h3>
		                                    <p class="text-sm text-neutral-500 mt-1">${followRequests.length} ${followRequests.length === 1 ? 'request' : 'requests'}</p>
		                                </div>
		                                <div class="space-y-3">${itemsHtml}</div>
		                            </div>
		                        `);
		                    }

		                    wrapper.innerHTML = `<div class="space-y-8">${sections.join('')}</div>`;

		                    // Toggle dropdown for accepted partner card(s).
		                    try {
		                        wrapper.querySelectorAll('.pl-course-partner-invite-item.is-accepted').forEach((item) => {
		                            item.addEventListener('click', (evt) => {
		                                const target = evt.target;
		                                if (target && target.closest && target.closest('a,button,form')) return;
		                                const dd = item.querySelector('.pl-course-partner-invite-dropdown');
		                                if (!dd) return;
		                                dd.classList.toggle('hidden');
		                            });
		                        });
		                    } catch (e) {}
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

        // Use DOMContentLoaded to ensure we run after standard WP init if needed
	        if (document.readyState === 'loading') {
	            document.addEventListener('DOMContentLoaded', init);
	        } else {
	            init();
	        }
    })();
</script>
