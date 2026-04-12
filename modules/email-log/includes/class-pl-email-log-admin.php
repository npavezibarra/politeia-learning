<?php
/**
 * Admin UI for Email Log.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Email_Log_Admin
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_pages'], 110);
        add_action('wp_ajax_pl_get_email_content', [$this, 'ajax_get_email_content']);
    }

    public function register_admin_pages()
    {
        $parent_slug = 'politeia-learning';

        add_submenu_page(
            $parent_slug,
            __('Registro de Emails', 'politeia-learning'),
            __('Email Log', 'politeia-learning'),
            'manage_options',
            'pl-email-log',
            [$this, 'render_admin_page']
        );
    }

    /**
     * AJAX handler to get email content by ID.
     */
    public function ajax_get_email_content()
    {
        check_ajax_referer('pl_email_log_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!$id) {
            wp_send_json_error('Invalid ID');
        }

        $log = PL_Email_Log_DB::get_instance()->get_log($id);
        if (!$log) {
            wp_send_json_error('Log not found');
        }

        echo (string) $log->content;
        exit;
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para ver esta página.', 'politeia-learning'));
        }

        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $db = PL_Email_Log_DB::get_instance();
        $logs = $db->get_logs($per_page, $offset, $search);
        $total_count = $db->get_total_count($search);
        $total_pages = (int) ceil($total_count / $per_page);

        $nonce = wp_create_nonce('pl_email_log_nonce');

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Registro de Emails', 'politeia-learning'); ?></h1>
            <hr class="wp-header-end">

            <div class="pl-email-log-header" style="margin-top: 20px; display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
                <p><?php echo esc_html__('Aquí se registran todos los correos enviados desde el sitio.', 'politeia-learning'); ?></p>
                <form method="get">
                    <input type="hidden" name="page" value="pl-email-log">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Buscar destinatario o asunto...', 'politeia-learning'); ?>">
                    <button type="submit" class="button"><?php echo esc_html__('Buscar', 'politeia-learning'); ?></button>
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 15%;"><?php echo esc_html__('A quién', 'politeia-learning'); ?></th>
                        <th style="width: 25%;"><?php echo esc_html__('Asunto', 'politeia-learning'); ?></th>
                        <th style="width: 10%;"><?php echo esc_html__('Tipo', 'politeia-learning'); ?></th>
                        <th style="width: 35%;"><?php echo esc_html__('Archivo', 'politeia-learning'); ?></th>
                        <th style="width: 10%;"><?php echo esc_html__('Fecha y Hora', 'politeia-learning'); ?></th>
                        <th style="width: 5%;"><?php echo esc_html__('Acción', 'politeia-learning'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><strong><?php echo esc_html($log->recipient); ?></strong></td>
                                <td><?php echo esc_html($log->subject); ?></td>
                                <td>
                                    <span class="pl-badge type-<?php echo esc_attr(strtolower($log->email_type)); ?>">
                                        <?php echo esc_html($log->email_type); ?>
                                    </span>
                                </td>
                                <td>
                                    <code style="font-size: 10px; background: #f1f5f9; padding: 2px 4px; border-radius: 3px; color: #64748b;">
                                        <?php echo esc_html($log->file_path ?: __('Desconocido', 'politeia-learning')); ?>
                                    </code>
                                </td>
                                <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime((string) $log->sent_at))); ?></td>
                                <td>
                                    <button type="button" class="button pl-view-email" data-id="<?php echo esc_attr($log->id); ?>">
                                        <?php echo esc_html__('Ver', 'politeia-learning'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6"><?php echo esc_html__('No se encontraron correos registrados.', 'politeia-learning'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo esc_html(sprintf(__('%s elementos', 'politeia-learning'), (string) $total_count)); ?></span>
                        <span class="pagination-links">
                            <?php if ($paged > 1): ?>
                                <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $paged - 1)); ?>">&lsaquo;</a>
                            <?php endif; ?>
                            <span class="paging-input">
                                <span class="current-page"><?php echo esc_html((string) $paged); ?></span>
                                <?php echo esc_html__('de', 'politeia-learning'); ?>
                                <span class="total-pages"><?php echo esc_html((string) $total_pages); ?></span>
                            </span>
                            <?php if ($paged < $total_pages): ?>
                                <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $paged + 1)); ?>">&rsaquo;</a>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <div id="pl-email-overlay" class="pl-overlay" style="display:none;">
                <div class="pl-modal">
                    <div class="pl-modal-header">
                        <h3><?php echo esc_html__('Visualizar Correo', 'politeia-learning'); ?></h3>
                        <button type="button" class="pl-close-modal" aria-label="<?php echo esc_attr__('Cerrar', 'politeia-learning'); ?>">&times;</button>
                    </div>
                    <div class="pl-modal-body">
                        <iframe id="pl-email-frame" style="width: 100%; height: 100%; border: none;"></iframe>
                    </div>
                </div>
            </div>

            <style>
                .pl-badge {
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    background: #f0f0f1;
                    color: #50575e;
                }
                .type-woocommerce { background: #EBDCF2; color: #763F98; }
                .type-contacto { background: #DFF1E4; color: #1E6C3B; }
                .type-viajes { background: #DFF1FB; color: #155E8D; }
                .type-registro { background: #FEF3C7; color: #92400E; }
                .type-cuenta { background: #FCE7F3; color: #9D174D; }

                .pl-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.8);
                    z-index: 99999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    backdrop-filter: blur(5px);
                }
                .pl-modal {
                    background: #fff;
                    width: 90%;
                    max-width: 900px;
                    height: 85%;
                    border-radius: 12px;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
                }
                .pl-modal-header {
                    padding: 15px 25px;
                    background: #f8fafc;
                    border-bottom: 1px solid #e2e8f0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .pl-modal-header h3 { margin: 0; font-size: 1.1rem; color: #1e293b; }
                .pl-close-modal {
                    background: none;
                    border: none;
                    font-size: 28px;
                    cursor: pointer;
                    color: #94a3b8;
                    transition: color 0.2s;
                }
                .pl-close-modal:hover { color: #ef4444; }
                .pl-modal-body { flex-grow: 1; background: #f1f5f9; }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const overlay = document.getElementById('pl-email-overlay');
                    const frame = document.getElementById('pl-email-frame');
                    const closeBtn = document.querySelector('.pl-close-modal');

                    document.querySelectorAll('.pl-view-email').forEach(btn => {
                        btn.onclick = function() {
                            const id = this.getAttribute('data-id');
                            frame.src = 'about:blank';
                            overlay.style.display = 'flex';

	                            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=pl_get_email_content&id=' + id + '&nonce=<?php echo $nonce; ?>')
                                .then(response => response.text())
                                .then(html => {
                                    const doc = frame.contentWindow.document;
                                    doc.open();
                                    doc.write(html);
                                    doc.close();
                                });
                        };
                    });

                    closeBtn.onclick = function() {
                        overlay.style.display = 'none';
                        frame.src = 'about:blank';
                    };

                    overlay.onclick = function(e) {
                        if (e.target === overlay) {
                            closeBtn.onclick();
                        }
                    };
                });
            </script>
        </div>
        <?php
    }
}
