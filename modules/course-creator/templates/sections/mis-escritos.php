<?php
if (!defined('ABSPATH'))
    exit;
?>

<!-- Creation Form (Hidden Initially) -->
<div id="pcg-escritos-form-section" class="pcg-create-course-container" style="display:none;">
    <input type="hidden" id="pcg-current-escrito-id" value="0">

    <div class="pcg-mode-content pcg-escritos-editor-mode">

        <!-- Minimalist Toolbar -->
        <header class="pcg-escrito-toolbar">
            <div class="pcg-escrito-toolbar-inner">
	                <!-- Formatting Tools -->
	                <div class="pcg-escrito-tools-group">
	                    <button type="button" onclick="document.execCommand('bold', false, null)" class="pcg-toolbar-btn"
	                        title="<?php esc_attr_e('Bold', 'politeia-learning'); ?>">
	                        <span class="dashicons dashicons-editor-bold"></span>
	                    </button>
	                    <button type="button" onclick="document.execCommand('italic', false, null)" class="pcg-toolbar-btn"
	                        title="<?php esc_attr_e('Italic', 'politeia-learning'); ?>">
	                        <span class="dashicons dashicons-editor-italic"></span>
	                    </button>

                    <div class="pcg-escrito-divider"></div>

	                    <button type="button" onclick="document.execCommand('justifyLeft', false, null)"
	                        class="pcg-toolbar-btn" title="<?php esc_attr_e('Align Left', 'politeia-learning'); ?>">
	                        <span class="dashicons dashicons-editor-alignleft"></span>
	                    </button>
	                    <button type="button" onclick="document.execCommand('justifyCenter', false, null)"
	                        class="pcg-toolbar-btn" title="<?php esc_attr_e('Align Center', 'politeia-learning'); ?>">
	                        <span class="dashicons dashicons-editor-aligncenter"></span>
	                    </button>
	                    <button type="button" onclick="document.execCommand('justifyRight', false, null)"
	                        class="pcg-toolbar-btn" title="<?php esc_attr_e('Align Right', 'politeia-learning'); ?>">
	                        <span class="dashicons dashicons-editor-alignright"></span>
	                    </button>
	                    <button type="button" onclick="document.execCommand('justifyFull', false, null)"
	                        class="pcg-toolbar-btn" title="<?php esc_attr_e('Justify', 'politeia-learning'); ?>">
	                        <span class="dashicons dashicons-editor-justify"></span>
	                    </button>

                    <div class="pcg-escrito-divider"></div>

	                    <button type="button" onclick="document.execCommand('formatBlock', false, 'H1')"
	                        class="pcg-toolbar-btn" title="<?php esc_attr_e('Heading 1', 'politeia-learning'); ?>">
	                        <span class="dashicons dashicons-editor-spellcheck"></span>
	                    </button>

                    <!-- Headings Dropdown -->

                    <div class="pcg-escrito-dropdown">

	                        <button type="button" class="pcg-toolbar-btn pcg-dropdown-trigger"
	                            title="<?php esc_attr_e('Headings', 'politeia-learning'); ?>">

                            <span class="pcg-heading-icon">H</span>

                        </button>

                        <div class="pcg-dropdown-content">
	                            <button type="button" onclick="document.execCommand('formatBlock', false, 'H1')">
	                                <?php _e('H1 - Título', 'politeia-learning'); ?>
	                            </button>
	                            <button type="button" onclick="document.execCommand('formatBlock', false, 'H2')">
	                                <?php _e('H2 - Sección', 'politeia-learning'); ?>
	                            </button>
	                            <button type="button" onclick="document.execCommand('formatBlock', false, 'H3')">
	                                <?php _e('H3 - Subsección', 'politeia-learning'); ?>
	                            </button>
	                            <button type="button" onclick="document.execCommand('formatBlock', false, 'H4')">
	                                <?php _e('H4 - Detalle', 'politeia-learning'); ?>
	                            </button>
	                            <button type="button" onclick="document.execCommand('formatBlock', false, 'P')">
	                                <?php _e('P - Texto Regular', 'politeia-learning'); ?>
	                            </button>
	                        </div>
	                    </div>

	                    <button type="button" onclick="document.execCommand('formatBlock', false, 'P')"
	                        class="pcg-toolbar-btn" title="<?php esc_attr_e('Paragraph', 'politeia-learning'); ?>">
	                        <span class="pcg-txt-icon">TXT</span>
	                    </button>

                    <div class="pcg-escrito-divider"></div>

                    <!-- Return Button -->
                    <button type="button" id="pcg-btn-back-to-escritos" class="pcg-toolbar-btn"
                        title="<?php _e('Volver', 'politeia-learning'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                </div>

                <!-- Action Buttons -->
                <div class="pcg-escrito-actions-group">
                    <button type="button" class="pcg-escrito-action-btn pcg-escrito-btn-save pcg-btn-save-escrito">
                        <?php _e('Save', 'politeia-learning'); ?>
                    </button>
                    <a id="pcg-btn-preview-escrito" class="pcg-escrito-action-btn pcg-escrito-btn-save" href="#"
                        target="_blank" style="display:none;">
                        <span class="dashicons dashicons-visibility"></span>
                    </a>
                    <button type="button" class="pcg-escrito-action-btn pcg-escrito-btn-publish pcg-btn-save-escrito">
                        <?php _e('Publish', 'politeia-learning'); ?>
                    </button>
                </div>
            </div>
        </header>

        <main class="pcg-escrito-editor-wrapper">
            <!-- Title -->
            <textarea class="pcg-escrito-title-input" id="pcg-escrito-title"
                placeholder="<?php _e('The title...', 'politeia-learning'); ?>" spellcheck="false" rows="1"></textarea>

            <!-- Cover Upload -->
            <div class="pcg-escrito-cover-section">
                <!-- Current Preview -->
                <div id="pcg-escrito-thumbnail-preview" class="pcg-escrito-cover-preview" style="display:none;">
                    <img src="" alt="">
                    <button type="button" id="pcg-remove-escrito-thumbnail" class="pcg-escrito-remove-cover">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>

                <!-- Upload UI -->
                <div id="pcg-escrito-upload-ui" class="pcg-escrito-upload-label" data-upload="escrito-thumbnail">
                    <span class="dashicons dashicons-images-alt2"></span>
                    <span
                        class="pcg-escrito-upload-text"><?php _e('Upload Article Cover', 'politeia-learning'); ?></span>
                </div>
            </div>

            <!-- Content Area (Rich Text) -->
            <div id="pcg-escrito-content-editor" class="pcg-escrito-content-editor" contenteditable="true"
                spellcheck="true"></div>

            <!-- Hidden textarea for compatibility with save logic if needed, or just updated JS -->
            <textarea id="pcg-escrito-content" style="display:none;"></textarea>
            <textarea id="pcg-escrito-excerpt" style="display:none;"></textarea>
        </main>

        <div class="pcg-escrito-footer-note">
            Newsreader & Poppins • Minimalist Focus
        </div>
    </div>
</div>

<!-- MY ESCRITOS LIST -->
<div id="pcg-my-escritos-section" class="pcg-my-courses-container">
    <div class="pcg-section-header">
        <h3><?php _e('MIS ESCRITOS PUBLICADOS', 'politeia-learning'); ?></h3>
        <button type="button" id="pcg-show-escritos-form" class="pcg-btn-intro-create">
            <?php _e('Crear un escrito', 'politeia-learning'); ?>
        </button>
    </div>

    <div id="pcg-my-escritos-grid" class="pcg-my-courses-grid">
        <div class="pcg-loading-placeholder">
            <span class="dashicons dashicons-update spin"></span>
            <p><?php _e('Cargando tus escritos...', 'politeia-learning'); ?></p>
        </div>
    </div>
</div>
