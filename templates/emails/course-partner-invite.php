<?php
/**
 * Email: course partner invite
 *
 * Available variables:
 * - $invitee_name (string)
 * - $inviter_name (string)
 * - $course_name  (string)
 * - $accept_url   (string)
 */

$invitee_name = isset($invitee_name) ? (string) $invitee_name : '';
$inviter_name = isset($inviter_name) ? (string) $inviter_name : 'Politeia';
$course_name = isset($course_name) ? (string) $course_name : '';
$accept_url = isset($accept_url) ? (string) $accept_url : '';
?>
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.5; color: #111827;">
    <h2 style="margin: 0 0 12px; font-size: 18px;">
        <?php echo esc_html__('Course Invitation', 'politeia-learning'); ?>
    </h2>

    <?php if (trim($invitee_name) !== '') : ?>
        <p style="margin: 0 0 12px;">
            <?php
            echo esc_html(
                sprintf(
                    /* translators: 1: invitee name */
                    __('Hi %1$s,', 'politeia-learning'),
                    $invitee_name
                )
            );
            ?>
        </p>
    <?php endif; ?>

    <p style="margin: 0 0 12px;">
        <?php
        echo esc_html(
            sprintf(
                /* translators: 1: inviter name, 2: course name */
                __('%1$s invited you to join the course "%2$s" as a partner.', 'politeia-learning'),
                $inviter_name,
                $course_name !== '' ? $course_name : __('(course)', 'politeia-learning')
            )
        );
        ?>
    </p>

    <p style="margin: 0 0 12px;">
        <a href="<?php echo esc_url($accept_url); ?>"
            style="display: inline-block; padding: 10px 14px; background: #111827; color: #ffffff; text-decoration: none; border-radius: 10px;">
            <?php echo esc_html__('Accept invitation', 'politeia-learning'); ?>
        </a>
    </p>

    <p style="margin: 18px 0 0; color: #6b7280; font-size: 12px;">
        <?php echo esc_html__('If you did not expect this email, you can ignore it.', 'politeia-learning'); ?>
    </p>
</div>
