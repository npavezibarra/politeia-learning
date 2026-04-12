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
$portfolio_manager = PL_Member_Profile_Portfolio_Manager::get_instance();
$portfolio_settings = $portfolio_manager->get_settings($user->ID);

$pl_membership_amount = 0;
$pl_membership_currency = 'CLP';
if (class_exists('Politeia_PPS_Subscription_Engine') && method_exists('Politeia_PPS_Subscription_Engine', 'get_creator_tier_by_slug')) {
    $tier = Politeia_PPS_Subscription_Engine::get_creator_tier_by_slug($user->ID, 'monthly');
    if (is_array($tier)) {
        $pl_membership_amount = (int) ($tier['amount_minor'] ?? 0);
        $pl_membership_currency = strtoupper((string) ($tier['currency'] ?? 'CLP'));
    }
} else {
    $pl_membership_amount = (int) get_user_meta($user->ID, 'politeia_membership_monthly_amount', true);
}

$pl_membership_amount_display = $pl_membership_amount > 0 ? number_format($pl_membership_amount, 0, ',', '.') : '';
$pl_membership_notice = isset($_GET['pl_membership_notice']) ? sanitize_text_field(wp_unslash($_GET['pl_membership_notice'])) : '';
$pl_membership_error = isset($_GET['pl_membership_error']) ? sanitize_text_field(wp_unslash($_GET['pl_membership_error'])) : '';

