<?php
/**
 * Template Name: Politeia Profile
 * Description: A modern portfolio dashboard for member profiles with native header and footer.
 */

if (!defined('ABSPATH')) exit;

// Get displayed user ID (BuddyBoss context)
$user_id = bp_displayed_user_id();
if (!$user_id) {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
    } else {
        auth_redirect();
        exit;
    }
}

$userdata = get_userdata($user_id);
$display_name = function_exists('pl_get_user_full_name_or_display_name') 
    ? pl_get_user_full_name_or_display_name($user_id, $userdata->display_name) 
    : $userdata->display_name;

$avatar_url = function_exists('bp_core_fetch_avatar') 
    ? bp_core_fetch_avatar(['item_id' => $user_id, 'html' => false, 'type' => 'full']) 
    : get_avatar_url($user_id);

// --- Portfolio Settings ---
$portfolio_manager = PL_Member_Profile_Portfolio_Manager::get_instance();
$portfolio_settings = $portfolio_manager->get_settings($user_id);
$twitter = function_exists('xprofile_get_field_data') ? xprofile_get_field_data('Twitter', $user_id) : '';
$linkedin = function_exists('xprofile_get_field_data') ? xprofile_get_field_data('LinkedIn', $user_id) : '';
$github = function_exists('xprofile_get_field_data') ? xprofile_get_field_data('GitHub', $user_id) : '';
$instagram = function_exists('xprofile_get_field_data') ? xprofile_get_field_data('Instagram', $user_id) : '';

// Rank
$rank = get_user_meta($user_id, 'pl_profile_rank', true) ?: 'Premium Member';

// --- Filter Queries based on Portfolio ---
$show_courses = !(isset($portfolio_settings['courses']) && $portfolio_settings['courses']->is_private == 1);
$show_writings = !(isset($portfolio_settings['writings']) && $portfolio_settings['writings']->is_private == 1);
$show_specs = !(isset($portfolio_settings['specializations']) && $portfolio_settings['specializations']->is_private == 1);

if ($show_courses && isset($portfolio_settings['courses'])) {
    if ($portfolio_settings['courses']->visibility_mode === 'selected') {
        $courses_ids = !empty($portfolio_settings['courses']->selected_ids) ? $portfolio_settings['courses']->selected_ids : [-1]; // -1 ensures no results if empty
    }
}
if ($show_writings && isset($portfolio_settings['writings'])) {
    if ($portfolio_settings['writings']->visibility_mode === 'selected') {
        $writings_ids = !empty($portfolio_settings['writings']->selected_ids) ? $portfolio_settings['writings']->selected_ids : [-1];
    }
}
if ($show_specs && isset($portfolio_settings['specializations'])) {
    if ($portfolio_settings['specializations']->visibility_mode === 'selected') {
        $specs_ids = !empty($portfolio_settings['specializations']->selected_ids) ? $portfolio_settings['specializations']->selected_ids : [-1];
    }
}

// Courses Query
$user_courses = [];
if ($show_courses) {
    $courses_args = [
        'post_type' => 'sfwd-courses',
        'post_status' => 'publish',
        'author' => $user_id,
        'posts_per_page' => -1
    ];
    if (!empty($courses_ids)) {
        $courses_args['post__in'] = $courses_ids;
        $courses_args['orderby'] = 'post__in';
    }
    $courses_query = new WP_Query($courses_args);
    if ($courses_query->have_posts()) {
        while ($courses_query->have_posts()) {
            $courses_query->the_post();
            $user_courses[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'price' => get_post_meta(get_the_ID(), 'course_price', true) ?: 'Free',
                'img' => get_the_post_thumbnail_url(get_the_ID(), 'large') ?: 'https://images.unsplash.com/photo-1633356122544-f134324a6cee?auto=format&fit=crop&w=400&q=80',
                'link' => get_permalink()
            ];
        }
        wp_reset_postdata();
    }
}

