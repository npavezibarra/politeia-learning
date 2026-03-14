<?php
/**
 * Profile Section Template
 */
if (!defined('ABSPATH')) exit;

// Get current user details
$user_slug = get_query_var('pcg_creator_user');
$user = get_user_by('slug', $user_slug);
$first_name = get_user_meta($user->ID, 'first_name', true) ?: $user->display_name;
$last_name = get_user_meta($user->ID, 'last_name', true);
$avatar_url = get_avatar_url($user->ID, ['size' => 128]);
?>

<style>
    :root {
        --pcg-profile-pure-black: #000000;
        --pcg-profile-deep-gray: #333333;
        --pcg-profile-light-gray: #A8A8A8;
        --pcg-profile-subtle-gray: #F5F5F5;
        --pcg-profile-off-white: #FEFEFF;
        --pcg-profile-gold-grad: linear-gradient(135deg, #8A6B1E, #C79F32, #E9D18A);
        --pcg-profile-copper-grad: linear-gradient(135deg, #783F27, #B87333, #E5AA70);
        --pcg-profile-border-radius: 6px;
    }

    .pcg-profile-view {
        background-color: #FAFAFB;
        font-family: 'Poppins', sans-serif;
        color: var(--pcg-profile-pure-black);
        padding: 40px 20px;
        width: 100%;
        box-sizing: border-box;
    }

    .pcg-profile-inner {
        max-width: 1080px;
        margin: 0 auto;
    }

    .pcg-profile-view .form-card {
        background: #FFFFFF;
        border-radius: var(--pcg-profile-border-radius);
        border: 1px solid #EAEAEA;
        box-shadow: none !important;
        overflow: hidden;
    }

    /* Golden Header Accents */
    .pcg-profile-view .card-header-accent {
        height: 4px;
        background: var(--pcg-profile-gold-grad);
    }

    /* Minimalist Inputs */
    .pcg-profile-view .input-field {
        width: 100%;
        padding: 0.75rem 0;
        border: none;
        border-bottom: 1px solid var(--pcg-profile-light-gray);
        border-radius: 0;
        transition: all 0.3s ease;
        outline: none;
        background: transparent;
    }

    .pcg-profile-view .input-field:focus {
        border-bottom-color: #C79F32;
        background: transparent;
    }

    .pcg-profile-view .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--pcg-profile-pure-black);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .pcg-profile-view .icon-gold {
        background: var(--pcg-profile-gold-grad);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .pcg-profile-view .author-list-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.6rem;
    }

    /* Small Circular White Badge with Golden Gradient Border */
    .pcg-profile-view .number-badge {
        width: 26px;
        height: 26px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--pcg-profile-pure-black);
        flex-shrink: 0;
        position: relative;
        background: linear-gradient(white, white) padding-box, var(--pcg-profile-gold-grad) border-box;
        border: 1px solid transparent;
    }

    /* Profile Photo Styles */
    .pcg-profile-view .profile-photo-container {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        position: relative;
        background: var(--pcg-profile-gold-grad) border-box;
        border: 2px solid transparent;
        padding: 2px;
        flex-shrink: 0;
    }

    .pcg-profile-view .profile-photo {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        background: #eee;
    }

    /* Tab Styles */
    .pcg-profile-view .tab-btn {
        text-align: left;
        padding: 1.25rem 1rem;
        font-weight: 500;
        color: var(--pcg-profile-light-gray);
        transition: all 0.2s;
        border-left: 4px solid transparent;
        background: transparent;
        border-right: none;
        border-top: none;
        border-bottom: none;
        cursor: pointer;
        width: 100%;
    }

    .pcg-profile-view .tab-btn.active {
        color: var(--pcg-profile-pure-black);
        background-color: white;
        border-left: 4px solid;
        border-image: var(--pcg-profile-gold-grad) 1;
    }

    .pcg-profile-view .gold-cta {
        background: var(--pcg-profile-gold-grad);
        color: #000;
        font-weight: 600;
        padding: 0.75rem 2.5rem;
        border-radius: var(--pcg-profile-border-radius);
        transition: transform 0.1s;
        border: none;
        cursor: pointer;
    }

    .pcg-profile-view .gold-cta:active {
        transform: scale(0.98);
    }

    /* Privacy Toggle Styles */
    .pcg-profile-view .privacy-wrapper {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-left: auto;
        cursor: pointer;
        user-select: none;
    }

    .pcg-profile-view .toggle-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 22px;
    }

    .pcg-profile-view .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .pcg-profile-view .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--pcg-profile-light-gray);
        transition: .4s;
        border-radius: var(--pcg-profile-border-radius);
    }

    .pcg-profile-view .slider:before {
        position: absolute;
        content: "";
        height: 14px;
        width: 14px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: calc(var(--pcg-profile-border-radius) / 2);
    }

    .pcg-profile-view .toggle-switch input:checked + .slider {
        background: var(--pcg-profile-gold-grad);
    }

    .pcg-profile-view .toggle-switch input:checked + .slider:before {
        transform: translateX(22px);
    }

    .pcg-profile-view .privacy-label {
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--pcg-profile-light-gray);
    }

    .pcg-profile-view .toggle-switch input:checked ~ .privacy-label {
        color: var(--pcg-profile-pure-black);
    }

    .pcg-profile-view .label-text {
        color: var(--pcg-profile-deep-gray);
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
        margin-bottom: 0.1rem;
        display: block;
    }

    @media (max-width: 768px) {
        .pcg-profile-view .tab-btn {
            border-left: none;
            border-bottom: 4px solid transparent;
            flex: 1;
            text-align: center;
            font-size: 0.75rem;
            padding: 1rem 0.5rem;
        }
        .pcg-profile-view .tab-btn.active {
            border-bottom: 4px solid;
            border-image: var(--pcg-profile-gold-grad) 1;
        }
    }

    #pcg-profile-status-msg {
        border-radius: var(--pcg-profile-border-radius);
        background: var(--pcg-profile-copper-grad);
        color: white;
        padding: 1.25rem;
        margin-top: 1.5rem;
    }
    
    /* Utility classes to replace Tailwind since we are in a WP plugin environment */
    .pcg-profile-view .flex { display: flex; }
    .pcg-profile-view .flex-col { flex-direction: column; }
    .pcg-profile-view .items-center { align-items: center; }
    .pcg-profile-view .justify-end { justify-content: flex-end; }
    .pcg-profile-view .gap-6 { gap: 1.5rem; }
    .pcg-profile-view .gap-8 { gap: 2rem; }
    .pcg-profile-view .mb-12 { margin-bottom: 3rem; }
    .pcg-profile-view .mb-6 { margin-bottom: 1.5rem; }
    .pcg-profile-view .mt-2 { margin-top: 0.5rem; }
    .pcg-profile-view .p-8 { padding: 2rem; }
    .pcg-profile-view .space-y-8 > * + * { margin-top: 2rem; }
    .pcg-profile-view .space-y-4 > * + * { margin-top: 1rem; }
    .pcg-profile-view .w-full { width: 100%; }
    .pcg-profile-view .hidden { display: none; }
    .pcg-profile-view .grid { display: grid; }
    .pcg-profile-view .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
    @media (min-width: 768px) {
        .pcg-profile-view .md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pcg-profile-view .md\:flex-row { flex-direction: row; }
        .pcg-profile-view .md\:flex-col { flex-direction: column; }
        .pcg-profile-view .md\:w-2\/5 { width: 40%; }
        .pcg-profile-view .md\:w-3\/5 { width: 60%; }
    }