$pl_membership_label = (strpos(get_locale(), 'es') !== false) ? __('Membresía', 'politeia-learning') : __('Membership', 'politeia-learning');
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
        width: 100%;
        max-width: 1180px;
        margin: 0;
        background-color: #FAFAFB;
        font-family: 'Poppins', sans-serif;
        color: var(--pcg-profile-pure-black);
        padding: 30px 20px;
        box-sizing: border-box;
    }

    .pcg-profile-inner {
        width: 100%;
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
        width: 100px;
        height: 100px;
        border-radius: 50%;
        position: relative;
        background: var(--pcg-profile-gold-grad) border-box;
        border: 2px solid transparent;
        padding: 2px;
        flex-shrink: 0;
        cursor: pointer;
        overflow: hidden;
    }

    .pcg-profile-view .profile-photo-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 2;
        border-radius: 50%;
    }

    .pcg-profile-view .profile-photo-overlay i {
        color: white;
        font-size: 24px;
    }

    .pcg-profile-view .profile-photo-container:hover .profile-photo-overlay {
        opacity: 1;
    }

    .pcg-profile-view .profile-photo {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        background: #eee;
        transition: filter 0.3s ease;
    }

    .pcg-profile-view .profile-photo-container:hover .profile-photo {
        filter: blur(1px);
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

    /* Portfolio Specific Styles */
    .pcg-portfolio-section {
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 2rem;
        margin-bottom: 2rem;
    }
    .pcg-portfolio-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }
    .pcg-select-all-wrapper {
        margin-left: 1rem;
        display: flex;
        align-items: center;
        gap: 6px;
        font-family: 'Poppins', sans-serif;
        font-size: 10px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #a3a3a3;
        cursor: pointer;
        user-select: none;
    }
    .pcg-select-all-wrapper input {
        width: 12px;
        height: 12px;
        cursor: pointer;
    }
    .pcg-item-selection {
        background: #f9f9f9;
        padding: 1rem;
        border-radius: 6px;
        margin-top: 1rem;
        font-size: 12px !important;
    }
    .pcg-item-selection * {
        font-size: 12px !important;
    }
    .pcg-selected-items {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    .pcg-tag-pill {
        background: white;
        border: 1px solid #e2e8f0;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px !important;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .pcg-tag-remove {
        cursor: pointer;
        color: #ef4444;
        font-weight: bold;
    }
    .pcg-autocomplete-results {
        position: absolute;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        z-index: 100;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .pcg-autocomplete-item {
        padding: 8px 12px;
        cursor: pointer;
        font-size: 0.85rem;
    }
    .pcg-autocomplete-item:hover {
        background: #f1f5f9;
    }
    .pcg-search-wrapper {
        position: relative;
    }
    
    /* Grid Selection Styles */
    .pcg-item-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-top: 1rem;
    }
    .pcg-grid-item {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        font-size: 12px !important;
        padding: 6px 0;
        line-height: 1.4;
    }
    .pcg-grid-item label {
        font-size: 12px !important;
        cursor: pointer;
    }
    .pcg-grid-item input[type="checkbox"] {
        cursor: pointer;
        margin-top: 2px;
    }
    .pcg-pagination {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.75rem;
        margin-top: 1rem;
        font-size: 12px !important;
    }
    .pcg-pagination button {
        background: transparent !important;
        border: none !important;
        padding: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #404040 !important;
        box-shadow: none !important;
    }
    .pcg-pagination button:hover {
        background: transparent !important;
    }
    .pcg-pagination button:disabled {
        opacity: 0.2;
        cursor: not-allowed;
    }
    .pcg-pagination .pcg-page-info {
        color: #737373;
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
                <div class="pcg-segment" data-profile-tab="membership">
                    <?php echo esc_html($pl_membership_label); ?>
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
            <form id="pcg-profile-form" class="space-y-8">
                    <!-- Profile Details & Connectivity Combined Row -->
                    <div class="form-card">
                        <div class="card-header-accent"></div>
                        <div class="flex flex-col md:flex-row" style="display: flex;">
                            <!-- Left Block: 33.33% Basic Info -->
                            <div class="p-8" style="width: 33.333%; flex: 0 0 33.333%; border-right: 1px solid #f0f0f0;">
                                <div class="flex flex-col gap-y-[32px] items-start">
                                    <div class="profile-photo-container mx-auto" style="width: 100px; height: 100px; margin-bottom: 24px;">
                                        <div class="profile-photo-overlay">
                                            <i class="fa-solid fa-camera"></i>
                                        </div>
                                        <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($user->display_name); ?>" class="profile-photo">
                                    </div>
                                    
                                    <div class="flex flex-col w-full">
                                        <div class="flex items-center" style="height: 20px;">
                                            <span class="label-text">First Name</span>
                                        </div>
                                        <div class="flex-1">
                                            <input type="text" value="<?php echo esc_attr($first_name); ?>" class="input-field">
                                        </div>
                                    </div>

                                    <div class="flex flex-col w-full">
                                        <div class="flex items-center" style="height: 20px;">
                                            <span class="label-text">Last Name</span>
                                        </div>
                                        <div class="flex-1">
                                            <input type="text" value="<?php echo esc_attr($last_name); ?>" class="input-field">
                                        </div>
                                    </div>
                                </div>
                            </div>

                             <!-- Right Block: 66.66% Connectivity -->
                            <div class="p-8" style="width: 66.666%; flex: 0 0 66.666%;">
                                <div class="flex items-center justify-between mb-8">
                                    <h2 class="section-title" style="margin: 0;">
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

                                <!-- 2 Columns, 3 Fields each -->
                                <!-- Spacing tuned to align with First/Last Name fields -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12" style="display: grid; row-gap: 32px; margin-top: 91px;">
                                    
                                    <!-- Left Column -->
                                    <div class="flex flex-col gap-y-[48px]">
                                        <!-- Email -->
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-2" style="height: 20px;">
                                                <span class="label-text" style="margin: 0;">E-mail address</span>
                                                <span style="font-size: 10px; color: #a1a1aa; font-weight: 400; text-transform: none; letter-spacing: normal;">(* Confirmation required if changed)</span>
                                            </div>
                                            <div class="flex-1">
                                                <input type="email" value="<?php echo esc_attr($user->user_email); ?>" class="input-field" placeholder="your@email.com">
                                            </div>
                                        </div>

                                        <!-- X -->
                                        <div class="flex flex-col">
                                            <div class="flex items-center" style="height: 20px;">
                                                <span class="label-text">X Profile</span>
                                            </div>
                                            <div class="flex-1">
                                                <input type="url" placeholder="https://x.com/username" class="input-field">
                                            </div>
                                        </div>

                                        <!-- Facebook -->
                                        <div class="flex flex-col">
                                            <div class="flex items-center" style="height: 20px;">
                                                <span class="label-text">Facebook Profile</span>
                                            </div>
                                            <div class="flex-1">
                                                <input type="url" placeholder="https://facebook.com/username" class="input-field">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Column -->
                                    <div class="flex flex-col gap-y-[48px]">
                                        <!-- Personal Website -->
                                        <div class="flex flex-col">
                                            <div class="flex items-center" style="height: 20px;">
                                                <span class="label-text">Personal Website</span>
                                            </div>
                                            <div class="flex-1">
                                                <input type="url" value="<?php echo esc_url($user->user_url); ?>" class="input-field" placeholder="https://yourwebsite.com">
                                            </div>
                                        </div>

                                        <!-- Instagram -->
                                        <div class="flex flex-col">
                                            <div class="flex items-center" style="height: 20px;">
                                                <span class="label-text">Instagram Profile</span>
                                            </div>
                                            <div class="flex-1">
                                                <input type="url" placeholder="https://instagram.com/username" class="input-field">
                                            </div>
                                        </div>

                                        <!-- LinkedIn -->
                                        <div class="flex flex-col">
                                            <div class="flex items-center" style="height: 20px;">
                                                <span class="label-text">LinkedIn Profile</span>
                                            </div>
                                            <div class="flex-1">
                                                <input type="url" placeholder="https://linkedin.com/in/username" class="input-field">
                                            </div>
                                        </div>
                                    </div>
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

    <!-- Portfolio Panel -->
    <div data-profile-panel="portfolio" class="pcg-profile-view" style="display:none;">
        <div class="pcg-profile-inner">
            <div class="form-card p-8">
                    <h2 class="section-title mb-6">
                        <i class="fa-solid fa-briefcase icon-gold"></i>
                        <?php _e('Portfolio Management', 'politeia-learning'); ?>
                    </h2>
                    
                    <p class="mb-8" style="color: #737373; font-size: 0.9rem;">
                        <?php _e('Choose which sections and specific works are visible on your public curiosity profile.', 'politeia-learning'); ?>
                    </p>

                    <div class="pcg-portfolio-sections">
                        <?php 
                        $sections = [
                            'courses' => [
                                'label' => __('Cursos', 'politeia-learning'),
                                'type' => 'courses',
                                'icon' => 'fa-graduation-cap'
                            ],
                            'writings' => [
                                'label' => __('Writings', 'politeia-learning'),
                                'type' => 'writings',
                                'icon' => 'fa-pen-nib'
                            ],
                            'specializations' => [
                                'label' => __('Especializaciones', 'politeia-learning'),
                                'type' => 'specializations',
                                'icon' => 'fa-award'
                            ]
                        ];

                        foreach ($sections as $id => $section_data):
                            $settings = $portfolio_settings[$id] ?? (object)[
                                'is_private' => 0,
                                'visibility_mode' => 'selected',
                                'selected_ids' => []
                            ];
                        ?>
                            <div class="pcg-portfolio-section" data-section="<?php echo $id; ?>">
                                <div class="flex items-center mb-4">
                                    <h3 style="font-size: 1rem; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fa-solid <?php echo $section_data['icon']; ?> icon-gold"></i>
                                        <?php echo $section_data['label']; ?>
                                    </h3>

                                    <div class="pcg-select-all-container <?php echo $settings->is_private ? 'hidden' : ''; ?>">
                                        <label class="pcg-select-all-wrapper">
                                            <input type="checkbox" class="pcg-select-all-toggle">
                                            <span><?php _e('ALL', 'politeia-learning'); ?></span>
                                        </label>
                                    </div>
                                    
                                    <label class="privacy-wrapper">
                                        <span class="privacy-label"><?php _e('Private', 'politeia-learning'); ?></span>
                                        <div class="toggle-switch">
                                            <input type="checkbox" name="portfolio[<?php echo $id; ?>][is_private]" 
                                                   class="pcg-portfolio-toggle" <?php checked($settings->is_private, 1); ?>>
                                            <span class="slider"></span>
                                        </div>
                                    </label>
                                </div>

                                <div class="pcg-portfolio-controls <?php echo $settings->is_private ? 'hidden' : ''; ?>">
                                     <div class="pcg-item-selection">
                                        <div class="pcg-item-grid-container" data-page="1">
                                            <div class="pcg-item-grid" id="grid-<?php echo $id; ?>">
                                                <!-- Dynamic Content -->
                                                <div class="col-span-2 text-center py-4 text-neutral-400">
                                                    <i class="fa-solid fa-spinner fa-spin"></i> <?php _e('Cargando...', 'politeia-learning'); ?>
                                                </div>
                                            </div>
                                             <div class="pcg-pagination flex justify-end items-center" id="pagination-<?php echo $id; ?>">
                                                 <button type="button" class="pcg-prev-page" disabled><i class="fa-solid fa-chevron-left" style="font-size: 14px; display: block;"></i></button>
                                                 <span class="pcg-page-info mx-2" style="font-weight: 500;">1 / 1</span>
                                                 <button type="button" class="pcg-next-page" disabled><i class="fa-solid fa-chevron-right" style="font-size: 14px; display: block;"></i></button>
                                             </div>
                                             
                                             <!-- Hidden storage for selection logic -->
                                             <div class="pcg-selected-items-data hidden">
                                                 <?php 
                                                 if (!empty($settings->selected_ids)) {
                                                     foreach ($settings->selected_ids as $item_id) {
                                                         echo '<div class="pcg-tag-pill" data-id="' . $item_id . '"></div>';
                                                     }
                                                 }
                                                 ?>
                                             </div>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex justify-end mt-8">
                         <button type="button" id="pcg-save-portfolio" class="gold-cta">
                            <?php _e('Save Portfolio Settings', 'politeia-learning'); ?>
                        </button>
                    </div>
                </div>
        </div>
    </div>

    <!-- Membership Panel -->
    <div data-profile-panel="membership" class="pcg-profile-view" style="display:none;">
        <div class="pcg-profile-inner">
            <div class="form-card p-8">
                <h2 class="section-title mb-6">
                    <i class="fa-solid fa-coins icon-gold"></i>
                    <?php echo esc_html($pl_membership_label); ?>
                </h2>

                <p class="mb-8" style="color: #737373; font-size: 0.9rem;">
                    <?php _e('Define el monto mensual para que otros usuarios puedan suscribirse a tu contenido exclusivo. Por ahora solo existe un tier mensual.', 'politeia-learning'); ?>
                </p>

                <?php if ($pl_membership_notice === 'saved') : ?>
                    <div style="padding:12px 14px;border-radius:6px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;margin-bottom:16px;font-size:13px;">
                        <?php _e('Guardado.', 'politeia-learning'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($pl_membership_error !== '') : ?>
                    <div style="padding:12px 14px;border-radius:6px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;margin-bottom:16px;font-size:13px;">
                        <?php echo esc_html($pl_membership_error); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-8">
                    <input type="hidden" name="action" value="pl_cc_save_membership_tier" />
                    <input type="hidden" name="user_slug" value="<?php echo esc_attr($user_slug); ?>" />
                    <?php wp_nonce_field('pl_cc_membership_tier', 'pl_cc_membership_tier_nonce'); ?>

                    <div class="grid grid-cols-1 md:grid-cols-2" style="display:grid; gap:32px;">
                        <div class="flex flex-col">
                            <div class="flex items-center" style="height: 20px;">
                                <span class="label-text"><?php _e('Monto mensual', 'politeia-learning'); ?> (<?php echo esc_html($pl_membership_currency); ?>)</span>
                            </div>
                            <div class="flex-1">
                                <input
                                    type="text"
                                    name="monthly_amount"
                                    value="<?php echo esc_attr($pl_membership_amount_display); ?>"
                                    class="input-field"
                                    inputmode="numeric"
                                    placeholder="5000"
                                    autocomplete="off"
                                />
                                <div style="margin-top:10px;color:#a1a1aa;font-size:12px;">
                                    <?php _e('Periodo: mensual (fijo).', 'politeia-learning'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-6 pt-6">
                        <button type="submit" class="gold-cta"><?php _e('Guardar', 'politeia-learning'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Interests Panel -->
    <div data-profile-panel="interests" class="pcg-profile-view" style="display:none;">
        <div class="pcg-profile-inner">
            <!-- Curated Favorites Moved to My Interest -->
            <form id="pcg-interests-form">
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
                                        <?php _e('Top 5 Books', 'politeia-learning'); ?>
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

                <!-- Actions for Interests -->
                <div class="flex items-center justify-end gap-6 pt-6">
                    <button type="submit" class="gold-cta">
                        <?php _e('Update Interests', 'politeia-learning'); ?>
                    </button>
                </div>
            </form>
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

        // Profile Photo Upload
        $(document).on('click', '.profile-photo-container', function() {
            if (typeof PL_Cropper === 'undefined') {
                console.error('PL_Cropper not found');
                return;
            }

            PL_Cropper.open({
                width: pcgCreatorData.avatarFullWidth || 300,
                height: pcgCreatorData.avatarFullHeight || 300,
                circleMask: true,
                title: '<?php _e('Cambiar foto de perfil', 'politeia-learning'); ?>',
                onSave: function(dataUrl) {
                    // Check size approx (Base64)
                    const stringLength = dataUrl.length - 'data:image/png;base64,'.length;
                    const sizeInBytes = 4 * Math.ceil((stringLength / 3)) * 0.5624896334383812; // Adjusted factor for precision or just use a simpler check
                    const sizeInKb = sizeInBytes / 1024;
                    
                    // console.log('Size: ' + sizeInKb + 'kb');

                    if (sizeInKb > 300) {
                        alert('<?php _e('La imagen es demasiado pesada. El máximo permitido es 300kb.', 'politeia-learning'); ?>');
                        // We could try to re-compress but for now just alert.
                        // return;
                    }

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

        $('#pcg-interests-form').on('submit', function(e) {
            e.preventDefault();
            const $status = $('#pcg-profile-status-msg');
            $status.text("Interests updated successfully.");
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

            // Lazy load portfolio items if this is the first time visiting the tab
            if (tab === 'portfolio' && !window.pcgPortfolioLoaded) {
                initPortfolioBulkLoad();
            }
            
            // Global event if needed
            window.dispatchEvent(new CustomEvent('pcg:profile-tab-changed', { detail: { tab } }));
        });

        // Open tab from URL (?profile_tab=membership)
        try {
            const initialTab = new URLSearchParams(window.location.search).get('profile_tab');
            if (initialTab) {
                const $initial = $(`#pcg-profile-tabs .pcg-segment[data-profile-tab="${initialTab}"]`);
                if ($initial.length) {
                    $initial.trigger('click');
                }
            }
        } catch (e) {}

        // Portfolio Logic
        const portfolioNonce = '<?php echo wp_create_nonce("pl_portfolio_nonce"); ?>';

        $('.pcg-portfolio-toggle').on('change', function() {
            const $section = $(this).closest('.pcg-portfolio-section');
            const isPrivate = $(this).is(':checked');
            $section.find('.pcg-portfolio-controls').toggleClass('hidden', isPrivate);
            $section.find('.pcg-select-all-container').toggleClass('hidden', isPrivate);
        });

        $(document).on('change', '.pcg-select-all-toggle', function() {
            const $toggle = $(this);
            const isChecked = $toggle.is(':checked');
            const $section = $toggle.closest('.pcg-portfolio-section');
            const $checkboxes = $section.find('.pcg-item-checkbox');
            
            $checkboxes.each(function() {
                if ($(this).is(':checked') !== isChecked) {
                    $(this).prop('checked', isChecked).trigger('change');
                }
            });
        });

        // Mode switch is removed, we default to curated (selected) items if not private.

        function loadPortfolioItems(sectionId, page) {
            const $section = $(`.pcg-portfolio-section[data-section="${sectionId}"]`);
            const $grid = $section.find('.pcg-item-grid');
            const $pagination = $section.find('.pcg-pagination');
            const type = sectionId;

            // Show spinner
            $grid.html('<div class="col-span-2 text-center py-4 text-neutral-400"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</div>');

            $.ajax({
                url: pcgCreatorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pl_get_portfolio_items',
                    nonce: portfolioNonce,
                    type: type,
                    paged: page
                },
                success: function(response) {
                    if (response.success) {
                        renderGrid($grid, response.data.items, sectionId);
                        renderPagination($pagination, response.data, sectionId);
                    } else {
                        $grid.html('<div class="col-span-2 text-center py-4 text-neutral-400">Error loading page.</div>');
                    }
                },
                error: function() {
                    $grid.html('<div class="col-span-2 text-center py-4 text-red-400">Connection error.</div>');
                }
            });
        }

        function initPortfolioBulkLoad() {
            if (typeof pcgCreatorData === 'undefined' || !pcgCreatorData.ajaxUrl) {
                console.error('Portfolio Error: Creator data not found.');
                $('.pcg-item-grid').html('<div class="col-span-2 text-center py-4 text-neutral-400">Error: Configuration not loaded.</div>');
                return;
            }

            const sectionsToLoad = [];
            $('.pcg-portfolio-section').each(function() {
                const $sec = $(this);
                if (!$sec.find('.pcg-portfolio-toggle').is(':checked')) {
                    sectionsToLoad.push($sec.data('section'));
                }
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
                    nonce: portfolioNonce,
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
                        });
                    } else {
                         $('.pcg-item-grid').html('<div class="col-span-2 text-center py-4 text-neutral-400">Could not load items. Please refresh.</div>');
                    }
                },
                error: function() {
                    $('.pcg-item-grid').html('<div class="col-span-2 text-center py-4 text-red-400">Connection error. Please try again.</div>');
                },
                complete: function() {
                    window.pcgPortfolioLoaded = true;
                }
            });
        }

        function renderGrid($grid, items, sectionId) {
            const $section = $(`.pcg-portfolio-section[data-section="${sectionId}"]`);
            const selectedIds = [];
            $section.find('.pcg-tag-pill').each(function() {
                selectedIds.push(parseInt($(this).data('id')));
            });

            if (items.length === 0) {
                $grid.html('<div class="col-span-2 text-center py-4 text-neutral-400">No hay elementos disponibles.</div>');
                return;
            }

            let html = '';
            let allChecked = true;
            items.forEach(item => {
                const isChecked = selectedIds.includes(item.id);
                if (!isChecked) allChecked = false;
                
                html += `
                    <div class="pcg-grid-item">
                        <input type="checkbox" id="item-${item.id}" value="${item.id}" data-title="${item.title}" ${isChecked ? 'checked' : ''} class="pcg-item-checkbox">
                        <label for="item-${item.id}" title="${item.title}">${item.title}</label>
                    </div>
                `;
            });
            $grid.html(html);
            $section.find('.pcg-select-all-toggle').prop('checked', allChecked);
        }

        function renderPagination($pagination, data, sectionId) {
            $pagination.find('.pcg-page-info').text(`${data.current_page} / ${data.total_pages}`);
            $pagination.find('.pcg-prev-page').prop('disabled', data.current_page <= 1);
            $pagination.find('.pcg-next-page').prop('disabled', data.current_page >= data.total_pages);
            $pagination.closest('.pcg-item-grid-container').data('page', data.current_page);
        }

        $(document).on('click', '.pcg-prev-page', function() {
            const $section = $(this).closest('.pcg-portfolio-section');
            const sectionId = $section.data('section');
            const currentPage = parseInt($section.find('.pcg-item-grid-container').data('page'));
            loadPortfolioItems(sectionId, currentPage - 1);
        });

        $(document).on('click', '.pcg-next-page', function() {
            const $section = $(this).closest('.pcg-portfolio-section');
            const sectionId = $section.data('section');
            const currentPage = parseInt($section.find('.pcg-item-grid-container').data('page'));
            loadPortfolioItems(sectionId, currentPage + 1);
        });

        $(document).on('change', '.pcg-item-checkbox', function() {
            const $cb = $(this);
            const id = parseInt($cb.val());
            const $section = $cb.closest('.pcg-portfolio-section');
            const $container = $section.find('.pcg-selected-items-data');

            if ($cb.is(':checked')) {
                if ($container.find(`[data-id="${id}"]`).length === 0) {
                    $container.append(`<div class="pcg-tag-pill" data-id="${id}"></div>`);
                }
            } else {
                $container.find(`[data-id="${id}"]`).remove();
            }

            // Sync 'ALL' checkbox
            const $allCbs = $section.find('.pcg-item-checkbox');
            const $checkedCbs = $section.find('.pcg-item-checkbox:checked');
            $section.find('.pcg-select-all-toggle').prop('checked', $allCbs.length > 0 && $allCbs.length === $checkedCbs.length);
        });

        // Initialize state markers but defer loading
        window.pcgPortfolioLoaded = false;

        // Check if portfolio is initial tab and trigger load
        $(document).ready(function() {
            if ($('#pcg-profile-tabs .pcg-segment.active').data('profile-tab') === 'portfolio') {
                initPortfolioBulkLoad();
            }
        });

        $(document).on('click', '.pcg-tag-remove', function() {
            const $tag = $(this).parent();
            const id = $tag.data('id');
            const $section = $(this).closest('.pcg-portfolio-section');
            
            // Uncheck in grid if present
            $section.find(`.pcg-item-checkbox[value="${id}"]`).prop('checked', false);
            
            $tag.remove();
        });

        // Save Portfolio
        $('#pcg-save-portfolio').on('click', function() {
            const $btn = $(this);
            const $status = $('#pcg-profile-status-msg');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Saving...');

            const sections = [];
            $('.pcg-portfolio-section').each(function() {
                const $sec = $(this);
                const sectionId = $sec.data('section');
                const isPrivate = $sec.find('.pcg-portfolio-toggle').is(':checked') ? 1 : 0;
                const mode = $sec.find('.pcg-portfolio-mode:checked').val();
                const selectedIds = [];
                $sec.find('.pcg-tag-pill').each(function() {
                    selectedIds.push($(this).data('id'));
                });

                sections.push({
                    section_id: sectionId,
                    is_private: isPrivate,
                    visibility_mode: 'selected', // Always curated
                    selected_ids: selectedIds
                });
            });

            // We save each section (could be optimized to bulk, but manager expects one)
            let completed = 0;
            let success = true;

            sections.forEach(sec => {
                $.ajax({
                    url: pcgCreatorData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pl_save_portfolio_settings',
                        nonce: portfolioNonce,
                        ...sec
                    },
                    complete: function() {
                        completed++;
                        if (completed === sections.length) {
                            $btn.prop('disabled', false).text(originalText);
                            $status.text(success ? "Portfolio settings updated successfully." : "Error saving some settings.");
                            $status.removeClass('hidden');
                            $status[0].scrollIntoView({ behavior: 'smooth' });
                            setTimeout(() => $status.addClass('hidden'), 4000);
                        }
                    },
                    error: function() {
                        success = false;
                    }
                });
            });
        });
    })(jQuery);
</script>