// Writings Query
$user_writings = [];
if ($show_writings) {
    $writings_args = [
        'post_type' => 'post',
        'post_status' => 'publish',
        'author' => $user_id,
        'posts_per_page' => -1
    ];
    if (!empty($writings_ids)) {
        $writings_args['post__in'] = $writings_ids;
        $writings_args['orderby'] = 'post__in';
    }
    $writings_query = new WP_Query($writings_args);
    if ($writings_query->have_posts()) {
        while ($writings_query->have_posts()) {
            $writings_query->the_post();
            $categories = get_the_category();
            $category_name = !empty($categories) ? $categories[0]->name : 'Writing';
            $user_writings[] = [
                'id' => get_the_ID(),
                'category' => $category_name,
                'title' => get_the_title(),
                'img' => get_the_post_thumbnail_url(get_the_ID(), 'large') ?: 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=400&q=80',
                'link' => get_permalink()
            ];
        }
        wp_reset_postdata();
    }
}

// Specializations Query (Groups)
$user_specs = [];
if ($show_specs) {
    $specs_args = [
        'post_type' => 'groups',
        'post_status' => 'publish',
        'author' => $user_id,
        'posts_per_page' => -1
    ];
    if (!empty($specs_ids)) {
        $specs_args['post__in'] = $specs_ids;
        $specs_args['orderby'] = 'post__in';
    }
    $specs_query = new WP_Query($specs_args);
    if ($specs_query->have_posts()) {
        while ($specs_query->have_posts()) {
            $specs_query->the_post();
            $user_specs[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'img' => get_the_post_thumbnail_url(get_the_ID(), 'large') ?: 'https://images.unsplash.com/photo-1579621970795-87f967b16ce8?auto=format&fit=crop&w=400&q=80',
                'link' => get_permalink()
            ];
        }
        wp_reset_postdata();
    }
}

// Book Notes (Thoughts Feed)
global $wpdb;
$table_notes = $wpdb->prefix . 'politeia_read_ses_notes';
$table_ub = $wpdb->prefix . 'politeia_user_books';
$table_books = $wpdb->prefix . 'politeia_books';
$table_book_authors = $wpdb->prefix . 'politeia_book_authors';
$table_authors = $wpdb->prefix . 'politeia_authors';

$user_notes = $wpdb->get_results( $wpdb->prepare(
    "SELECT 
        n.id as note_id,
        n.note, 
        n.created_at, 
        b.title AS book_title, 
        (SELECT GROUP_CONCAT(a.display_name SEPARATOR ', ') 
         FROM {$table_book_authors} ba 
         JOIN {$table_authors} a ON ba.author_id = a.id 
         WHERE ba.book_id = b.id
        ) AS book_author,
        ub.cover_url AS user_cover,
        b.cover_url AS book_cover,
        b.year AS book_year
    FROM {$table_notes} n
    JOIN {$table_ub} ub ON n.user_book_id = ub.id
    JOIN {$table_books} b ON ub.book_id = b.id
    WHERE n.user_id = %d AND n.visibility = 'public' AND n.note != ''
    ORDER BY n.created_at DESC",
    $user_id
) );

$book_thoughts = [];
foreach ( $user_notes as $note ) {
    $final_cover = !empty($note->user_cover) ? $note->user_cover : $note->book_cover;
    $cover = !empty($final_cover) ? $final_cover : 'https://via.placeholder.com/60x90?text=No+Cover';
    
    $book_thoughts[] = [
        'id' => $note->note_id,
        'user' => $display_name,
        'handle' => '@' . $userdata->user_nicename,
        'avatar' => $avatar_url,
        'time' => human_time_diff( strtotime( $note->created_at ), current_time( 'timestamp' ) ) . ' ago',
        'content' => $note->note,
        'book' => $note->book_title,
        'author' => $note->book_author ?: 'Unknown Author',
        'cover' => $cover,
        'date' => date_i18n( 'F j, Y', strtotime( $note->created_at ) ),
        'book_year' => $note->book_year ?: 'N/A'
    ];
}

/**
 * Force BuddyBoss Header/Footer
 */
add_filter('buddyboss_theme_remove_header', '__return_false', 999);
add_filter('buddyboss_theme_remove_footer', '__return_false', 999);

get_header(); 
?>

