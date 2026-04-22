<?php
/**
 * Course Creator Main Template
 * 
 * This file is now an orchestrator that includes modular partials.
 * Following the 500-line rule for maintainability in Learni.
 */
if (!defined('ABSPATH'))
    exit;

$pcg_is_editing_quiz = isset($_GET['edit_quiz']) && !empty($_GET['edit_quiz']);
$pcg_active_segment = $pcg_is_editing_quiz ? 'evaluacion' : 'curso';

$current_course_id = 0;
if ($pcg_is_editing_quiz && class_exists('PQC_Quiz_Creator') && method_exists('PQC_Quiz_Creator', 'get_course_id_by_quiz_id')) {
    $current_course_id = (int) PQC_Quiz_Creator::get_course_id_by_quiz_id((int) $_GET['edit_quiz']);
}
?>

<!-- Creation Form (Hidden Initially) -->
<div id="pcg-course-form-section" class="pcg-create-course-container" <?php echo $pcg_is_editing_quiz ? 'style="display:block;"' : 'style="display:none;"'; ?>>
    <input type="hidden" id="pcg-current-course-id" value="<?php echo esc_attr($current_course_id); ?>">

    <?php 
    // Navigation & Segments
    include __DIR__ . '/course/nav.php'; 

    // Different Edit Modes
    include __DIR__ . '/course/mode-curso.php'; 
    include __DIR__ . '/course/mode-lecciones.php';
    include __DIR__ . '/course/picker-escritos.php';
    include __DIR__ . '/course/mode-evaluacion.php';
    include __DIR__ . '/course/mode-certificado.php';
    include __DIR__ . '/course/mode-meta.php';
    ?>
</div>

<?php 
// Dashboard Home List
include __DIR__ . '/course/list-my-courses.php'; 
?>
