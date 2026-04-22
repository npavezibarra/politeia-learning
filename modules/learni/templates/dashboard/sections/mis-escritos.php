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
                    <!-- Back Button -->
                    <button type="button" id="pcg-btn-back-to-escritos" class="pcg-toolbar-btn"
                        title="<?php _e('Volver', 'politeia-learning'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>

                    <div class="pcg-escrito-divider"></div>

                    <button type="button" onclick="window.pcgEscritoExec && window.pcgEscritoExec('bold')"
                        class="pcg-toolbar-btn" title="<?php esc_attr_e('Bold', 'politeia-learning'); ?>">
                        <span class="dashicons dashicons-editor-bold"></span>
                    </button>
                    <button type="button" onclick="window.pcgEscritoExec && window.pcgEscritoExec('italic')"
                        class="pcg-toolbar-btn" title="<?php esc_attr_e('Italic', 'politeia-learning'); ?>">
                        <span class="dashicons dashicons-editor-italic"></span>
                    </button>

                    <div class="pcg-escrito-divider"></div>

                    <button type="button" onclick="window.pcgEscritoExec && window.pcgEscritoExec('justifyLeft')"
                        class="pcg-toolbar-btn" title="<?php esc_attr_e('Align Left', 'politeia-learning'); ?>">
                        <span class="dashicons dashicons-editor-alignleft"></span>
                    </button>
                    <button type="button" onclick="window.pcgEscritoExec && window.pcgEscritoExec('justifyCenter')"
                        class="pcg-toolbar-btn" title="<?php esc_attr_e('Align Center', 'politeia-learning'); ?>">
                        <span class="dashicons dashicons-editor-aligncenter"></span>
                    </button>
                    <button type="button" onclick="window.pcgEscritoExec && window.pcgEscritoExec('justifyRight')"
                        class="pcg-toolbar-btn" title="<?php esc_attr_e('Align Right', 'politeia-learning'); ?>">
                        <span class="dashicons dashicons-editor-alignright"></span>
                    </button>
                    <button type="button" onclick="window.pcgEscritoExec && window.pcgEscritoExec('justifyFull')"
                        class="pcg-toolbar-btn" title="<?php esc_attr_e('Justify', 'politeia-learning'); ?>">
                        <span class="dashicons dashicons-editor-justify"></span>
                    </button>

                    <div class="pcg-escrito-divider"></div>

                    <!-- Headings Dropdown -->
                    <div class="pcg-escrito-dropdown">

                        <button type="button" class="pcg-toolbar-btn pcg-dropdown-trigger"
                            title="<?php esc_attr_e('Headings', 'politeia-learning'); ?>">

                            <span class="pcg-heading-icon">H</span>

                        </button>

                        <div class="pcg-dropdown-content">
                            <button type="button"
                                onclick="window.pcgEscritoFormatBlock && window.pcgEscritoFormatBlock('h1')">
                                <?php _e('H1 - Título', 'politeia-learning'); ?>
                            </button>
                            <button type="button"
                                onclick="window.pcgEscritoFormatBlock && window.pcgEscritoFormatBlock('h2')">
                                <?php _e('H2 - Sección', 'politeia-learning'); ?>
                            </button>
                            <button type="button"
                                onclick="window.pcgEscritoFormatBlock && window.pcgEscritoFormatBlock('h3')">
                                <?php _e('H3 - Subsección', 'politeia-learning'); ?>
                            </button>
                            <button type="button"
                                onclick="window.pcgEscritoFormatBlock && window.pcgEscritoFormatBlock('p')">
                                <?php _e('P - Texto Regular', 'politeia-learning'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="pcg-escrito-divider"></div>

                    <button type="button" id="pcg-btn-escrito-add-image" class="pcg-toolbar-btn"
                        title="<?php esc_attr_e('Agregar Imagen', 'politeia-learning'); ?>">
                        <span class="dashicons dashicons-format-image"></span>
                    </button>

                </div>

                <!-- Action Buttons -->
                <div class="pcg-escrito-actions-group">
                    <button type="button" class="pcg-escrito-action-btn pcg-escrito-btn-save pcg-btn-save-escrito"
                        data-action="draft">
                        <?php _e('GUARDAR', 'politeia-learning'); ?>
                    </button>
                    <a id="pcg-btn-preview-escrito" class="pcg-escrito-action-btn pcg-escrito-btn-save" href="#"
                        target="_blank" style="display:none; padding: 0 15px;">
                        <span class="dashicons dashicons-visibility"></span>
                    </a>
                    <button type="button" class="pcg-escrito-action-btn pcg-escrito-btn-publish pcg-btn-save-escrito"
                        data-action="publish">
                        <?php _e('PUBLICAR', 'politeia-learning'); ?> <span id="pcg-publish-status-icon"
                            class="dashicons dashicons-update"
                            style="display:none; margin-left: 6px; font-size: 16px; width: 16px; height: 16px; align-items: center; justify-content: center;"></span>
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
            <div class="pcg-editor-container" style="position: relative; width: 100%;">
                <div id="pcg-editor-placeholder"
                    style="position: absolute; top: 0; left: 0; pointer-events: none; color: #a0a0a0; font-size: 22px; font-family: 'Newsreader', serif; font-weight: 300; font-style: normal; z-index: 10;">
                    <?php _e('Escribe tu artículo aquí...', 'politeia-learning'); ?>
                </div>
                <div id="pcg-escrito-content-editor" class="pcg-escrito-content-editor" contenteditable="true"
                    spellcheck="true"></div>
            </div>

            <!-- Hidden textarea for compatibility with save logic if needed, or just updated JS -->
            <textarea id="pcg-escrito-content" style="display:none;"></textarea>
            <textarea id="pcg-escrito-excerpt" style="display:none;"></textarea>
        </main>


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
