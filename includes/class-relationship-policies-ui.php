<?php
/**
 * Per-user relationship policies UI (pure WordPress).
 *
 * Each user can configure what tabs are visible for:
 * - public visitors
 * - followers (accepted)
 * - friends (accepted)
 * - subscribers (active subscription)
 *
 * Also allows setting the subscription period in days (used to compute expires_at).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Relationship_Policies_UI
{
    private const NONCE_ACTION = 'pl_relationship_policies_save';

    public static function init(): void
    {
        add_action('show_user_profile', [__CLASS__, 'render']);
        add_action('edit_user_profile', [__CLASS__, 'render']);
        add_action('personal_options_update', [__CLASS__, 'save']);
        add_action('edit_user_profile_update', [__CLASS__, 'save']);
    }

    public static function render(WP_User $user): void
    {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $tabs = [
            'main' => 'Inicio',
            'courses' => 'Mis Cursos',
            'writings' => 'Escritos',
            'specializations' => 'Especializaciones',
            'thoughts' => 'Feed de Pensamientos',
            'plans' => 'Planes',
            'book' => 'Libros',
        ];

        $public = PL_Relationships::get_owner_policy((int) $user->ID, 'public');
        $follow = PL_Relationships::get_owner_policy((int) $user->ID, PL_Relationships::TYPE_FOLLOW);
        $friend = PL_Relationships::get_owner_policy((int) $user->ID, PL_Relationships::TYPE_FRIEND);
        $subscribe = PL_Relationships::get_owner_policy((int) $user->ID, PL_Relationships::TYPE_SUBSCRIBE);

        $public_tabs = is_array($public['profile_tabs'] ?? null) ? $public['profile_tabs'] : [];
        $follow_tabs = is_array($follow['profile_tabs'] ?? null) ? $follow['profile_tabs'] : [];
        $friend_tabs = is_array($friend['profile_tabs'] ?? null) ? $friend['profile_tabs'] : [];
        $subscribe_tabs = is_array($subscribe['profile_tabs'] ?? null) ? $subscribe['profile_tabs'] : [];

        $period_days = absint(get_user_meta((int) $user->ID, PL_Relationships::META_SUBSCRIBE_PERIOD_DAYS, true));
        if ($period_days <= 0) {
            $period_days = 30;
        }

        $pending = PL_Relationships::get_pending_requests_for_owner((int) $user->ID);

        ?>
        <h2><?php echo esc_html__('Relaciones y permisos', 'politeia-learning'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="pl_subscribe_period_days"><?php echo esc_html__('Duración de suscripción (días)', 'politeia-learning'); ?></label></th>
                <td>
                    <input type="number" min="1" step="1" class="small-text" id="pl_subscribe_period_days" name="pl_subscribe_period_days" value="<?php echo esc_attr((string) $period_days); ?>" />
                    <p class="description"><?php echo esc_html__('Se usa para calcular la fecha de expiración de una suscripción pagada.', 'politeia-learning'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php echo esc_html__('Visibilidad del perfil (/profile/{username})', 'politeia-learning'); ?></h3>
        <p class="description"><?php echo esc_html__('Selecciona qué pestañas pueden ver según el tipo de relación.', 'politeia-learning'); ?></p>

        <?php wp_nonce_field(self::NONCE_ACTION, '_pl_rel_policies_nonce'); ?>

        <table class="widefat striped" style="max-width: 900px;">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Pestaña', 'politeia-learning'); ?></th>
                    <th><?php echo esc_html__('Público', 'politeia-learning'); ?></th>
                    <th><?php echo esc_html__('Follow', 'politeia-learning'); ?></th>
                    <th><?php echo esc_html__('Friend', 'politeia-learning'); ?></th>
                    <th><?php echo esc_html__('Subscribe', 'politeia-learning'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tabs as $key => $label): ?>
                    <tr>
                        <td><?php echo esc_html($label); ?></td>
                        <td><input type="checkbox" name="pl_policy_public_tabs[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $public_tabs, true)); ?> /></td>
                        <td><input type="checkbox" name="pl_policy_follow_tabs[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $follow_tabs, true)); ?> /></td>
                        <td><input type="checkbox" name="pl_policy_friend_tabs[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $friend_tabs, true)); ?> /></td>
                        <td><input type="checkbox" name="pl_policy_subscribe_tabs[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $subscribe_tabs, true)); ?> /></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3 style="margin-top: 24px;"><?php echo esc_html__('Solicitudes pendientes', 'politeia-learning'); ?></h3>
        <?php if ($pending === []): ?>
            <p class="description"><?php echo esc_html__('No hay solicitudes pendientes.', 'politeia-learning'); ?></p>
        <?php else: ?>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Usuario', 'politeia-learning'); ?></th>
                        <th><?php echo esc_html__('Tipo', 'politeia-learning'); ?></th>
                        <th><?php echo esc_html__('Fecha', 'politeia-learning'); ?></th>
                        <th><?php echo esc_html__('Acciones', 'politeia-learning'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $req):
                        $from_id = (int) ($req['from_user_id'] ?? 0);
                        $u = $from_id > 0 ? get_userdata($from_id) : null;
                        $name = ($u instanceof WP_User) ? ($u->display_name ?: $u->user_login) : ('User #' . $from_id);
                        $type = (string) ($req['rel_type'] ?? '');
                        $created = (string) ($req['created_at'] ?? '');
                        ?>
                        <tr>
                            <td><?php echo esc_html($name); ?></td>
                            <td><?php echo esc_html($type); ?></td>
                            <td><?php echo esc_html($created); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                                    <?php wp_nonce_field('pl_relationship_respond'); ?>
                                    <input type="hidden" name="action" value="pl_relationship_respond" />
                                    <input type="hidden" name="request_id" value="<?php echo esc_attr((string) ($req['id'] ?? 0)); ?>" />
                                    <input type="hidden" name="decision" value="accept" />
                                    <input type="submit" class="button button-primary" value="<?php echo esc_attr__('Aceptar', 'politeia-learning'); ?>" />
                                </form>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-left:6px;">
                                    <?php wp_nonce_field('pl_relationship_respond'); ?>
                                    <input type="hidden" name="action" value="pl_relationship_respond" />
                                    <input type="hidden" name="request_id" value="<?php echo esc_attr((string) ($req['id'] ?? 0)); ?>" />
                                    <input type="hidden" name="decision" value="reject" />
                                    <input type="submit" class="button" value="<?php echo esc_attr__('Rechazar', 'politeia-learning'); ?>" />
                                </form>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-left:6px;">
                                    <?php wp_nonce_field('pl_relationship_block'); ?>
                                    <input type="hidden" name="action" value="pl_relationship_block" />
                                    <input type="hidden" name="blocked_user_id" value="<?php echo esc_attr((string) $from_id); ?>" />
                                    <input type="submit" class="button button-secondary" value="<?php echo esc_attr__('Bloquear', 'politeia-learning'); ?>" />
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    public static function save(int $user_id): void
    {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        if (!isset($_POST['_pl_rel_policies_nonce']) || !wp_verify_nonce((string) $_POST['_pl_rel_policies_nonce'], self::NONCE_ACTION)) {
            return;
        }

        $period_days = isset($_POST['pl_subscribe_period_days']) ? absint($_POST['pl_subscribe_period_days']) : 30;
        if ($period_days <= 0) {
            $period_days = 30;
        }
        update_user_meta($user_id, PL_Relationships::META_SUBSCRIBE_PERIOD_DAYS, $period_days);

        $public_tabs = self::sanitize_tabs($_POST['pl_policy_public_tabs'] ?? []);
        $follow_tabs = self::sanitize_tabs($_POST['pl_policy_follow_tabs'] ?? []);
        $friend_tabs = self::sanitize_tabs($_POST['pl_policy_friend_tabs'] ?? []);
        $subscribe_tabs = self::sanitize_tabs($_POST['pl_policy_subscribe_tabs'] ?? []);

        update_user_meta($user_id, PL_Relationships::META_POLICY_PUBLIC, ['profile_tabs' => $public_tabs]);
        update_user_meta($user_id, PL_Relationships::META_POLICY_FOLLOW, ['profile_tabs' => $follow_tabs]);
        update_user_meta($user_id, PL_Relationships::META_POLICY_FRIEND, ['profile_tabs' => $friend_tabs]);
        update_user_meta($user_id, PL_Relationships::META_POLICY_SUBSCRIBE, ['profile_tabs' => $subscribe_tabs]);
    }

    /**
     * @param mixed $raw
     * @return array<int,string>
     */
    private static function sanitize_tabs($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $tabs = array_values(array_unique(array_filter(array_map('sanitize_key', $raw))));
        return $tabs;
    }
}

