<?php
/**
 * Handles the User Course Creator Dashboard.
 */

if (!defined('ABSPATH'))
    exit;

class PL_CC_Creator_Dashboard
{

    const REWRITE_TAG = 'pcg_creator_user';
    const SECTION_VAR = 'cc_section';

    public function __construct()
    {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_filter('template_include', [$this, 'load_dashboard_template']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_escrito_frontend_assets'], 999);
        add_action('bp_setup_nav', [$this, 'add_bp_nav_item'], 100);

        // Shortcode as fallback or alternative
        add_shortcode('pcg_course_creator_dashboard', [$this, 'render_dashboard_shortcode']);

        // Wrap the frontend post content to guarantee 1:1 matching with Editor
        add_filter('the_content', [$this, 'wrap_escrito_content']);

        add_filter('body_class', [$this, 'add_dashboard_body_classes']);
    }

    /**
     * Add specific classes to the body when dashboard is active
     */
    public function add_dashboard_body_classes($classes)
    {
        if (get_query_var(self::REWRITE_TAG)) {
            $classes[] = 'pcg-operation-page';
            $op_template = get_option('pcg_operation_template', '/center');
            if ($op_template === '/center-2') {
                $classes[] = 'pcg-template-center-2-active';
            } else {
                $classes[] = 'pcg-template-center-active';
            }
        }
        return $classes;
    }

    /**
     * Frontend style for Escritos (single posts).
     * Enqueue late so it reliably overrides theme typography.
     */
    public function enqueue_escrito_frontend_assets(): void
    {
        if (is_single() && get_post_type() === 'post') {
            wp_enqueue_style('pcg-escrito-frontend-css', PL_CC_URL . 'assets/css/escrito-frontend.css', [], '1.0.9');
        }
    }

    /**
     * Wrap post content in our editor class for consistent styling
     */
    public function wrap_escrito_content($content)
    {
        if (is_singular('post') && in_the_loop() && is_main_query()) {
            return '<div class="pcg-escrito-content-editor">' . $content . '</div>';
        }
        return $content;
    }

    /**
     * Add custom rewrite rules for /members/{user}/center
     */
    public function add_rewrite_rules()
    {
        // Register both slugs to avoid needing a rewrite-flush when switching templates.
        foreach (['center', 'center-2'] as $slug) {
            add_rewrite_rule(
                'members/([^/]+)/' . preg_quote($slug, '/') . '/?$',
                'index.php?' . self::REWRITE_TAG . '=$matches[1]',
                'top'
            );
        }
    }

    private function resolve_user_slug_from_request_uri(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri === '') {
            return '';
        }

        $path = (string) wp_parse_url($uri, PHP_URL_PATH);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^/members/([^/]+)/(center|center-2)/?$#', $path, $m)) {
            return sanitize_key(rawurldecode((string) ($m[1] ?? '')));
        }

