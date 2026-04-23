<?php if (!defined('ABSPATH')) exit; ?>
<style>
    .material-symbols-outlined {
        font-family: 'Material Symbols Outlined' !important;
        font-weight: normal;
        font-style: normal;
        font-size: 24px;
        line-height: 1;
        letter-spacing: normal;
        text-transform: none;
        display: inline-block;
        white-space: nowrap;
        word-wrap: normal;
        direction: ltr;
        -webkit-font-smoothing: antialiased;
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    /* 
       THEME OVERRIDES
       The active theme may have nested containers.
       We "break out" so the dashboard can occupy the expected space.
    */
    #primary, #primary .entry-content {
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Fix Tailwind/Theme Container Conflict */
    .container {
        max-width: <?php echo $pl_profile_is_fullwidth ? 'none' : esc_html($pl_container_max_width); ?> !important;
        margin-left: auto !important;
        margin-right: auto !important;
        padding-left: <?php echo $pl_profile_is_fullwidth ? '0' : '20px'; ?> !important;
        padding-right: <?php echo $pl_profile_is_fullwidth ? '0' : '20px'; ?> !important;
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
        margin-left: auto;
        margin-right: auto;
        <?php if (!$pl_profile_is_fullwidth) : ?>
        max-width: var(--wp--style--global--wide-size);
        <?php else : ?>
        max-width: none;
        <?php endif; ?>
    }

	    /* Intentionally no external community/profile UI overrides. */
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
