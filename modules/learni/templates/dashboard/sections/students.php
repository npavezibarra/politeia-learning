<?php
/**
 * Students Section - Main Template
 * 
 * Orchestrator file for the students analytics and profile management.
 * Modular architecture native to Learni.
 */
if (!defined('ABSPATH')) exit;
?>

<div class="pcg-students-section">
    <?php 
    // Navigation 
    include __DIR__ . '/students/nav.php'; 
    ?>

    <div class="pcg-creator-section">
        <?php
        // Panels
        include __DIR__ . '/students/panel-general.php';
        include __DIR__ . '/students/panel-ranking.php';
        
        // Profile Section (List & Detail)
        echo '<div data-students-panel="profile" style="display:none;">';
            include __DIR__ . '/students/profile-index.php';
            include __DIR__ . '/students/profile-detail.php';
        echo '</div>';
        ?>
    </div>
</div>
