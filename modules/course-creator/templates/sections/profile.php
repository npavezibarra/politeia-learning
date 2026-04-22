<?php
/**
 * Profile Creator - Main Template
 * 
 * Orchestrator file that handles identity, portfolio and membership panels.
 */
if (!defined('ABSPATH')) exit;
?>

<div class="pcg-profile-section">
    <?php 
    // Standardized Dashboard Header (Title + Tabs)
    include __DIR__ . '/profile/nav.php'; 
    ?>

    <div class="pcg-profile-panels-wrap">
        <?php
        // Panels
        include __DIR__ . '/profile/panel-profile.php'; 
        include __DIR__ . '/profile/panel-portfolio.php'; 
        include __DIR__ . '/profile/panel-membership.php'; 
        ?>
    </div>

    <!-- Global Status Message (Used by all profile panels) -->
    <div id="pcg-profile-status-msg" class="hidden"></div>
</div>
