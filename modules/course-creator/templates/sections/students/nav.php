<?php
/**
 * Students Section - Navigation
 */
if (!defined('ABSPATH')) exit;
?>

<div class="pcg-form-nav pcg-sales-nav">
    <div class="pcg-sales-nav-inner">
        <div class="pcg-nav-left">
            <span class="pcg-current-course-label"><?php _e('ESTUDIANTES', 'politeia-learning'); ?></span>
        </div>
        <div class="pcg-nav-right">
            <div class="pcg-segmented-control" id="pcg-students-tabs">
                <div class="pcg-segment active" data-students-tab="general">
                    <?php _e('GENERAL', 'politeia-learning'); ?>
                </div>
                <div class="pcg-segment" data-students-tab="ranking">
                    <?php _e('RANKING', 'politeia-learning'); ?>
                </div>
                <div class="pcg-segment" data-students-tab="profile">
                    <?php _e('PROFILE', 'politeia-learning'); ?>
                </div>
            </div>
        </div>
    </div>
</div>
