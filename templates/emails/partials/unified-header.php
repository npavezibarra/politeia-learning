<?php
/**
 * Unified email header (copied from password-reset.php).
 *
 * Expected variables:
 * - $pl_email_header_title (string) Header title (left).
 * - $logo_url              (string) Logo URL.
 */

if (!defined('ABSPATH')) {
    exit;
}

$pl_email_header_title = isset($pl_email_header_title) ? (string) $pl_email_header_title : '';
$logo_url = isset($logo_url) ? (string) $logo_url : '';
?>
<tr>
    <td style="padding:0 0 20px; border-bottom: 1px solid #e5e7eb;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td align="left" class="poppins" style="font-size:11px;font-weight:700;color:#000000;text-transform:uppercase;letter-spacing:2px;">
                    <?php echo esc_html($pl_email_header_title); ?>
                </td>
                <td align="right" class="poppins" style="font-size:16px;font-weight:700;color:#000000;">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr__('Politeia', 'politeia-learning'); ?>" width="120" style="display:inline-block; border:0; outline:none; text-decoration:none; width:120px; max-width: 45%; height:auto;">
                </td>
            </tr>
        </table>
    </td>
</tr>

