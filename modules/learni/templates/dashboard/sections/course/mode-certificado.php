<?php
/**
 * Course Creator - Certificate Mode
 */
if (!defined('ABSPATH')) exit;
?>

<div id="pcg-mode-certificado" class="pcg-mode-content" <?php echo $pcg_active_segment !== 'certificado' ? 'style="display:none;"' : ''; ?>>
    <div class="pcg-meta-editor__grid pcg-certificate-editor__grid">
        <div class="pcg-meta-editor__left">
            <div class="pcg-tabs-card">
                <div class="pcg-tabs-card__body">
                    <div class="pcg-certificate-editor">
                        <div class="pcg-certificate-editor__section">
                            <div class="pcg-certificate-fields">
                                <div class="pcg-certificate-field">
                                    <div class="pcg-certificate-field__label"><?php _e('Title of certificate', 'politeia-learning'); ?></div>
                                    <input type="text" id="pcg-certificate-title" class="pcg-modern-input" placeholder="<?php esc_attr_e('Certificado Finalización', 'politeia-learning'); ?>">
                                </div>

                                <div class="pcg-certificate-field">
                                    <div class="pcg-certificate-field__label"><?php _e('Congratulations paragraph (max 50 words)', 'politeia-learning'); ?></div>
                                    <div class="pcg-certificate-shortcodes">
                                        <?php _e('Shortcodes:', 'politeia-learning'); ?>
                                        <code>[display_full_name]</code>,
                                        <code>[first_name]</code>,
                                        <code>[course_name]</code>,
                                        <code>[date_start]</code>,
                                        <code>[date_end]</code>
                                    </div>
                                    <textarea id="pcg-certificate-congrats" class="pcg-modern-textarea pcg-certificate-textarea"
                                        placeholder="<?php esc_attr_e('Write the certificate paragraph...', 'politeia-learning'); ?>"></textarea>
                                    <span class="pcg-word-count" id="pcg-cert-word-count">0 / 50 <?php _e('words', 'politeia-learning'); ?></span>
                                </div>

                                <div class="pcg-certificate-row">
                                    <div class="pcg-media-card pcg-media-card--certificate-logo">
                                        <div class="pcg-media-card__meta">
                                            <span class="pcg-media-card__label"><?php _e('Logo (png/jpg)', 'politeia-learning'); ?></span>
                                        </div>

                                        <div id="pcg-certificate-logo-preview" class="pcg-media-card__preview" style="display:none;">
                                            <img src="" alt="">
                                            <button type="button" id="pcg-remove-certificate-logo" class="pcg-media-card__remove">
                                                <?php _e('Remove', 'politeia-learning'); ?>
                                            </button>
                                        </div>
                                        <div class="pcg-media-card__empty" role="button" tabindex="0" data-upload="certificate_logo">
                                            <div class="pcg-media-card__empty-inner">
                                                <div class="pcg-media-card__empty-title"><?php _e('Sin imagen', 'politeia-learning'); ?></div>
                                                <div class="pcg-media-card__empty-action">
                                                    <svg class="pcg-media-card__empty-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                        <polyline points="17 8 12 3 7 8"></polyline>
                                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                                    </svg>
                                                    <span><?php _e('Click para subir imagen', 'politeia-learning'); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pcg-media-card pcg-media-card--certificate-signature">
                                        <div class="pcg-media-card__meta">
                                            <span class="pcg-media-card__label"><?php _e('Signature scan (png/jpg)', 'politeia-learning'); ?></span>
                                        </div>

                                        <div id="pcg-certificate-signature-preview" class="pcg-media-card__preview" style="display:none;">
                                            <img src="" alt="">
                                            <button type="button" id="pcg-remove-certificate-signature" class="pcg-media-card__remove">
                                                <?php _e('Remove', 'politeia-learning'); ?>
                                            </button>
                                        </div>
                                        <div class="pcg-media-card__empty" role="button" tabindex="0" data-upload="certificate_signature">
                                            <div class="pcg-media-card__empty-inner">
                                                <div class="pcg-media-card__empty-title"><?php _e('Sin imagen', 'politeia-learning'); ?></div>
                                                <div class="pcg-media-card__empty-action">
                                                    <svg class="pcg-media-card__empty-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                        <polyline points="17 8 12 3 7 8"></polyline>
                                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                                    </svg>
                                                    <span><?php _e('Click para subir imagen', 'politeia-learning'); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="pcg-certificate-field pcg-certificate-field--compact">
                                            <div class="pcg-certificate-field__label"><?php _e('Signature label', 'politeia-learning'); ?></div>
                                            <input type="text" id="pcg-cert-signature-label" class="pcg-modern-input"
                                                placeholder="<?php esc_attr_e('Signature label...', 'politeia-learning'); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php 
        $sidebar_id_suffix = '-cert';
        $sidebar_actions_slot = true;
        $sidebar_checklist_slot = true;
        include __DIR__ . '/sidebar.php'; 
        ?>
    </div>
</div>
