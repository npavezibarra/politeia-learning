<?php
/**
 * Template for the Test Emails tab.
 * 
 * Variables available:
 * @var array  $items
 * @var array  $origin_labels
 * @var string $nonce
 * @var array  $templates
 * @var string $instructions
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$default_to_email = $current_user && !empty($current_user->user_email) ? (string) $current_user->user_email : (string) get_option('admin_email');
?>
<p style="margin-top: 12px;">
    <?php echo esc_html__('Lista unificada de correos automáticos (WP core / WooCommerce / Learni). Todos se muestran, incluso si no tienen template.', 'politeia-learning'); ?>
</p>

<!-- Global Settings Section -->
<div class="pl-email-global-settings" style="margin-bottom: 24px; padding: 18px; background: #fff; border: 1px solid #000; border-radius: 2px; display: flex; align-items: center; gap: 24px;">
    <div style="flex: 0 0 auto;">
        <div style="font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.18em; color: rgba(0,0,0,0.4); margin-bottom: 8px;"><?php echo esc_html__('Logo para Emails', 'politeia-learning'); ?></div>
        <?php 
        $custom_logo_id = get_option('pl_email_custom_logo_id');
        $custom_logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'thumbnail') : '';
        ?>
        <div id="pl-email-logo-preview" style="width: 100px; height: 60px; border: 1px dashed #ccc; border-radius: 4px; display: flex; align-items: center; justify-content: center; background: #f9fafb; overflow: hidden;">
            <?php if ($custom_logo_url): ?>
                <img src="<?php echo esc_url($custom_logo_url); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
            <?php else: ?>
                <span style="font-size: 10px; color: #94a3b8;"><?php echo esc_html__('Sin logo', 'politeia-learning'); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div style="flex: 1 1 auto;">
        <p style="margin-top: 0; margin-bottom: 12px; font-size: 13px; color: #334155;">
            <?php echo esc_html__('Selecciona un logo específico para las cabeceras de tus correos (JPG o PNG recomendado). Si no seleccionas ninguno, se usará el logo por defecto del sitio.', 'politeia-learning'); ?>
        </p>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="button" id="pl-select-email-logo"><?php echo esc_html__('Seleccionar Logo', 'politeia-learning'); ?></button>
            <button type="button" class="button" id="pl-remove-email-logo" <?php echo !$custom_logo_id ? 'style="display:none;"' : ''; ?>><?php echo esc_html__('Eliminar', 'politeia-learning'); ?></button>
            <span id="pl-email-logo-status" style="margin-left: 10px; font-size: 12px; color: #64748b; align-self: center;"></span>
        </div>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=JetBrains+Mono:wght@700&display=swap');

    .pl-email-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 14px;
    }
    .pl-email-card {
        width: 100%;
        max-width: 340px;
        border: 1px solid #000;
        border-radius: 2px;
        overflow: hidden;
        background: #fff;
        box-shadow: 4px 4px 0px 0px rgba(0,0,0,0.05);
        font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
    }
    .pl-email-card * { box-sizing: border-box; }
    .pl-email-card-header {
        padding: 12px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        cursor: pointer;
        transition: background-color .15s ease;
    }
    .pl-email-card-header:hover { background: #f9fafb; }
    .pl-email-card-header-left { min-width: 0; padding-right: 8px; }
    .pl-email-card-toggle {
        margin-top: 2px;
        padding: 4px;
        background: transparent;
        border: 0;
        cursor: pointer;
        color: #000;
        border-radius: 2px;
        transition: all .15s ease;
        flex: 0 0 auto;
    }
    .pl-email-card-toggle:hover { background: #000; color: #fff; }
    .pl-email-card-toggle svg { width: 16px; height: 16px; stroke-width: 3px; transition: transform .15s ease; }
    .pl-email-card-toggle:hover svg { stroke: #fff; }
    .pl-email-card-toggle svg.pl-rotated { transform: rotate(180deg); }
    .pl-email-card-collapsible {
        border-top: 1px solid rgba(0,0,0,0.06);
        max-height: 0;
        overflow: hidden;
        opacity: 0;
        transition: max-height .2s ease, opacity .2s ease;
    }
    .pl-email-card.is-open .pl-email-card-collapsible { max-height: 1000px; opacity: 1; }
    .pl-email-card-badge {
        display: inline-block;
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: -0.02em;
        color: #000;
        border: 1px solid #000;
        padding: 2px 6px;
        border-radius: 2px;
        font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace;
    }
    .pl-email-card-title {
        margin: 10px 0 12px;
        font-size: 13px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: -0.02em;
        line-height: 1.15;
        color: #000;
    }
    .pl-email-card-actions { padding: 12px; padding-top: 10px; display: flex; gap: 8px; }
    .pl-email-card-btn {
        flex: 1 1 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 10px 8px;
        border: 1px solid #000;
        background: #fff;
        color: #000;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        cursor: pointer;
        transition: all .15s ease;
    }
    .pl-email-card-btn:hover { background:#000; color:#fff; }
    .pl-email-card-btn svg { width: 12px; height: 12px; stroke-width: 3px; }
    .pl-email-card-btn:hover svg { stroke: #fff; }
    .pl-email-card-meta { padding: 14px 12px; display: grid; gap: 14px; }
    .pl-email-card-meta-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .pl-email-card-k { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .18em; color: rgba(0,0,0,0.4); margin-bottom: 6px; }
    .pl-email-card-v {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 800;
        color: #000;
    }
    .pl-email-card-v svg { width: 12px; height: 12px; stroke-width: 3px; }
    .pl-email-card-select {
        width: 100%;
        appearance: none;
        background-color: #fff;
        border: 1px solid #000;
        border-radius: 2px;
        padding: 10px 34px 10px 10px;
        font-size: 11px;
        font-weight: 800;
        color: #000;
        cursor: pointer;
        transition: all .15s ease;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 16px;
    }
    .pl-email-card-select:focus {
        outline: none;
        background-color: #000;
        color: #fff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    }
    .pl-email-card-footer {
        padding: 12px;
        background:#000;
        display:flex;
        align-items:center;
        justify-content: space-between;
        gap: 10px;
    }
    .pl-email-card-copy {
        display:inline-flex;
        align-items:center;
        gap: 6px;
        background: transparent;
        border: 0;
        cursor: pointer;
        padding: 0;
        color: rgba(255,255,255,0.75);
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .pl-email-card-copy[disabled] { opacity: 0.45; cursor: not-allowed; }
    .pl-email-card-copy:hover { color: #fff; }
    .pl-email-card-copy svg { width: 12px; height: 12px; stroke: rgba(255,255,255,0.75); }
    .pl-email-card-copy:hover svg { stroke: #fff; }
    .pl-email-card-copy.pl-copied { color: #34d399; }
    .pl-email-card-copy.pl-copied svg { stroke: #34d399; }
    .pl-email-card-edit {
        display:inline-flex;
        align-items:center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 2px;
        border: 0;
        cursor: pointer;
        background: #fff;
        color: #000;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: -0.02em;
        box-shadow: 2px 2px 0px 0px rgba(255,255,255,0.2);
    }
    .pl-email-card-edit:hover { background: #e5e7eb; }
    .pl-email-card-edit svg { width: 12px; height: 12px; stroke-width: 2.6px; }
</style>

<div style="margin-top: 10px; margin-bottom: 12px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <button type="button" class="button" id="pl-copy-email-instructions"><?php echo esc_html__('COPY INSTRUCTIONS', 'politeia-learning'); ?></button>
    <div id="pl-test-emails-origin-filter" style="display:flex;gap:8px;align-items:center;">
        <span style="font-size:12px;color:#334155;font-weight:600;"><?php echo esc_html__('Tipos de Emails', 'politeia-learning'); ?>:</span>
            <button type="button" class="button" id="pl-test-emails-origin-filter-toggle" aria-expanded="false" aria-controls="pl-test-emails-origin-filter-panel">
                <?php echo esc_html__('Todos', 'politeia-learning'); ?>
            </button>
        <div id="pl-test-emails-origin-filter-panel" style="display:none; position:relative;">
            <div style="position:absolute;z-index:999; margin-top:6px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 10px 25px rgba(15,23,42,0.12); padding:10px 12px; min-width: 220px;">
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#0f172a;">
                        <input type="checkbox" class="pl-test-emails-origin-all" checked>
                        <span><?php echo esc_html__('Todos', 'politeia-learning'); ?></span>
                    </label>
                    <div style="height:1px;background:#e2e8f0;"></div>
                    <?php foreach ($origin_labels as $origin_label): ?>
                        <?php $origin_key = sanitize_key(str_replace(' ', '_', (string) $origin_label)); ?>
                        <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#0f172a;">
                            <input type="checkbox" class="pl-test-emails-origin-opt" value="<?php echo esc_attr($origin_key); ?>" checked>
                            <span><?php echo esc_html((string) $origin_label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div id="pl-test-emails-recipient-filter" style="display:flex;gap:8px;align-items:center;">
        <span style="font-size:12px;color:#334155;font-weight:600;"><?php echo esc_html__('Quién recibe', 'politeia-learning'); ?>:</span>
        <button type="button" class="button" id="pl-test-emails-recipient-filter-toggle" aria-expanded="false" aria-controls="pl-test-emails-recipient-filter-panel">
            <?php echo esc_html__('Todos', 'politeia-learning'); ?>
        </button>
        <div id="pl-test-emails-recipient-filter-panel" style="display:none; position:relative;">
            <div style="position:absolute;z-index:999; margin-top:6px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 10px 25px rgba(15,23,42,0.12); padding:10px 12px; min-width: 220px;">
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#0f172a;">
                        <input type="checkbox" class="pl-test-emails-recipient-all" checked>
                        <span><?php echo esc_html__('Todos', 'politeia-learning'); ?></span>
                    </label>
                    <div style="height:1px;background:#e2e8f0;"></div>
                    <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#0f172a;">
                        <input type="checkbox" class="pl-test-emails-recipient-opt" value="admin" checked>
                        <span><?php echo esc_html__('Admin', 'politeia-learning'); ?></span>
                    </label>
                    <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#0f172a;">
                        <input type="checkbox" class="pl-test-emails-recipient-opt" value="customer" checked>
                        <span><?php echo esc_html__('Cliente', 'politeia-learning'); ?></span>
                    </label>
                    <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#0f172a;">
                        <input type="checkbox" class="pl-test-emails-recipient-opt" value="other" checked>
                        <span><?php echo esc_html__('Otro', 'politeia-learning'); ?></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
    <span id="pl-copy-email-instructions-status" style="font-size:12px;color:#64748b;"></span>
    <textarea id="pl-copy-email-instructions-text" style="position:absolute;left:-9999px;top:-9999px;"><?php echo esc_textarea($instructions); ?></textarea>
</div>

<div id="pl-html-sandbox" style="margin-top: 10px; margin-bottom: 14px; padding: 16px 18px; background:#fff; border:1px solid #e2e8f0; border-radius: 12px;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
        <div style="min-width: 260px; flex: 1 1 auto;">
            <div style="font-size:12px; font-weight:800; letter-spacing: 0.16em; text-transform: uppercase; color:#0f172a;">
                <?php echo esc_html__('HTML Sandbox', 'politeia-learning'); ?>
            </div>
            <div style="margin-top: 6px; font-size:13px; color:#475569; line-height:1.4;">
                <?php echo esc_html__('Pega tu HTML + CSS completo aquí para previsualizarlo y enviarte un correo de prueba.', 'politeia-learning'); ?>
            </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap: wrap;">
            <label style="display:flex; flex-direction:column; gap:4px;">
                <span style="font-size:11px; color:#64748b; font-weight:600;"><?php echo esc_html__('To', 'politeia-learning'); ?></span>
                <input id="pl-html-sandbox-to" type="email" value="<?php echo esc_attr($default_to_email); ?>" style="width: 260px;">
            </label>
            <label style="display:flex; flex-direction:column; gap:4px;">
                <span style="font-size:11px; color:#64748b; font-weight:600;"><?php echo esc_html__('Subject', 'politeia-learning'); ?></span>
                <input id="pl-html-sandbox-subject" type="text" value="<?php echo esc_attr__('Test email (Custom HTML)', 'politeia-learning'); ?>" style="width: 260px;">
            </label>
            <div style="display:flex; gap:10px; align-items:flex-end;">
                <button type="button" class="button" id="pl-html-sandbox-preview"><?php echo esc_html__('Preview', 'politeia-learning'); ?></button>
                <button type="button" class="button button-primary" id="pl-html-sandbox-send"><?php echo esc_html__('Send test email', 'politeia-learning'); ?></button>
            </div>
        </div>
    </div>

    <div style="margin-top: 12px;">
        <textarea id="pl-html-sandbox-code" rows="10" style="width:100%; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace;" placeholder="<!doctype html>\n<html>\n  <head>\n    <style>/* CSS */</style>\n  </head>\n  <body>\n    ...\n  </body>\n</html>"></textarea>
        <div style="margin-top: 6px; font-size:12px; color:#64748b;">
            <?php echo esc_html__('Tip: usa HTML completo (<html><head>...) para simular clientes reales. Scripts se bloquean por seguridad.', 'politeia-learning'); ?>
        </div>
        <div id="pl-html-sandbox-status" style="margin-top: 8px; font-size:12px; color:#64748b;"></div>
    </div>
