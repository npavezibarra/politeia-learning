<?php
/**
 * Trait for Dashboard Assets and UI integration in Course Creator.
 */

if (!defined('ABSPATH'))
    exit;

trait PL_CC_Dashboard_Assets_Trait
{
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
            $frontend_css_path = PL_CC_PATH . 'assets/dashboard/css/escrito-frontend.css';
            $frontend_css_ver = file_exists($frontend_css_path) ? (string) filemtime($frontend_css_path) : '1.0.10';
            wp_enqueue_style('pcg-escrito-frontend-css', PL_CC_URL . 'assets/dashboard/css/escrito-frontend.css', [], $frontend_css_ver);
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
     * Enqueue CSS and JS for the dashboard
     */
    public function enqueue_assets()
    {
        if (get_query_var(self::REWRITE_TAG)) {
            wp_enqueue_media();

            // Load Cropper.js from a stable CDN instead of the BuddyBoss path.
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

            wp_enqueue_script(
                'lucide-icons',
                'https://unpkg.com/lucide@latest/dist/umd/lucide.js',
                [],
                null,
                true
            );

            $creator_css_path = PL_CC_PATH . 'assets/dashboard/css/creator-dashboard.css';
            $creator_js_path = PL_CC_PATH . 'assets/dashboard/js/creator-dashboard.js';
            $creator_css_ver = file_exists($creator_css_path) ? (string) filemtime($creator_css_path) : '1.0.19';
            $creator_js_ver = file_exists($creator_js_path) ? (string) filemtime($creator_js_path) : '1.0.15';

            wp_enqueue_style('pcg-creator-css', PL_CC_URL . 'assets/dashboard/css/creator-dashboard.css', [], $creator_css_ver);
            wp_enqueue_style('pcg-cropper-css', PL_CC_URL . 'assets/dashboard/css/pcg-cropper.css', ['cropperjs'], '1.0.0');

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

            wp_enqueue_script('pcg-dashboard-utils', PL_CC_URL . 'assets/dashboard/js/parts/_utils.js', [], $creator_js_ver, true);
            wp_enqueue_script('pcg-dashboard-shared', PL_CC_URL . 'assets/dashboard/js/parts/_shared-logic.js', ['jquery', 'pcg-dashboard-utils'], $creator_js_ver, true);
            wp_enqueue_script('pcg-dashboard-ui-nav', PL_CC_URL . 'assets/dashboard/js/parts/_ui-nav.js', ['jquery', 'pcg-dashboard-utils'], $creator_js_ver, true);
            wp_enqueue_script('pcg-dashboard-specs', PL_CC_URL . 'assets/dashboard/js/parts/_specializations.js', ['jquery', 'pcg-dashboard-shared', 'pcg-dashboard-utils', 'jquery-ui-sortable'], $creator_js_ver, true);
            wp_enqueue_script('pcg-dashboard-programs', PL_CC_URL . 'assets/dashboard/js/parts/_programs.js', ['jquery', 'pcg-dashboard-shared', 'pcg-dashboard-utils'], $creator_js_ver, true);
            wp_enqueue_script('pcg-dashboard-escritos', PL_CC_URL . 'assets/dashboard/js/parts/_escritos.js', ['jquery', 'pcg-dashboard-utils'], $creator_js_ver, true);
            wp_enqueue_script('pcg-dashboard-lessons', PL_CC_URL . 'assets/dashboard/js/parts/_lessons.js', ['jquery', 'pcg-dashboard-utils', 'jquery-ui-sortable'], $creator_js_ver, true);
            wp_enqueue_script('pcg-dashboard-evaluation', PL_CC_URL . 'assets/dashboard/js/parts/_evaluation.js', ['jquery', 'pcg-dashboard-utils'], $creator_js_ver, true);
            wp_enqueue_script('pcg-dashboard-media', PL_CC_URL . 'assets/dashboard/js/parts/_media-handlers.js', ['jquery', 'pcg-dashboard-utils', 'pcg-cropper-js'], $creator_js_ver, true);
            wp_enqueue_script('pcg-dashboard-profile', PL_CC_URL . 'assets/dashboard/js/parts/_profile-ui.js', ['jquery', 'pcg-dashboard-utils'], $creator_js_ver, true);
            wp_enqueue_script('pcg-dashboard-students', PL_CC_URL . 'assets/dashboard/js/parts/_students-ui.js', ['jquery', 'pcg-dashboard-utils'], $creator_js_ver, true);

            wp_enqueue_script('pcg-cropper-js', PL_CC_URL . 'assets/dashboard/js/pcg-course-cropper.js', ['jquery', 'cropperjs'], '1.0.0', true);
            wp_enqueue_script('pcg-creator-js', PL_CC_URL . 'assets/dashboard/js/creator-dashboard.js', [
                'jquery', 
                'jquery-ui-sortable', 
                'pcg-cropper-js', 
                'pcg-dashboard-utils', 
                'pcg-dashboard-shared', 
                'pcg-dashboard-ui-nav',
                'pcg-dashboard-specs',
                'pcg-dashboard-programs',
                'pcg-dashboard-escritos',
                'pcg-dashboard-lessons',
                'pcg-dashboard-evaluation',
                'pcg-dashboard-media',
                'pcg-dashboard-profile',
                'pcg-dashboard-students'
            ], $creator_js_ver, true);
            wp_enqueue_script('pcg-chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], '4.4.1', true);
            wp_enqueue_script('pcg-sales-dashboard', PL_CC_URL . 'assets/dashboard/js/pcg-sales-dashboard.js', ['pcg-chartjs'], '1.0.0', true);
            wp_enqueue_script('pcg-sales-list', PL_CC_URL . 'assets/dashboard/js/pcg-sales-list.js', [], '1.0.2', true);
            wp_enqueue_script('pcg-students-dashboard', PL_CC_URL . 'assets/dashboard/js/pcg-students-dashboard.js', ['pcg-chartjs'], '1.0.2', true);
            wp_enqueue_script('pcg-students-rankings', PL_CC_URL . 'assets/dashboard/js/pcg-students-rankings.js', [], '1.0.0', true);

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
                'portfolioNonce' => wp_create_nonce('pl_portfolio_nonce'),
                'teacherSearchAction' => 'pcg_search_teachers',
                'teacherSearchNonce' => wp_create_nonce('pcg_search_teachers_nonce'),
                'currentUserId' => $current_user->ID,
                'currentUserName' => $full_name . ' (' . $current_user->user_email . ')',
                'currentUserAvatar' => get_avatar_url($current_user->ID, ['size' => 64]),
                'currentUserFullNameEmail' => $full_name . ' (' . $current_user->user_email . ')',
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
                    'addText' => __('AGREGAR TEXTO', 'politeia-learning'),
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
                    'changeProfilePhoto' => __('Cambiar foto de perfil', 'politeia-learning'),
                    'imageTooHeavy' => __('La imagen es demasiado pesada. El máximo permitido es 300kb.', 'politeia-learning'),
                ],
            ]);

            // Cropper modal logic
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
    }
}