</style>

<!-- Load Font Awesome if not present -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<div class="pcg-form-nav pcg-sales-nav">
    <div class="pcg-sales-nav-inner">
        <div class="pcg-nav-left">
            <span class="pcg-current-course-label"><?php _e('PERFIL', 'politeia-learning'); ?></span>
        </div>
        <div class="pcg-nav-right">
            <div class="pcg-segmented-control" id="pcg-profile-tabs">
                <div class="pcg-segment active" data-profile-tab="profile">
                    <?php _e('Profile', 'politeia-learning'); ?>
                </div>
                <div class="pcg-segment" data-profile-tab="portfolio">
                    <?php _e('Portfolio', 'politeia-learning'); ?>
                </div>
                <div class="pcg-segment" data-profile-tab="interests">
                    <?php _e('My Interest', 'politeia-learning'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="pcg-creator-section">
    <!-- Profile Settings Panel -->
    <div data-profile-panel="profile" class="pcg-profile-view">
        <div class="pcg-profile-inner">
            <div class="max-w-4xl mx-auto">
                <form id="pcg-profile-form" class="space-y-8">
                    <!-- Basic Information -->
                    <div class="form-card">
                        <div class="card-header-accent"></div>
                        <div class="p-8">
                            <div class="flex items-center mb-6">
                                <h2 class="section-title">
                                    <i class="fa-solid fa-id-card icon-gold"></i>
                                    Basic Information
                                </h2>
                                <label class="privacy-wrapper">
                                    <span class="privacy-label">Private</span>
                                    <div class="toggle-switch">
                                        <input type="checkbox" name="privacy_basic">
                                        <span class="slider"></span>
                                    </div>
                                </label>
                            </div>
                            <div class="flex gap-10 items-start">
                                <!-- Profile Photo inside block -->
                                <div class="profile-photo-container" style="width: 120px; height: 120px;">
                                    <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($user->display_name); ?>" class="profile-photo">
                                </div>
                                
                                <div class="flex-1 flex flex-col gap-6">
                                    <div>
                                        <span class="label-text">First Name</span>
                                        <input type="text" value="<?php echo esc_attr($first_name); ?>" class="input-field" style="font-size: 1.1rem; font-weight: 500;">
                                    </div>
                                    <div>
                                        <span class="label-text">Last Name</span>
                                        <input type="text" value="<?php echo esc_attr($last_name); ?>" class="input-field" style="font-size: 1.1rem; font-weight: 500;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Media -->
                    <div class="form-card">
                        <div class="card-header-accent"></div>
                        <div class="p-8">
                            <div class="flex items-center mb-6">
                                <h2 class="section-title">
                                    <i class="fa-solid fa-globe icon-gold"></i>
                                    Connectivity
                                </h2>
                                <label class="privacy-wrapper">
                                    <span class="privacy-label">Private</span>
                                    <div class="toggle-switch">
                                        <input type="checkbox" name="privacy_social">
                                        <span class="slider"></span>
                                    </div>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="relative flex items-end">
                                    <i class="fa-brands fa-x-twitter mb-3 icon-gold mr-4" style="margin-right: 1rem; margin-bottom: 0.75rem;"></i>
                                    <div class="flex-1">
                                        <span class="label-text">X Profile</span>
                                        <input type="url" placeholder="https://x.com/username" class="input-field">
                                    </div>
                                </div>
                                <div class="relative flex items-end">
                                    <i class="fa-brands fa-facebook mb-3 icon-gold mr-4" style="margin-right: 1rem; margin-bottom: 0.75rem;"></i>
                                    <div class="flex-1">
                                        <span class="label-text">Facebook Profile</span>
                                        <input type="url" placeholder="https://facebook.com/username" class="input-field">
                                    </div>
                                </div>
                                <div class="relative flex items-end">
                                    <i class="fa-brands fa-instagram mb-3 icon-gold mr-4" style="margin-right: 1rem; margin-bottom: 0.75rem;"></i>
                                    <div class="flex-1">
                                        <span class="label-text">Instagram Profile</span>
                                        <input type="url" placeholder="https://instagram.com/username" class="input-field">
                                    </div>
                                </div>
                                <div class="relative flex items-end">
                                    <i class="fa-brands fa-linkedin mb-3 icon-gold mr-4" style="margin-right: 1rem; margin-bottom: 0.75rem;"></i>
                                    <div class="flex-1">
                                        <span class="label-text">LinkedIn Profile</span>
                                        <input type="url" placeholder="https://linkedin.com/in/username" class="input-field">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Five Favorites Combined -->
                    <div class="form-card">
                        <div class="card-header-accent"></div>
                        <div class="flex flex-col md:flex-row">
                            <!-- Left Tabs -->
                            <div class="w-full md:w-2/5 bg-[#F9F9F9]" style="border-right: 1px solid #f0f0f0;">
                                <div class="p-6 hidden md:block" style="border-bottom: 1px solid #f0f0f0; padding: 1.5rem;">
                                    <h3 style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: #a1a1aa; margin: 0;">Curated Favorites</h3>
                                </div>
                                <nav id="pcg-profile-tabs-nav" class="flex flex-col">
                                    <button type="button" class="tab-btn active" data-target="nonFictionAuthors">
                                        Non-Fiction Authors
                                    </button>
                                    <button type="button" class="tab-btn" data-target="fictionAuthors">
                                        Fiction Authors
                                    </button>
                                    <button type="button" class="tab-btn" data-target="topBooks">
                                        Books of All Time
                                    </button>
                                </nav>
                            </div>

                            <!-- Right Content -->
                            <div class="w-full md:w-3/5 p-8 bg-white">
                                <div id="nonFictionAuthorsContent" class="tab-pane">
                                    <div class="flex items-center mb-6">
                                        <h3 style="font-size: 1.125rem; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fa-solid fa-feather-pointed icon-gold"></i>
                                            Non-Fiction
                                        </h3>
                                        <label class="privacy-wrapper">
                                            <span class="privacy-label">Private</span>
                                            <div class="toggle-switch">
                                                <input type="checkbox" name="privacy_nf">
                                                <span class="slider"></span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="space-y-4" id="nonFictionAuthors"></div>
                                </div>

                                <div id="fictionAuthorsContent" class="tab-pane hidden">
                                    <div class="flex items-center mb-6">
                                        <h3 style="font-size: 1.125rem; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fa-solid fa-book-open icon-gold"></i>
                                            Fiction
                                        </h3>
                                        <label class="privacy-wrapper">
                                            <span class="privacy-label">Private</span>
                                            <div class="toggle-switch">
                                                <input type="checkbox" name="privacy_fiction">
                                                <span class="slider"></span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="space-y-4" id="fictionAuthors"></div>
                                </div>

                                <div id="topBooksContent" class="tab-pane hidden">
                                    <div class="flex items-center mb-6">
                                        <h3 style="font-size: 1.125rem; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fa-solid fa-crown icon-gold"></i>
                                            Top 5 Books
                                        </h3>
                                        <label class="privacy-wrapper">
                                            <span class="privacy-label">Private</span>
                                            <div class="toggle-switch">
                                                <input type="checkbox" name="privacy_books">
                                                <span class="slider"></span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="space-y-4" id="topBooks"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-6 pt-4">
                        <button type="button" style="color: #a1a1aa; background: transparent; border: none; font-weight: 500; cursor: pointer;">Discard</button>
                        <button type="submit" class="gold-cta">
                            Update Profile
                        </button>
                    </div>
                </form>

                <div id="pcg-profile-status-msg" class="hidden text-center font-semibold"></div>
            </div>
        </div>
    </div>

    <!-- Portfolio Panel -->
    <div data-profile-panel="portfolio" class="pcg-profile-view" style="display:none;">
        <div class="pcg-profile-inner">
            <div class="max-w-4xl mx-auto">
                <div class="form-card p-8">
                    <h2 class="section-title mb-4">
                        <i class="fa-solid fa-briefcase icon-gold"></i>
                        <?php _e('Portfolio', 'politeia-learning'); ?>
                    </h2>
                    <p style="color: #737373;"><?php _e('Sección en construcción...', 'politeia-learning'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Interests Panel -->
    <div data-profile-panel="interests" class="pcg-profile-view" style="display:none;">
        <div class="pcg-profile-inner">
            <div class="max-w-4xl mx-auto">
                <div class="form-card p-8">
                    <h2 class="section-title mb-4">
                        <i class="fa-solid fa-heart icon-gold"></i>
                        <?php _e('My Interest', 'politeia-learning'); ?>
                    </h2>
                    <p style="color: #737373;"><?php _e('Sección en construcción...', 'politeia-learning'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function($) {
        function createListInputs(containerId, placeholderText) {
            const container = document.getElementById(containerId);
            if (!container) return;
            for (let i = 1; i <= 5; i++) {
                const div = document.createElement('div');
                div.className = 'author-list-item items-end flex gap-4';
                div.innerHTML = `
                    <span class="number-badge mb-1">${i}</span>
                    <div style="flex: 1;">
                        <input type="text" placeholder="${placeholderText} #${i}" class="input-field">
                    </div>
                `;
                container.appendChild(div);
            }
        }

        // Init
        createListInputs('nonFictionAuthors', 'Author name');
        createListInputs('fictionAuthors', 'Author name');
        createListInputs('topBooks', 'Book title');

        // Tabs
        const $tabs = $('#pcg-profile-tabs-nav .tab-btn');
        const $panes = $('.tab-pane');

        $tabs.on('click', function() {
            const target = $(this).data('target');
            $tabs.removeClass('active');
            $(this).addClass('active');
            $panes.addClass('hidden');
            $('#' + target + 'Content').removeClass('hidden');
        });

        // Form
        $('#pcg-profile-form').on('submit', function(e) {
            e.preventDefault();
            const $status = $('#pcg-profile-status-msg');
            $status.text("Profile & privacy settings updated.");
            $status.removeClass('hidden');
            
            $status[0].scrollIntoView({ behavior: 'smooth' });
            setTimeout(() => {
                $status.addClass('hidden');
            }, 4000);
        });

        // Main Profile Tabs Switching
        $('#pcg-profile-tabs .pcg-segment').on('click', function() {
            const tab = $(this).data('profile-tab');
            
            // UI
            $('#pcg-profile-tabs .pcg-segment').removeClass('active');
            $(this).addClass('active');
            
            // Panels
            $('[data-profile-panel]').hide();
            $(`[data-profile-panel="${tab}"]`).show();
            
            // Global event if needed
            window.dispatchEvent(new CustomEvent('pcg:profile-tab-changed', { detail: { tab } }));
        });
    })(jQuery);
</script>