</div>

<div class="pl-test-emails-layout" style="display:flex; gap:16px; align-items:flex-start; margin-top: 12px;">
    <div class="pl-test-emails-list" style="flex: 1 1 720px; max-width: 720px;">
        <div class="pl-email-cards">
            <?php foreach ($items as $key => $item): ?>
                <?php $enabled = !empty($templates[$key]['enabled']); ?>
                <?php $custom_template = isset($templates[$key]['template']) ? (string) $templates[$key]['template'] : ''; ?>
                <?php 
                $path = isset($item['default_template']) ? trim((string) $item['default_template']) : '';
                $default_template_label = $path !== '' ? $path : __('No existe', 'politeia-learning');
                ?>
                <?php $has_custom = $enabled && trim($custom_template) !== ''; ?>
                <?php $origin = isset($item['origin']) ? (string) $item['origin'] : ''; ?>
                <?php $origin_label = $origin !== '' ? $origin : __('Otros', 'politeia-learning'); ?>
                <?php $origin_key = sanitize_key(str_replace(' ', '_', $origin_label)); ?>
                <?php $recipient = isset($item['recipient']) ? sanitize_key((string) $item['recipient']) : 'other'; ?>
                <?php if (!in_array($recipient, ['admin', 'customer', 'other'], true)) { $recipient = 'other'; } ?>
                <?php $recipient_label = $recipient === 'admin' ? __('Admin', 'politeia-learning') : ($recipient === 'customer' ? __('Cliente', 'politeia-learning') : __('Otro', 'politeia-learning')); ?>
                <?php $name = isset($item['label']) ? (string) $item['label'] : (string) $key; ?>

                <div class="pl-email-card pl-test-email-card" data-key="<?php echo esc_attr((string) $key); ?>" data-email-origin="<?php echo esc_attr($origin_key); ?>" data-email-recipient="<?php echo esc_attr($recipient); ?>">
                    <div class="pl-email-card-header" data-toggle="card">
                        <div class="pl-email-card-header-left">
                            <div><span class="pl-email-card-badge"><?php echo esc_html((string) $key); ?></span></div>
                            <div class="pl-email-card-title"><?php echo esc_html($name); ?></div>
                        </div>
                        <button type="button" class="pl-email-card-toggle" aria-expanded="false" aria-label="<?php echo esc_attr__('Expandir', 'politeia-learning'); ?>">
                            <svg class="pl-email-card-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m6 9 6 6 6-6"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="pl-email-card-collapsible">
                        <div class="pl-email-card-actions">
                            <button type="button" class="pl-email-card-btn pl-test-email-view" data-key="<?php echo esc_attr((string) $key); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                <?php echo esc_html__('Ver', 'politeia-learning'); ?>
                            </button>
                            <button type="button" class="pl-email-card-btn pl-test-email-send" data-key="<?php echo esc_attr((string) $key); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 3h15"/><path d="M6 3v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3"/><path d="M6 14h12"/></svg>
                                <?php echo esc_html__('Test', 'politeia-learning'); ?>
                            </button>
                        </div>

                        <div class="pl-email-card-meta">
                            <div class="pl-email-card-meta-grid">
                                <div>
                                    <div class="pl-email-card-k"><?php echo esc_html__('Origen', 'politeia-learning'); ?></div>
                                    <div class="pl-email-card-v">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 12 22l-8.59-8.59a2 2 0 0 1 0-2.82l8.59-8.59 8.59 8.59a2 2 0 0 1 0 2.82Z"/><path d="M7 7h.01"/></svg>
                                        <?php echo esc_html($origin_label); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="pl-email-card-k"><?php echo esc_html__('Recibe', 'politeia-learning'); ?></div>
                                    <div class="pl-email-card-v">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        <?php echo esc_html($recipient_label); ?>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="pl-email-card-k" for="pl-email-mode-<?php echo esc_attr((string) $key); ?>"><?php echo esc_html__('Template File', 'politeia-learning'); ?></label>
                                <select id="pl-email-mode-<?php echo esc_attr((string) $key); ?>" class="pl-email-card-select pl-test-email-mode" data-key="<?php echo esc_attr((string) $key); ?>">
                                    <option value="traditional" <?php selected(false, $enabled); ?>><?php echo esc_html((string) $default_template_label); ?></option>
                                    <option value="custom" <?php selected(true, $enabled); ?>><?php echo esc_html__('Custom', 'politeia-learning'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="pl-email-card-footer">
                            <button type="button" class="pl-email-card-copy pl-test-email-copy-path" data-copy="<?php echo esc_attr((string) $default_template_label); ?>" <?php echo $default_template_label === __('No existe', 'politeia-learning') ? 'disabled' : ''; ?>>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                <span class="pl-email-card-copy-text"><?php echo esc_html__('Ruta', 'politeia-learning'); ?></span>
                            </button>
                            <button type="button" class="pl-email-card-edit pl-test-email-template" data-key="<?php echo esc_attr((string) $key); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
                                <?php echo esc_html__('Editar', 'politeia-learning'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="description" style="margin-top: 10px;">
            <?php echo esc_html__('“TEST” envía un correo de prueba al email del usuario actual.', 'politeia-learning'); ?>
        </p>
    </div>

    <div class="pl-test-emails-preview" style="flex: 1 1 auto; min-width: 420px; position: sticky; top: 32px; align-self: flex-start;">
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
            <div style="padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                    <strong id="pl-test-emails-panel-title"><?php echo esc_html__('Preview', 'politeia-learning'); ?></strong>
                    <span id="pl-test-emails-status" style="font-size:12px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></span>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex:0 0 auto;">
                    <button type="button" class="button" id="pl-test-emails-mode-preview"><?php echo esc_html__('Preview', 'politeia-learning'); ?></button>
                    <button type="button" class="button" id="pl-test-emails-mode-code"><?php echo esc_html__('Edit code', 'politeia-learning'); ?></button>
                </div>
            </div>
            <div id="pl-test-emails-preview-pane" style="height: calc(100vh - 220px); min-height: 520px; background: #f1f5f9;">
                <iframe id="pl-test-email-preview-frame" style="width:100%;height:100%;border:none;background:#f1f5f9;" src="about:blank"></iframe>
            </div>
            <div id="pl-test-emails-code-pane" style="display:none; height: calc(100vh - 220px); min-height: 520px; background:#ffffff;">
                <div style="padding:12px;border-bottom:1px solid #e2e8f0;display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
                    <div id="pl-test-emails-code-note" style="font-size:12px;color:#64748b;">
                        <?php echo esc_html__('Edit the HTML and save to store it as a Custom override for this email.', 'politeia-learning'); ?>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <button type="button" class="button" id="pl-test-emails-code-cancel"><?php echo esc_html__('Cancel', 'politeia-learning'); ?></button>
                        <button type="button" class="button button-primary" id="pl-test-emails-code-save"><?php echo esc_html__('Save', 'politeia-learning'); ?></button>
                    </div>
                </div>
                <textarea id="pl-test-emails-code-editor" spellcheck="false" style="width:100%;height:calc(100% - 54px);border:0;resize:none;padding:14px 16px;box-sizing:border-box;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace;font-size:12px;line-height:1.5;"></textarea>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const frame = document.getElementById('pl-test-email-preview-frame');
        const status = document.getElementById('pl-test-emails-status');
        const nonce = <?php echo wp_json_encode($nonce); ?>;
        const copyBtn = document.getElementById('pl-copy-email-instructions');
        const copyStatus = document.getElementById('pl-copy-email-instructions-status');
        const copyText = document.getElementById('pl-copy-email-instructions-text');
        const panelTitle = document.getElementById('pl-test-emails-panel-title');
        const previewPane = document.getElementById('pl-test-emails-preview-pane');
        const codePane = document.getElementById('pl-test-emails-code-pane');
        const codeEditor = document.getElementById('pl-test-emails-code-editor');
        const codeNote = document.getElementById('pl-test-emails-code-note');
        const modePreviewBtn = document.getElementById('pl-test-emails-mode-preview');
        const modeCodeBtn = document.getElementById('pl-test-emails-mode-code');
        const codeSaveBtn = document.getElementById('pl-test-emails-code-save');
        const codeCancelBtn = document.getElementById('pl-test-emails-code-cancel');
        let activeKey = '';
        const originFilterToggle = document.getElementById('pl-test-emails-origin-filter-toggle');
        const originFilterPanelWrap = document.getElementById('pl-test-emails-origin-filter-panel');
        const originAll = document.querySelector('#pl-test-emails-origin-filter-panel .pl-test-emails-origin-all');
        const originOpts = Array.from(document.querySelectorAll('#pl-test-emails-origin-filter-panel .pl-test-emails-origin-opt'));
        const recipientFilterToggle = document.getElementById('pl-test-emails-recipient-filter-toggle');
        const recipientFilterPanelWrap = document.getElementById('pl-test-emails-recipient-filter-panel');
        const recipientAll = document.querySelector('#pl-test-emails-recipient-filter-panel .pl-test-emails-recipient-all');
        const recipientOpts = Array.from(document.querySelectorAll('#pl-test-emails-recipient-filter-panel .pl-test-emails-recipient-opt'));
        const emailRows = Array.from(document.querySelectorAll('.pl-test-email-card'));
        const originStorageKey = 'pl_test_emails_origin_filter_v1';
        const recipientStorageKey = 'pl_test_emails_recipient_filter_v1';
        const htmlSandboxCode = document.getElementById('pl-html-sandbox-code');
        const htmlSandboxTo = document.getElementById('pl-html-sandbox-to');
        const htmlSandboxSubject = document.getElementById('pl-html-sandbox-subject');
        const htmlSandboxPreview = document.getElementById('pl-html-sandbox-preview');
        const htmlSandboxSend = document.getElementById('pl-html-sandbox-send');
        const htmlSandboxStatus = document.getElementById('pl-html-sandbox-status');
        const htmlSandboxStorageKey = 'pl_test_emails_html_sandbox_v1';

        function setHtmlSandboxStatus(message, tone) {
            if (!htmlSandboxStatus) return;
            htmlSandboxStatus.textContent = message || '';
            htmlSandboxStatus.style.color = tone === 'error' ? '#b91c1c' : (tone === 'success' ? '#047857' : '#64748b');
        }

        if (htmlSandboxCode) {
            try {
                const saved = localStorage.getItem(htmlSandboxStorageKey);
                if (saved) htmlSandboxCode.value = saved;
            } catch (e) {}

            htmlSandboxCode.addEventListener('input', function() {
                try { localStorage.setItem(htmlSandboxStorageKey, htmlSandboxCode.value || ''); } catch (e) {}
            });
        }

        if (htmlSandboxPreview && htmlSandboxCode && frame) {
            htmlSandboxPreview.addEventListener('click', function() {
                const html = (htmlSandboxCode.value || '').trim();
                if (!html) {
                    setHtmlSandboxStatus(<?php echo wp_json_encode(__('Pega HTML primero.', 'politeia-learning')); ?>, 'error');
                    return;
                }
                frame.srcdoc = html;
                setHtmlSandboxStatus(<?php echo wp_json_encode(__('Preview actualizado.', 'politeia-learning')); ?>, 'success');
            });
        }

        if (htmlSandboxSend && htmlSandboxCode && htmlSandboxTo && htmlSandboxSubject) {
            htmlSandboxSend.addEventListener('click', async function() {
                const html = (htmlSandboxCode.value || '').trim();
                const to = (htmlSandboxTo.value || '').trim();
                const subject = (htmlSandboxSubject.value || '').trim();

                if (!html) {
                    setHtmlSandboxStatus(<?php echo wp_json_encode(__('Pega HTML primero.', 'politeia-learning')); ?>, 'error');
                    return;
                }

                setHtmlSandboxStatus(<?php echo wp_json_encode(__('Enviando...', 'politeia-learning')); ?>, 'info');

                const form = new URLSearchParams();
                form.set('action', 'pl_send_custom_test_email');
                form.set('nonce', nonce);
                form.set('to', to);
                form.set('subject', subject);
                form.set('html', html);

                try {
                    const res = await fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: form.toString(),
                        credentials: 'same-origin',
                    });
                    const data = await res.json();
                    if (data && data.success) {
                        setHtmlSandboxStatus(<?php echo wp_json_encode(__('Enviado a:', 'politeia-learning')); ?> + ' ' + (data.data && data.data.to ? data.data.to : to), 'success');
                        return;
                    }
                    setHtmlSandboxStatus((data && data.data && data.data.message) ? data.data.message : <?php echo wp_json_encode(__('Falló el envío.', 'politeia-learning')); ?>, 'error');
                } catch (e) {
                    setHtmlSandboxStatus(<?php echo wp_json_encode(__('Error de red al enviar.', 'politeia-learning')); ?>, 'error');
                }
            });
        }

        function setOriginPanelOpen(open) {
            if (!originFilterToggle || !originFilterPanelWrap) return;
            originFilterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            originFilterPanelWrap.style.display = open ? 'block' : 'none';
        }

        function getSelectedOrigins() {
            return originOpts.filter(opt => opt.checked).map(opt => opt.value);
        }

        function setRecipientPanelOpen(open) {
            if (!recipientFilterToggle || !recipientFilterPanelWrap) return;
            recipientFilterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            recipientFilterPanelWrap.style.display = open ? 'block' : 'none';
        }

        function getSelectedRecipients() {
            return recipientOpts.filter(opt => opt.checked).map(opt => opt.value);
        }

        function updateRecipientToggleLabel() {
            if (!recipientFilterToggle) return;
            const selected = getSelectedRecipients();
            if (selected.length === 0 || selected.length === recipientOpts.length) {
                recipientFilterToggle.textContent = 'Todos';
                return;
            }
            recipientFilterToggle.textContent = selected.length + ' seleccionado(s)';
        }

        function updateOriginToggleLabel() {
            if (!originFilterToggle) return;
            const selected = getSelectedOrigins();
            if (selected.length === 0 || selected.length === originOpts.length) {
                originFilterToggle.textContent = 'Todos';
                return;
            }
            originFilterToggle.textContent = selected.length + ' seleccionado(s)';
        }

        function applyFilters() {
            const selectedOrigins = getSelectedOrigins();
            const selectedRecipients = getSelectedRecipients();
            const allOriginsSelected = selectedOrigins.length === 0 || selectedOrigins.length === originOpts.length;
            const allRecipientsSelected = selectedRecipients.length === 0 || selectedRecipients.length === recipientOpts.length;

            emailRows.forEach(card => {
                const rowOrigin = card.getAttribute('data-email-origin') || '';
                const rowRecipient = card.getAttribute('data-email-recipient') || 'other';
                const showByOrigin = allOriginsSelected || selectedOrigins.includes(rowOrigin);
                const showByRecipient = allRecipientsSelected || selectedRecipients.includes(rowRecipient);
                card.style.display = (showByOrigin && showByRecipient) ? '' : 'none';
            });
        }

        function persistOriginFilter() {
            try {
                const selected = getSelectedOrigins();
                window.localStorage.setItem(originStorageKey, JSON.stringify(selected));
            } catch (e) {}
        }

        function persistRecipientFilter() {
            try {
                const selected = getSelectedRecipients();
                window.localStorage.setItem(recipientStorageKey, JSON.stringify(selected));
            } catch (e) {}
        }

        function restoreOriginFilter() {
            let selected = null;
            try {
                const raw = window.localStorage.getItem(originStorageKey);
                if (raw) selected = JSON.parse(raw);
            } catch (e) {}

            if (!Array.isArray(selected)) {
                if (originAll) originAll.checked = true;
                originOpts.forEach(opt => opt.checked = true);
                updateOriginToggleLabel();
                applyFilters();
                return;
            }

            const selectedSet = new Set(selected.map(String));
            originOpts.forEach(opt => opt.checked = selectedSet.has(opt.value));
            const nowSelected = getSelectedOrigins();
            if (originAll) originAll.checked = nowSelected.length === originOpts.length;
            updateOriginToggleLabel();
            applyFilters();
        }

        function restoreRecipientFilter() {
            let selected = null;
            try {
                const raw = window.localStorage.getItem(recipientStorageKey);
                if (raw) selected = JSON.parse(raw);
            } catch (e) {}

            if (!Array.isArray(selected)) {
                if (recipientAll) recipientAll.checked = true;
                recipientOpts.forEach(opt => opt.checked = true);
                updateRecipientToggleLabel();
                applyFilters();
                return;
            }

            const selectedSet = new Set(selected.map(String));
            recipientOpts.forEach(opt => opt.checked = selectedSet.has(opt.value));
            const nowSelected = getSelectedRecipients();
            if (recipientAll) recipientAll.checked = nowSelected.length === recipientOpts.length;
            updateRecipientToggleLabel();
            applyFilters();
        }

        if (originFilterToggle && originFilterPanelWrap && originAll && originOpts.length) {
            originFilterToggle.addEventListener('click', function () {
                const open = originFilterToggle.getAttribute('aria-expanded') === 'true';
                setOriginPanelOpen(!open);
            });

            document.addEventListener('click', function (e) {
                if (originFilterToggle.contains(e.target) || originFilterPanelWrap.contains(e.target)) return;
                setOriginPanelOpen(false);
            });

            originAll.addEventListener('change', function () {
                const checked = originAll.checked;
                originOpts.forEach(opt => opt.checked = checked);
                updateOriginToggleLabel();
                applyFilters();
                persistOriginFilter();
            });

            originOpts.forEach(opt => {
                opt.addEventListener('change', function () {
                    const selected = getSelectedOrigins();
                    originAll.checked = selected.length === originOpts.length;
                    updateOriginToggleLabel();
                    applyFilters();
                    persistOriginFilter();
                });
            });

            restoreOriginFilter();
        }

        if (recipientFilterToggle && recipientFilterPanelWrap && recipientAll && recipientOpts.length) {
            recipientFilterToggle.addEventListener('click', function () {
                const open = recipientFilterToggle.getAttribute('aria-expanded') === 'true';
                setRecipientPanelOpen(!open);
            });

            document.addEventListener('click', function (e) {
                if (recipientFilterToggle.contains(e.target) || recipientFilterPanelWrap.contains(e.target)) return;
                setRecipientPanelOpen(false);
            });

            recipientAll.addEventListener('change', function () {
                const checked = recipientAll.checked;
                recipientOpts.forEach(opt => opt.checked = checked);
                updateRecipientToggleLabel();
                applyFilters();
                persistRecipientFilter();
            });

            recipientOpts.forEach(opt => {
                opt.addEventListener('change', function () {
                    const selected = getSelectedRecipients();
                    recipientAll.checked = selected.length === recipientOpts.length;
                    updateRecipientToggleLabel();
                    applyFilters();
                    persistRecipientFilter();
                });
            });

            restoreRecipientFilter();
        }

        function setStatus(text, isError) {
            status.textContent = text || '';
            status.style.color = isError ? '#b91c1c' : '#64748b';
        }

        function setCopyStatus(text, isError) {
            copyStatus.textContent = text || '';
            copyStatus.style.color = isError ? '#b91c1c' : '#64748b';
        }

        copyBtn.addEventListener('click', async function() {
            setCopyStatus('', false);
            const text = copyText.value || '';

            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    copyText.focus();
                    copyText.select();
                    document.execCommand('copy');
                }
                setCopyStatus('Copiado.', false);
            } catch (e) {
                setCopyStatus('No se pudo copiar.', true);
            }
        });

        document.addEventListener('click', async function(e) {
            const header = e.target && e.target.closest ? e.target.closest('.pl-email-card-header[data-toggle=\"card\"]') : null;
            const toggle = e.target && e.target.closest ? e.target.closest('.pl-email-card-toggle') : null;
            const card = (header || toggle) && e.target && e.target.closest ? e.target.closest('.pl-email-card') : null;
            if (card && (header || toggle)) {
                e.preventDefault();
                const icon = card.querySelector('.pl-email-card-toggle-icon');
                const toggleBtn = card.querySelector('.pl-email-card-toggle');
                card.classList.toggle('is-open');
                if (icon) icon.classList.toggle('pl-rotated');
                if (toggleBtn) toggleBtn.setAttribute('aria-expanded', card.classList.contains('is-open') ? 'true' : 'false');
                return;
            }

            const btn = e.target && e.target.closest ? e.target.closest('.pl-test-email-copy-path') : null;
            if (!btn) return;
            e.preventDefault();
            if (btn.disabled) return;

            const text = btn.getAttribute('data-copy') || '';
            if (!text || text === 'No existe') return;

            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const temp = document.createElement('textarea');
                    temp.value = text;
                    temp.style.position = 'fixed';
                    temp.style.top = '-9999px';
                    temp.style.left = '-9999px';
                    document.body.appendChild(temp);
                    temp.focus();
                    temp.select();
                    document.execCommand('copy');
                    temp.remove();
                }

                const label = btn.querySelector('.pl-email-card-copy-text');
                const original = label ? label.textContent : null;
                if (label) label.textContent = 'Copiado';
                btn.classList.add('pl-copied');
                window.setTimeout(() => {
                    if (label && original) label.textContent = original;
                    btn.classList.remove('pl-copied');
                }, 2000);
            } catch (err) {
                // no-op
            }
        });

        function setPanelMode(mode) {
            const showCode = mode === 'code';
            if (previewPane) previewPane.style.display = showCode ? 'none' : 'block';
            if (codePane) codePane.style.display = showCode ? 'block' : 'none';
            if (panelTitle) panelTitle.textContent = showCode ? 'Edit code' : 'Preview';
        }

        function loadPreview(key) {
            if (!key) return;
            activeKey = key;
            setPanelMode('preview');
            frame.src = 'about:blank';
            setStatus('Cargando preview…', false);

            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=pl_get_test_email_preview&key=' + encodeURIComponent(key) + '&nonce=' + encodeURIComponent(nonce))
                .then(r => r.text())
                .then(html => {
                    const doc = frame.contentWindow.document;
                    doc.open();
                    doc.write(html);
                    doc.close();
                    setStatus('', false);
                })
                .catch(() => setStatus('No se pudo cargar el preview.', true));
        }

        function loadSourceIntoEditor(key) {
            if (!key) return;
            activeKey = key;
            setPanelMode('code');
            if (codeEditor) codeEditor.value = '';
            if (codeNote) codeNote.textContent = 'Cargando código…';
            setStatus('', false);

            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=pl_get_test_email_source&key=' + encodeURIComponent(key) + '&nonce=' + encodeURIComponent(nonce))
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.success || !data.data) {
                        if (codeNote) codeNote.textContent = 'No se pudo cargar el código.';
                        return;
                    }
                    const payload = data.data;
                    if (codeEditor) codeEditor.value = payload.template || '';
                    if (codeNote) {
                        codeNote.textContent = payload.note ? payload.note : 'Edit the HTML and save to store it as a Custom override for this email.';
                    }
                })
                .catch(() => {
                    if (codeNote) codeNote.textContent = 'No se pudo cargar el código.';
                });
        }

        document.querySelectorAll('.pl-test-email-view').forEach(btn => {
            btn.addEventListener('click', function() {
                loadPreview(this.getAttribute('data-key'));
            });
        });

        document.querySelectorAll('.pl-test-email-send').forEach(btn => {
            btn.addEventListener('click', function() {
                const key = this.getAttribute('data-key');
                setStatus('Enviando test…', false);

                const body = new URLSearchParams();
                body.set('action', 'pl_send_test_email');
                body.set('nonce', nonce);
                body.set('key', key);

                fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.success) {
                            setStatus('Enviado a ' + (data.data && data.data.to ? data.data.to : ''), false);
                        } else {
                            setStatus('No se pudo enviar el test.', true);
                        }
                    })
                    .catch(() => setStatus('No se pudo enviar el test.', true));
            });
        });

        document.querySelectorAll('.pl-test-email-mode').forEach(select => {
            select.addEventListener('change', function() {
                const key = this.getAttribute('data-key');
                const mode = this.value;

                const body = new URLSearchParams();
                body.set('action', 'pl_set_test_email_template_mode');
                body.set('nonce', nonce);
                body.set('key', key);
                body.set('mode', mode);

                fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                }).catch(() => {});
            });
        });

        document.querySelectorAll('.pl-test-email-template').forEach(btn => {
            btn.addEventListener('click', function() {
                loadSourceIntoEditor(this.getAttribute('data-key'));
            });
        });

        if (modePreviewBtn) {
            modePreviewBtn.addEventListener('click', function() {
                if (activeKey) loadPreview(activeKey);
                else setPanelMode('preview');
            });
        }

        if (modeCodeBtn) {
            modeCodeBtn.addEventListener('click', function() {
                if (activeKey) loadSourceIntoEditor(activeKey);
                else setPanelMode('code');
            });
        }

        if (codeCancelBtn) {
            codeCancelBtn.addEventListener('click', function() {
                if (activeKey) loadPreview(activeKey);
                else setPanelMode('preview');
            });
        }

        if (codeSaveBtn) {
            codeSaveBtn.addEventListener('click', function() {
                if (!activeKey) return;
                const body = new URLSearchParams();
                body.set('action', 'pl_save_test_email_template');
                body.set('nonce', nonce);
                body.set('key', activeKey);
                body.set('template', (codeEditor && codeEditor.value) ? codeEditor.value : '');
                body.set('enabled', '1');

                setStatus('Guardando template…', false);
                fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.success) {
                            setStatus('Template guardado.', false);
                            const modeSelect = document.querySelector('.pl-test-email-mode[data-key=\"' + activeKey + '\"]');
                            if (modeSelect) modeSelect.value = 'custom';
                            loadPreview(activeKey);
                        } else {
                            setStatus('No se pudo guardar el template.', true);
                        }
                    })
                    .catch(() => setStatus('No se pudo guardar el template.', true));
            });
        }

        // --- Custom Email Logo Selector Logic ---
        const selectLogoBtn = document.getElementById('pl-select-email-logo');
        const removeLogoBtn = document.getElementById('pl-remove-email-logo');
        const logoPreview = document.getElementById('pl-email-logo-preview');
        const logoStatus = document.getElementById('pl-email-logo-status');
        let logoFrame;

        if (selectLogoBtn) {
            selectLogoBtn.addEventListener('click', function(e) {
                e.preventDefault();

                if (logoFrame) {
                    logoFrame.open();
                    return;
                }

                logoFrame = wp.media({
                    title: 'Seleccionar Logo para Emails',
                    button: { text: 'Usar este logo' },
                    multiple: false
                });

                logoFrame.on('select', function() {
                    const attachment = logoFrame.state().get('selection').first().toJSON();
                    saveEmailLogo(attachment.id, attachment.url);
                });

                logoFrame.open();
            });
        }

        if (removeLogoBtn) {
            removeLogoBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('¿Seguro que quieres quitar el logo personalizado de los emails?')) {
                    saveEmailLogo(0, null);
                }
            });
        }

        function saveEmailLogo(logoId, logoUrl) {
            const body = new URLSearchParams();
            body.set('action', 'pl_save_email_logo');
            body.set('nonce', nonce);
            body.set('logo_id', logoId);

            logoStatus.textContent = 'Guardando…';
            logoStatus.style.color = '#64748b';

            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    logoStatus.textContent = 'Guardado.';
                    logoStatus.style.color = '#10b981';
                    
                    // Update Preview
                    if (logoId && logoUrl) {
                        logoPreview.innerHTML = `<img src="${logoUrl}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
                        removeLogoBtn.style.display = 'inline-block';
                    } else {
                        logoPreview.innerHTML = '<span style="font-size: 10px; color: #94a3b8;">Sin logo</span>';
                        removeLogoBtn.style.display = 'none';
                    }
                    
                    window.setTimeout(() => { logoStatus.textContent = ''; }, 2000);
                } else {
                    logoStatus.textContent = 'Error al guardar.';
                    logoStatus.style.color = '#ef4444';
                }
            })
            .catch(() => {
                logoStatus.textContent = 'Error de conexión.';
                logoStatus.style.color = '#ef4444';
            });
        }
    });
</script>
