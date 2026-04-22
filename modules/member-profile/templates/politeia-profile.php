<?php
/**
 * Template Name: Politeia Profile
 * Description: A modern portfolio dashboard for member profiles with native header and footer.
 */

if (!defined('ABSPATH')) exit;

include __DIR__ . '/parts/profile-logic-data.php';
include __DIR__ . '/parts/profile-logic-queries.php';

/**
 * Template shell
 */
pl_template_open();
?>

<!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@latest"></script>
	<!-- Fonts -->
	<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Inter:wght@400;500;600;700;900&family=Newsreader:opsz,wght@6..72,300&display=swap" rel="stylesheet">

    <?php include __DIR__ . '/parts/profile-styles.php'; ?>


<?php include __DIR__ . '/parts/profile-layout.php'; ?>

<?php include __DIR__ . '/parts/profile-scripts.php'; ?>

<?php pl_template_close(); ?>