        return '';
    }

    /**
     * Register BuddyBoss profile tab
     */
    public function add_bp_nav_item()
    {
        if (!function_exists('bp_core_new_nav_item')) {
            return;
        }

        $op_template = get_option('pcg_operation_template', '/center');
        $slug = ltrim($op_template, '/');

        bp_core_new_nav_item([
            'name' => __('Center', 'politeia-learning'),
            'slug' => $slug,
            'position' => 10,
            'screen_function' => [$this, 'dashboard_screen'],
            'default_subnav_slug' => 'create-course',
            'item_css_id' => 'pcg-center'
        ]);
    }

    /**
     * Screen function for BuddyBoss tab
     */
    public function dashboard_screen()
    {
        $user_slug = bp_get_displayed_user_username();
        set_query_var(self::REWRITE_TAG, $user_slug);

        add_action('bp_template_content', function () {
            $this->render_dashboard_content();
        });

        // If we want it to look EXACTLY as it looks now (with get_header/get_footer),
        // we should probably NOT use bp_core_load_template which wraps it in profile template.
        // Instead, we can just load the template directly if it's the main page.
        $op_template = get_option('pcg_operation_template', '/center');
        $template_name = ($op_template === '/center-2') ? 'main-dashboard-2.php' : 'main-dashboard.php';
        
        $template = PL_CC_PATH . 'templates/' . $template_name;
        if (file_exists($template)) {
            load_template($template);
            exit;
        }
    }

    /**
     * Register custom query variables
     */
    public function add_query_vars($vars)
    {
        $vars[] = self::REWRITE_TAG;
        $vars[] = self::SECTION_VAR;
        return $vars;
    }

    /**
     * Load the dashboard template if the rewrite tag is present
     */
    public function load_dashboard_template($template)
    {
        $user_slug = get_query_var(self::REWRITE_TAG);
        if (empty($user_slug)) {
            // Fallback when rewrites haven't been flushed yet.
            $user_slug = $this->resolve_user_slug_from_request_uri();
            if ($user_slug !== '') {
                set_query_var(self::REWRITE_TAG, $user_slug);
            }
        }

        if (!empty($user_slug)) {
            $user = get_user_by('slug', $user_slug);

            if ($user) {
                // Check if the current user is authorized to view this dashboard
                // For now, let's allow the user to view their own dashboard
                $current_user_id = get_current_user_id();

                if ($current_user_id === $user->ID || current_user_can('manage_options')) {
                    $op_template = get_option('pcg_operation_template', '/center');
                    $template_name = ($op_template === '/center-2') ? 'main-dashboard-2.php' : 'main-dashboard.php';
                    
                    $custom_template = PL_CC_PATH . 'templates/' . $template_name;
                    if (file_exists($custom_template)) {
                        return $custom_template;
                    }
                }
            }

            return $template;
        }

        return $template;
    }

    /**
     * Enqueue CSS and JS for the dashboard
     */
    public function enqueue_assets()
    {
        if (get_query_var(self::REWRITE_TAG)) {
            wp_enqueue_media();

            // Load Cropper.js from a stable CDN instead of the BuddyBoss path.
            // The BuddyBoss plugin is not present in this install, so the old URL
            // silently failed and the cropper modal never became interactive.
            wp_enqueue_style(
                'cropperjs',
                'https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css',
                [],
                '1.5.13'
            );
            wp_enqueue_script(
                'cropperjs',
                'https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js',
                ['jquery'],
                '1.5.13',
                true
            );

            wp_enqueue_style(
                'pcg-material-symbols-outlined',
                'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=more_horiz',
                [],
                null
            );

            wp_enqueue_style('pcg-creator-css', PL_CC_URL . 'assets/css/creator-dashboard.css', [], '1.0.19');
            wp_enqueue_style('pcg-cropper-css', PL_CC_URL . 'assets/css/pcg-cropper.css', ['cropperjs'], '1.0.0');


            // Inject Custom Styles from Admin Options
            $creator_max_width = get_option('pcg_creator_max_width', '1400px');
            $container_max_width = get_option('pcg_container_max_width', '1200px');

            $custom_css = "
                .pcg-creator-container { max-width: {$creator_max_width} !important; }
                .container { max-width: {$container_max_width} !important; }
                .pcg-creator-dashboard-wrapper { padding: 0px !important; }
                div#content { padding-left: 0px !important; padding-right: 0px !important; }
            ";
            wp_add_inline_style('pcg-creator-css', $custom_css);

            wp_enqueue_script('pcg-cropper-js', PL_CC_URL . 'assets/js/pcg-course-cropper.js', ['jquery', 'cropperjs'], '1.0.0', true);
            wp_enqueue_script('pcg-creator-js', PL_CC_URL . 'assets/js/creator-dashboard.js', ['jquery', 'jquery-ui-sortable', 'pcg-cropper-js'], '1.0.15', true);
            wp_enqueue_script('pcg-chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], '4.4.1', true);
            wp_enqueue_script('pcg-sales-dashboard', PL_CC_URL . 'assets/js/pcg-sales-dashboard.js', ['pcg-chartjs'], '1.0.0', true);
            wp_enqueue_script('pcg-sales-list', PL_CC_URL . 'assets/js/pcg-sales-list.js', [], '1.0.2', true);
            wp_enqueue_script('pcg-students-dashboard', PL_CC_URL . 'assets/js/pcg-students-dashboard.js', ['pcg-chartjs'], '1.0.2', true);
            wp_enqueue_script('pcg-students-rankings', PL_CC_URL . 'assets/js/pcg-students-rankings.js', [], '1.0.0', true);

            wp_localize_script('pcg-sales-dashboard', 'pcgSalesData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action' => 'pl_get_user_sales_metrics',
                'nonce' => wp_create_nonce('pl_user_sales_metrics'),
                'i18n' => [
                    'productsSold' => __('PRODUCTOS VENDIDOS', 'politeia-learning'),
                    'coursesSold' => __('CURSOS VENDIDOS', 'politeia-learning'),
                    'booksSold' => __('LIBROS VENDIDOS', 'politeia-learning'),
                    'supportSold' => __('APOYOS VENDIDOS', 'politeia-learning'),
                ],
            ]);

            wp_localize_script('pcg-sales-list', 'pcgSalesListData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action' => 'pl_get_user_sales_table',
                'nonce' => wp_create_nonce('pl_user_sales_table'),
                'i18n' => [
                    'paid' => __('Pagado', 'politeia-learning'),
                    'pending' => __('Pendiente', 'politeia-learning'),
                    'refunded' => __('Reembolsado', 'politeia-learning'),
                    'unknown' => __('Desconocido', 'politeia-learning'),
                ],
            ]);

            wp_localize_script('pcg-students-dashboard', 'pcgStudentsData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action' => 'pl_get_user_student_metrics',
                'nonce' => wp_create_nonce('pl_user_student_metrics'),
                'studentDetailAction' => 'pl_get_user_student_detail',
                'studentDetailNonce' => wp_create_nonce('pl_user_student_detail'),
            ]);

            wp_localize_script('pcg-students-rankings', 'pcgStudentsRankingsData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action' => 'pl_get_user_student_rankings',
                'nonce' => wp_create_nonce('pl_user_student_rankings'),
                'i18n' => [
                    'loading' => __('Cargando...', 'politeia-learning'),
                    'empty' => __('Sin datos', 'politeia-learning'),
                    'errorLoading' => __('Error al cargar', 'politeia-learning'),
                ],
            ]);

            $current_user = wp_get_current_user();
            $full_name = trim($current_user->first_name . ' ' . $current_user->last_name);
            if (empty($full_name)) {
                $full_name = $current_user->display_name;
            }

            wp_localize_script('pcg-creator-js', 'pcgCreatorData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pcg_creator_nonce'),
                'teacherSearchAction' => 'pcg_search_teachers',
                'teacherSearchNonce' => wp_create_nonce('pcg_search_teachers_nonce'),
                'currentUserId' => $current_user->ID,
                'currentUserName' => $full_name . ' (' . $current_user->user_email . ')',
                'currentUserAvatar' => get_avatar_url($current_user->ID, ['size' => 64]),
                'currentUserFullNameEmail' => $full_name . ' (' . $current_user->user_email . ')',
                // BuddyPress/BuddyBoss optional: provide safe defaults when not installed.
                'avatarFullWidth' => function_exists('bp_core_avatar_full_width') ? bp_core_avatar_full_width() : 150,
                'avatarFullHeight' => function_exists('bp_core_avatar_full_height') ? bp_core_avatar_full_height() : 150,
                'i18n' => [
                    'loadingCourses' => __('Cargando cursos...', 'politeia-learning'),
                    'loading' => __('Cargando...', 'politeia-learning'),
                    'loadingCourse' => __('Cargando curso...', 'politeia-learning'),
                    'loadingSpecialization' => __('Cargando especialización...', 'politeia-learning'),
                    'loadingProgram' => __('Cargando programa...', 'politeia-learning'),
                    'noCoursesToAssign' => __('No tienes cursos para asignar.', 'politeia-learning'),
                    'noCoursesAddedYet' => __('Aún no has agregado cursos.', 'politeia-learning'),
                    'noCourses' => __('No hay cursos.', 'politeia-learning'),
                    'failedToLoadCourses' => __('No se pudieron cargar los cursos.', 'politeia-learning'),
                    'errorLoadingSpecialization' => __('Error al cargar la especialización.', 'politeia-learning'),
                    'errorLoadingSpecializationGeneric' => __('Ocurrió un error al cargar la especialización.', 'politeia-learning'),
                    'pleaseEnterSpecializationName' => __('Por favor, ingresa un nombre para la especialización.', 'politeia-learning'),
                    'errorSavingSpecialization' => __('Ocurrió un error al guardar la especialización.', 'politeia-learning'),
                    'confirmDeleteSpecialization' => __('¿Estás seguro de que deseas eliminar esta especialización? Esta acción no se puede deshacer.', 'politeia-learning'),
                    'errorDeletingSpecialization' => __('Ocurrió un error al eliminar la especialización.', 'politeia-learning'),
                    'noSpecializationsAddedYet' => __('Aún no has agregado especializaciones.', 'politeia-learning'),
                    'noSpecializations' => __('No hay especializaciones.', 'politeia-learning'),
                    'noSpecializationsYet' => __('No tienes especializaciones.', 'politeia-learning'),
                    'failedToLoadSpecializations' => __('No se pudieron cargar las especializaciones.', 'politeia-learning'),
                    'errorLoadingProgram' => __('Error al cargar el programa.', 'politeia-learning'),
                    'errorLoadingProgramGeneric' => __('Ocurrió un error al cargar el programa.', 'politeia-learning'),
                    'pleaseEnterProgramName' => __('Por favor, ingresa un nombre para el programa.', 'politeia-learning'),
                    'errorSavingProgram' => __('Ocurrió un error al guardar el programa.', 'politeia-learning'),
                    'confirmDeleteProgram' => __('¿Estás seguro de que deseas eliminar este programa? Esta acción no se puede deshacer.', 'politeia-learning'),
                    'errorDeletingProgram' => __('Ocurrió un error al eliminar el programa.', 'politeia-learning'),
                    'remove' => __('Quitar', 'politeia-learning'),
                    'delete' => __('Eliminar', 'politeia-learning'),
                    'removeItem' => __('Remove', 'politeia-learning'),
                    'addText' => __('Add Text', 'politeia-learning'),
                    'createTag' => __('Crear etiqueta', 'politeia-learning'),
                    'noCategories' => __('No hay categorías.', 'politeia-learning'),
                    'unknownError' => __('Error desconocido', 'politeia-learning'),
                    'couldNotDelete' => __('No se pudo eliminar.', 'politeia-learning'),
                    'errorPrefix' => __('Error: ', 'politeia-learning'),
                    'errorGettingCourseData' => __('Error al obtener los datos del curso: ', 'politeia-learning'),
                    'pleaseEnterCourseTitle' => __('Por favor, ingresa un título para el curso.', 'politeia-learning'),
                    'errorUploadingImage' => __('Ocurrió un error al subir la imagen.', 'politeia-learning'),
                    'errorSavingCourse' => __('Ocurrió un error al guardar el curso.', 'politeia-learning'),
                    'errorLoadingCourseGeneric' => __('Ocurrió un error al cargar el curso para editar.', 'politeia-learning'),
                    'confirmDeleteCourse' => __('¿Estás seguro de que deseas eliminar este curso? Esta acción no se puede deshacer.', 'politeia-learning'),
                    'words' => __('palabras', 'politeia-learning'),
                    'noPublishedCoursesYet' => __('No has publicado cursos aún.', 'politeia-learning'),
                    'createYourSpecialization' => __('CREA TU ESPECIALIZACIÓN', 'politeia-learning'),
                    'createYourProgram' => __('CREA TU PROGRAMA', 'politeia-learning'),
                    'roleSlugPlaceholder' => __('Ej: Editor de video, Diseñador...', 'politeia-learning'),
                    'participationLabel' => __('Participación (%)', 'politeia-learning'),
                    'roleDescriptionLabel' => __('Descripción del rol', 'politeia-learning'),
                    'newSection' => __('Nueva Sección', 'politeia-learning'),
                    'newLesson' => __('Nueva Lección', 'politeia-learning'),
                    'viewDetails' => __('Ver detalles', 'politeia-learning'),
                    'expandDetails' => __('Expand Details', 'politeia-learning'),
                    'searchCollaborator' => __('Buscar colaborador...', 'politeia-learning'),
                    'mainAuthor' => __('Principal', 'politeia-learning'),
                    'mainAuthorRoleSlug' => __('Autor principal', 'politeia-learning'),
                    'role' => __('Rol', 'politeia-learning'),
                    'youtubeUrl' => __('YouTube URL', 'politeia-learning'),
                    'availableOn' => __('Disponible en', 'politeia-learning'),
                    'describeResponsibilities' => __('Describe las responsabilidades...', 'politeia-learning'),
                    'noCollaboratorsAssigned' => __('No hay colaboradores asignados.', 'politeia-learning'),
                    'approvalRequestSent' => __('Solicitud enviada. Esta publicación quedará en borrador hasta que todos los participantes aprueben.', 'politeia-learning'),
                    'requestedBy' => __('Solicitado por:', 'politeia-learning'),
                    'approve' => __('Aprobar', 'politeia-learning'),
                    'reject' => __('Rechazar', 'politeia-learning'),
                    'confirmReject' => __('¿Seguro que quieres rechazar esta propuesta?', 'politeia-learning'),
                    'approvalActionFailed' => __('No se pudo completar la acción. Intenta nuevamente.', 'politeia-learning'),
                    'pendingApproval' => __('Pendiente de aprobación', 'politeia-learning'),
                    'pendingApprovalNotice' => __('Este contenido está pendiente de aprobación y aún no está publicado.', 'politeia-learning'),
                    'waitingApproval' => __('Esperando aprobación', 'politeia-learning'),
                    'courseCover' => __('Portada del curso', 'politeia-learning'),
                    'coverPhoto' => __('Foto de portada', 'politeia-learning'),
                    'edit' => __('Editar', 'politeia-learning'),
                    'lessons' => __('Lecciones', 'politeia-learning'),
                    'courseSingular' => __('curso', 'politeia-learning'),
                    'coursesPlural' => __('cursos', 'politeia-learning'),
                    'groupSingular' => __('grupo', 'politeia-learning'),
                    'groupsPlural' => __('grupos', 'politeia-learning'),
                    'added' => __('Agregado', 'politeia-learning'),
                    'add' => __('Agregar', 'politeia-learning'),
                    'loadingEscritos' => __('Cargando escritos...', 'politeia-learning'),
                    'noEscritosYet' => __('No has publicado escritos aún.', 'politeia-learning'),
                    'pleaseEnterEscritoTitle' => __('Por favor, ingresa un título para el escrito.', 'politeia-learning'),
                    'errorSavingEscrito' => __('Ocurrió un error al guardar el escrito.', 'politeia-learning'),
                ],
            ]);

            // Cropper modal runs before pcgCreatorData is localized, so give it its own i18n payload.
            wp_localize_script('pcg-cropper-js', 'pcgCropperData', [
                'i18n' => [
                    'uploadImage' => __('Subir imagen', 'politeia-learning'),
                    'dragDropHere' => __('Arrastra y suelta tu imagen aquí', 'politeia-learning'),
                    'clickToBrowse' => __('o haz clic para buscar archivos', 'politeia-learning'),
                    'recommendedSize' => __('Tamaño recomendado:', 'politeia-learning'),
                    'cancel' => __('Cancelar', 'politeia-learning'),
                    'saveImage' => __('Guardar imagen', 'politeia-learning'),
                    'saving' => __('Guardando...', 'politeia-learning'),
                    'selectImageFile' => __('Por favor selecciona un archivo de imagen (JPG o PNG).', 'politeia-learning'),
                ],
            ]);
        }

        // Frontend styles for Escritos are enqueued via enqueue_escrito_frontend_assets().
    }


    /**
     * Shortcode renderer (as alternative)
     */
    public function render_dashboard_shortcode($atts)
    {
        ob_start();
        $this->render_dashboard_content();
        return ob_get_clean();
    }

    /**
     * Helper to render the dashboard content
     */
    public function render_dashboard_content()
    {
        $user_slug = get_query_var(self::REWRITE_TAG);
        $user = get_user_by('slug', $user_slug);
        $section = get_query_var(self::SECTION_VAR, 'overview');

        if (!$user)
            return;

        $op_template = get_option('pcg_operation_template', '/center');
        $template_name = ($op_template === '/center-2') ? 'main-dashboard-2.php' : 'main-dashboard.php';

        include PL_CC_PATH . 'templates/' . $template_name;
    }
}