<!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@latest"></script>
<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Inter:wght@400;500;600;700;900&family=Newsreader:opsz,wght@6..72,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=space_dashboard" />

<style>
    /* 
       THEME OVERRIDES
       BuddyBoss Theme has several nested containers. 
       We need to "break out" so our dashboard fits exactly between peak and base.
    */
    #primary, #primary .entry-content {
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Fix Tailwind/Theme Container Conflict */
    .container {
        max-width: 1260px !important;
        margin-left: auto !important;
        margin-right: auto !important;
        padding-left: 20px !important;
        padding-right: 20px !important;
    }

    /* Target ONLY Content Area Container padding on mobile/tablet */
    @media (max-width: 1023px) {
        .site-content .container {
            padding: 0 !important;
        }
    }

    /* Hide Desktop Header on Mobile to Fix "Double Header" Issue */
    @media (max-width: 799px) {
        .site-header .default-header {
            display: none !important;
        }
    }

    .pcg-profile-wrapper {
        font-family: 'Poppins', sans-serif;
        background-color: #ffffff;
        color: #171717;
        display: flex;
        height: 80vh; 
        min-height: 600px;
        overflow: hidden;
        width: 100%;
        margin: auto;
    }
    .bb-grid-cell:not(.no-gutter), .bb-grid>:not(.no-gutter) {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    .gold-gradient {
        background: linear-gradient(135deg, #8A6B1E, #C79F32, #E9D18A);
    }
    .gold-text {
        background: linear-gradient(to right, #8A6B1E, #C79F32, #E9D18A);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f9fafb;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #e5e7eb;
        border-radius: 3px;
    }
    .pcg-nav-item.active {
        background-color: #f9fafb;
        border-left: 4px solid #8A6B1E;
        color: #8A6B1E;
        border-radius: 0 !important;
    }
    .pcg-nav-item {
        border-left: 4px solid transparent;
        transition: all 0.2s ease;
        background: none;
        border-top: none;
        border-right: none;
        border-bottom: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        width: 100%;
        text-align: left;
        border-radius: 0 !important;
    }
    .card-transition {
        animation: slideUp 0.4s ease-out forwards;
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    /* Max font weight 600 */
    .pcg-profile-wrapper h1, .pcg-profile-wrapper h2, .pcg-profile-wrapper h3, .pcg-profile-wrapper h4, .pcg-profile-wrapper span, .pcg-profile-wrapper p, .pcg-profile-wrapper button {
        font-weight: 400;
    }
    .pcg-profile-wrapper .font-semibold, .pcg-profile-wrapper b, .pcg-profile-wrapper strong, .pcg-profile-wrapper h1, .pcg-profile-wrapper h2, .pcg-profile-wrapper h3 {
        font-weight: 600 !important;
    }
    .pcg-toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 24px;
        background: #171717;
        color: white;
        border-radius: 6px;
        transform: translateY(100px);
        transition: transform 0.3s ease;
        z-index: 9999;
    }
    .pcg-toast.show {
        transform: translateY(0);
    }
    /* Sidebar behavior */
    #politeia-profile-sidebar.pcg-sidebar {
        position: relative !important;
        transform: none !important;
        border-left: 1px solid #e5e5e5;
    }
    @media (max-width: 1023px) {
        #politeia-profile-sidebar.pcg-sidebar {
            position: fixed !important;
            left: 0;
            top: 0;
            height: 100%;
            transform: translateX(-100%) !important;
            z-index: 50;
            margin-top: 186px;
            height: calc(100% - 186px);
        }
        #politeia-profile-sidebar.pcg-sidebar.open {
            transform: translateX(0) !important;
            box-shadow: 4px 0 15px -3px rgba(0, 0, 0, 0.07);
        }
    }

    /* Fixed Dashboard Header for Mobile/Tablet */
    @media (max-width: 1023px) {
        .pcg-dashboard-header {
            position: fixed !important;
            top: 122px !important;
            left: 0;
            width: 100%;
            z-index: 60;
            background: white;
            border-bottom: 2px solid #f0f0f0;
        }
        #pcg-content-area {
            padding: 20px !important;
            padding-top: 80px !important; /* Offset + internal padding for fixed header */
        }
    }

    .pcg-minimal-button {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        outline: none !important;
        -webkit-tap-highlight-color: transparent !important;
        padding: 0 !important;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .pcg-minimal-button:hover, 
    .pcg-minimal-button:focus, 
    .pcg-minimal-button:active {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        outline: none !important;
    }

    /* Hybrid Executive Styles */
    .accent-gradient {
        background: linear-gradient(135deg, #8A6B1E, #C79F32, #E9D18A);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        display: inline-block;
    }

    .hybrid-container {
        height: 100px;
        width: 100%;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 6px 6px 0 0;
        overflow: hidden;
        display: flex;
        align-items: center;
        box-shadow: none;
        transition: border-color 0.3s ease;
    }

    .hybrid-container:hover {
        border-color: #cbd5e1;
    }

    .hybrid-book-section {
        background-color: #fcfcfc;
        border-left: 1px solid #e2e8f0;
        height: 100%;
        width: 240px;
        padding: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }

    .hybrid-book-title {
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: -0.02em;
        line-height: 1.1;
        color: #1e293b;
    }

    .hybrid-book-author {
        font-size: 9px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        margin-top: 2px;
    }

    .hybrid-catalog-tag {
        font-size: 8px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f1f5f9;
        font-family: 'Inter', sans-serif;
    }

    .hybrid-bookmark-icon {
        position: absolute;
        top: -4px;
        right: -2px;
    }

    .hybrid-content-box {
        background: white;
        border: 1px solid #e2e8f0;
        border-top: none;
        border-radius: 0 0 6px 6px;
        padding: 48px;
    }

    .hybrid-note-text {
        font-family: 'Newsreader', serif;
        font-size: 22px;
        font-weight: 300;
        line-height: 1.6;
        color: #1e293b;
    }

    .hybrid-content-box * {
        font-style: normal !important;
    }
</style>

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
                    Profile / <span id="pcg-current-tab-label" class="text-neutral-900 font-semibold">Main</span>
                </h2>
            </div>
            <div class="flex items-center gap-4">
                <div class="relative group cursor-pointer text-neutral-500 hover:text-neutral-900 transition-colors">
                    <i data-lucide="bell" size="16"></i>
                    <span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 bg-red-500 rounded-full border border-white"></span>
                </div>
                <div class="hidden sm:block text-right">
                    <p class="text-[8px] text-neutral-400 font-semibold tracking-widest uppercase">Rank</p>
                    <p class="text-xs font-semibold text-[#8A6B1E]"><?php echo esc_html($rank); ?></p>
                </div>
            </div>
        </header>

        <!-- Dynamic Content Container -->
        <div id="pcg-content-area" class="flex-1 overflow-y-auto p-8 custom-scrollbar">
            <!-- JS will inject content here -->
        </div>
    </main>
</div>

<script>
    (function() {
        // --- Data Layer ---
        const portfolioSettings = <?php echo json_encode($portfolio_settings); ?>;
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
            { id: 'book', label: 'Libros', icon: 'book' }
        ];

        // Filter menu items based on privacy
        const menuItems = allMenuItems.filter(item => {
            if (portfolioSettings[item.id] && portfolioSettings[item.id].is_private == 1) {
                return false;
            }
            return true;
        });

        const courses = <?php echo json_encode($user_courses); ?>;
        const articles = <?php echo json_encode($user_writings); ?>;
        const specializations = <?php echo json_encode($user_specs); ?>;

        const thoughts = <?php echo json_encode($book_thoughts); ?>;

        const books = [
            { id: 1, title: 'The Architect\'s Mind', price: '$24.00', img: 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=400&q=80' },
            { id: 2, title: 'Visual Poetry', price: '$32.50', img: 'https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=400&q=80' }
        ];

        // --- Core Logic ---
        let currentTab = 'main';

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
            if (label) label.innerText = menuItems.find(m => m.id === tabId).label;
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
                    <i data-lucide="${item.icon}" size="18"></i>
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
            wrapper.className = 'max-w-6xl mx-auto card-transition';

            switch (currentTab) {
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

<?php get_footer(); ?>
