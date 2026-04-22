<?php
if (!defined('ABSPATH')) exit;
?>

<div id="pcg-toast" class="pcg-toast font-semibold">Action successful!</div>

<div class="pcg-profile-wrapper">
    
    <!-- Sidebar -->
    <aside id="politeia-profile-sidebar" class="pcg-sidebar w-72 bg-neutral-50 border-r border-neutral-200 transition-transform duration-300 ease-in-out flex flex-col shrink-0">
        
        <!-- Profile Section -->
        <div class="hidden lg:flex p-8 flex-col items-center border-b border-neutral-200">
            <div class="w-24 h-24 gold-gradient p-1 rounded-full mb-5 shadow-lg">
                <div class="w-full h-full bg-white rounded-full flex items-center justify-center overflow-hidden">
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($display_name); ?>" class="w-full h-full object-cover">
                </div>
            </div>

	            <h2 class="text-lg font-semibold text-neutral-900 mb-4"><?php echo esc_html($display_name); ?></h2>

	            <?php if (!$is_own_profile) : ?>
	                <div class="w-full mb-4">
	                    <?php if (!is_user_logged_in()) : ?>
	                        <a href="<?php echo esc_url(wp_login_url(esc_url_raw(home_url((string) ($_SERVER['REQUEST_URI'] ?? '/'))))); ?>" class="inline-flex w-full items-center justify-center px-4 py-2 text-xs font-semibold uppercase tracking-widest rounded-[6px] border border-neutral-200 text-neutral-800 hover:bg-white no-underline">
	                            <?php echo esc_html__('Follow', 'politeia-learning'); ?>
	                        </a>
	                    <?php elseif (class_exists('PL_Relationships') && $pl_access_level !== 'blocked') : ?>
	                        <?php
	                        $label_follow = __('Follow', 'politeia-learning');
	                        $label_following = __('Following', 'politeia-learning');
	                        $label_requested = __('Requested', 'politeia-learning');
	                        if ($label_follow === '') $label_follow = 'Follow';
	                        if ($label_following === '') $label_following = 'Following';
	                        if ($label_requested === '') $label_requested = 'Requested';
	                        ?>
	                        <?php if (PL_Relationships::is_effective((int) $logged_in_user_id, (int) $user_id, PL_Relationships::TYPE_FOLLOW)) : ?>
	                            <span class="inline-flex w-full items-center justify-center px-4 py-2 text-xs font-semibold uppercase tracking-widest rounded-[6px] bg-neutral-100 text-neutral-800">
	                                <?php echo esc_html($label_following); ?>
	                            </span>
	                        <?php elseif ($pl_follow_status === PL_Relationships::STATUS_PENDING) : ?>
	                            <span class="inline-flex w-full items-center justify-center px-4 py-2 text-xs font-semibold uppercase tracking-widest rounded-[6px] bg-neutral-50 text-neutral-500 border border-neutral-200">
	                                <?php echo esc_html($label_requested); ?>
	                            </span>
	                        <?php else : ?>
	                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="m-0">
	                                <?php wp_nonce_field('pl_relationship_request'); ?>
	                                <input type="hidden" name="action" value="pl_relationship_request" />
	                                <input type="hidden" name="to_user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
	                                <input type="hidden" name="rel_type" value="<?php echo esc_attr(PL_Relationships::TYPE_FOLLOW); ?>" />
	                                <input type="submit" class="inline-flex w-full items-center justify-center px-4 py-2 text-xs font-semibold uppercase tracking-widest rounded-[6px] gold-gradient text-black shadow-sm hover:shadow-lg transition-all cursor-pointer" value="<?php echo esc_attr($label_follow); ?>" />
	                            </form>
	                        <?php endif; ?>
	                    <?php endif; ?>
	                </div>
	            <?php endif; ?>
	            
	            <div class="flex gap-4 text-neutral-400 mb-2">
	                <?php if ($twitter): ?><a href="<?php echo esc_url($twitter); ?>" class="hover:text-[#8A6B1E] transition-colors"><i data-lucide="twitter" size="16"></i></a><?php endif; ?>
	                <?php if ($linkedin): ?><a href="<?php echo esc_url($linkedin); ?>" class="hover:text-[#8A6B1E] transition-colors"><i data-lucide="linkedin" size="16"></i></a><?php endif; ?>
	                <?php if ($github): ?><a href="<?php echo esc_url($github); ?>" class="hover:text-[#8A6B1E] transition-colors"><i data-lucide="github" size="16"></i></a><?php endif; ?>
                <?php if ($instagram): ?><a href="<?php echo esc_url($instagram); ?>" class="hover:text-[#8A6B1E] transition-colors"><i data-lucide="instagram" size="16"></i></a><?php endif; ?>
                
                <?php if (!$twitter && !$linkedin && !$github && !$instagram): ?>
                    <a href="#" class="hover:text-[#8A6B1E] transition-colors"><i data-lucide="twitter" size="16"></i></a>
                    <a href="#" class="hover:text-[#8A6B1E] transition-colors"><i data-lucide="linkedin" size="16"></i></a>
                    <a href="#" class="hover:text-[#8A6B1E] transition-colors"><i data-lucide="github" size="16"></i></a>
                    <a href="#" class="hover:text-[#8A6B1E] transition-colors"><i data-lucide="instagram" size="16"></i></a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 py-4 overflow-y-auto" id="pcg-nav-menu">
            <!-- JS will inject items here -->
        </nav>

        <div class="p-4 border-t border-neutral-200">
            <?php $op_template = get_option('pcg_operation_template', '/center'); ?>
            <a href="<?php echo esc_url(function_exists('bp_core_get_user_domain') ? bp_core_get_user_domain($user_id) . ltrim($op_template, '/') . '/' : $op_template); ?>" class="flex items-center gap-3 p-3 bg-white border border-neutral-200 rounded-[6px] shadow-sm hover:bg-neutral-50 transition-colors">
                <span class="material-symbols-outlined text-[20px] text-neutral-500">space_dashboard</span>
                <span class="text-[10px] text-neutral-500 font-semibold uppercase tracking-widest">
                    <?php echo (strpos(get_locale(), 'es') !== false) ? 'OPERACIONES' : 'OPERATIONS'; ?>
                </span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col overflow-hidden bg-white">
        <!-- Dashboard Header -->
	        <header class="pcg-dashboard-header h-16 border-b border-neutral-200 bg-white flex items-center justify-between px-5 shrink-0">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden pcg-minimal-button text-neutral-900 !text-[24px]">
                    <i class="bb-icon-l bb-icon-bars"></i>
                </button>
                <h2 class="text-sm font-medium text-neutral-400">
                    Profile / <span id="pcg-current-tab-label" class="text-neutral-900 font-semibold"><?php echo esc_html($initial_label); ?></span>
                </h2>
            </div>
	            <div class="flex items-center gap-4">
	                <?php if ($is_own_profile) : ?>
	                    <a href="<?php echo esc_url($friends_url); ?>" class="relative group flex items-center gap-2 text-neutral-500 hover:text-neutral-900 transition-colors no-underline">
	                        <i data-lucide="users" size="16"></i>
	                        <span class="text-xs font-semibold"><?php echo (int) $friends_count; ?></span>
	                    </a>

                    <a href="<?php echo esc_url($notifications_url); ?>" class="relative group cursor-pointer text-neutral-500 hover:text-neutral-900 transition-colors no-underline" aria-label="Notifications">
                        <i data-lucide="bell" size="16"></i>
                        <?php if ($unread_notifications > 0) : ?>
                            <span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 bg-red-500 rounded-full border border-white"></span>
                        <?php endif; ?>
                    </a>
	                <?php endif; ?>
	                <?php if (!$is_own_profile) : ?>
	                    <?php if (!is_user_logged_in()) : ?>
	                        <a href="<?php echo esc_url(wp_login_url(esc_url_raw(home_url((string) ($_SERVER['REQUEST_URI'] ?? '/'))))); ?>" class="hidden sm:inline-flex items-center px-3 py-1.5 text-xs font-semibold border border-neutral-200 rounded-[6px] text-neutral-700 hover:bg-neutral-50 no-underline">
	                            <?php echo esc_html__('Inicia sesión', 'politeia-learning'); ?>
	                        </a>
	                    <?php elseif (class_exists('PL_Relationships') && $pl_access_level !== 'blocked') : ?>
	                        <?php
	                        $label_follow = __('Seguir', 'politeia-learning');
	                        $label_friend = __('Amistad', 'politeia-learning');
	                        $label_pending = __('Solicitud enviada', 'politeia-learning');
	                        $label_following = __('Siguiendo', 'politeia-learning');
	                        $label_friends = __('Amigos', 'politeia-learning');
	                        $label_subscribed = __('Suscrito', 'politeia-learning');
	                        $label_subscribe = __('Suscribirme', 'politeia-learning');
	                        $label_request_friend = __('Solicitar amistad', 'politeia-learning');
	                        if ($label_follow === '') $label_follow = 'Seguir';
	                        if ($label_friend === '') $label_friend = 'Amistad';
	                        if ($label_pending === '') $label_pending = 'Solicitud enviada';
	                        if ($label_following === '') $label_following = 'Siguiendo';
	                        if ($label_friends === '') $label_friends = 'Amigos';
	                        if ($label_subscribed === '') $label_subscribed = 'Suscrito';
	                        if ($label_subscribe === '') $label_subscribe = 'Suscribirme';
	                        if ($label_request_friend === '') $label_request_friend = 'Solicitar amistad';

	                        $pl_subscribe_url = (string) apply_filters('pl_subscribe_checkout_url', '', (int) $user_id, (int) $logged_in_user_id);
	                        ?>
	                        <div class="hidden sm:flex items-center gap-2">
	                            <?php if ($pl_subscribe_active) : ?>
	                                <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-[6px] bg-neutral-100 text-neutral-800"><?php echo esc_html($label_subscribed); ?></span>
	                            <?php elseif ($pl_subscribe_url !== '') : ?>
	                                <a href="<?php echo esc_url($pl_subscribe_url); ?>" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-[6px] gold-gradient text-black no-underline">
	                                    <?php echo esc_html($label_subscribe); ?>
	                                </a>
	                            <?php endif; ?>

	                            <?php if (PL_Relationships::is_effective_friendship((int) $logged_in_user_id, (int) $user_id)) : ?>
	                                <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-[6px] bg-neutral-100 text-neutral-800"><?php echo esc_html($label_friends); ?></span>
	                            <?php elseif ($pl_friend_status === PL_Relationships::STATUS_PENDING) : ?>
	                                <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-[6px] bg-neutral-50 text-neutral-500 border border-neutral-200"><?php echo esc_html($label_pending); ?></span>
	                            <?php else : ?>
	                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="m-0">
	                                    <?php wp_nonce_field('pl_relationship_request'); ?>
	                                    <input type="hidden" name="action" value="pl_relationship_request" />
	                                    <input type="hidden" name="to_user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
	                                    <input type="hidden" name="rel_type" value="<?php echo esc_attr(PL_Relationships::TYPE_FRIEND); ?>" />
	                                    <input type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold border border-neutral-200 rounded-[6px] text-neutral-700 hover:bg-neutral-50" value="<?php echo esc_attr($label_request_friend); ?>" />
	                                </form>
	                            <?php endif; ?>

	                            <?php if (PL_Relationships::is_effective((int) $logged_in_user_id, (int) $user_id, PL_Relationships::TYPE_FOLLOW)) : ?>
	                                <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-[6px] bg-neutral-100 text-neutral-800"><?php echo esc_html($label_following); ?></span>
	                            <?php elseif ($pl_follow_status === PL_Relationships::STATUS_PENDING) : ?>
	                                <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-[6px] bg-neutral-50 text-neutral-500 border border-neutral-200"><?php echo esc_html($label_pending); ?></span>
	                            <?php else : ?>
	                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="m-0">
	                                    <?php wp_nonce_field('pl_relationship_request'); ?>
	                                    <input type="hidden" name="action" value="pl_relationship_request" />
	                                    <input type="hidden" name="to_user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
	                                    <input type="hidden" name="rel_type" value="<?php echo esc_attr(PL_Relationships::TYPE_FOLLOW); ?>" />
	                                    <input type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold border border-neutral-200 rounded-[6px] text-neutral-700 hover:bg-neutral-50" value="<?php echo esc_attr($label_follow); ?>" />
	                                </form>
	                            <?php endif; ?>
	                        </div>
	                    <?php endif; ?>
	                <?php endif; ?>
	                <div class="hidden sm:block text-right">
	                    <p class="text-[8px] text-neutral-400 font-semibold tracking-widest uppercase">Rank</p>
	                    <p class="text-xs font-semibold text-[#8A6B1E]"><?php echo esc_html($rank); ?></p>
	                </div>
	            </div>
	        </header>

            <?php if ($pl_subscribe_error_message !== '') : ?>
                <div class="px-5 py-3 border-b border-neutral-200 bg-red-50 text-red-800 text-xs">
                    <?php echo esc_html($pl_subscribe_error_message); ?>
                </div>
            <?php endif; ?>

	        <!-- Dynamic Content Container -->
	        <div id="pcg-content-area"
	            class="flex-1 overflow-y-auto p-8 custom-scrollbar"
            <?php if ($server_view !== '') : ?>
                data-server-view="<?php echo esc_attr($server_view); ?>"
            <?php endif; ?>
	        >
	            <?php if ($server_view === 'notifications' && function_exists('bp_get_template_part')) : ?>
	                <div class="<?php echo esc_attr($pl_profile_content_container_class); ?>">
	                    <?php bp_get_template_part('members/single/notifications'); ?>
	                </div>
	            <?php elseif ($server_view === 'friends' && function_exists('bp_get_template_part')) : ?>
	                <div class="<?php echo esc_attr($pl_profile_content_container_class); ?>">
	                    <!-- Student Friends (BuddyPress removed) -->
	                </div>
	            <?php else : ?>
	                <!-- JS will inject content here -->
	            <?php endif; ?>
	        </div>
    </main>
</div>
