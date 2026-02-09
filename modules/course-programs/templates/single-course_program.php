<?php
/**
 * Single template for Programa Politeia posts.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

if ( have_posts() ) {
    while ( have_posts() ) {
        the_post();

        $program_id       = get_the_ID();
        $groups_meta      = get_post_meta( $program_id, 'politeia_program_groups', true );
        $program_price    = get_post_meta( $program_id, 'politeia_program_price', true );
        $program_summary  = get_post_meta( $program_id, 'politeia_program_summary', true );

        if ( empty( $groups_meta ) ) {
            $group_ids = array();
        } elseif ( is_array( $groups_meta ) ) {
            $group_ids = array_filter( array_map( 'intval', $groups_meta ) );
        } else {
            $group_ids = array_filter( array_map( 'intval', (array) maybe_unserialize( $groups_meta ) ) );
        }

        $group_ids   = array_unique( $group_ids );
        $group_ids   = array_filter( $group_ids );
        $group_posts = array();

        if ( ! empty( $group_ids ) ) {
            $group_posts = get_posts(
                array(
                    'post_type'      => 'groups',
                    'post__in'       => $group_ids,
                    'posts_per_page' => -1,
                    'orderby'        => 'post__in',
                )
            );
        }

        $group_count = count( $group_posts );

        if ( empty( $program_summary ) ) {
            $program_summary = wp_strip_all_tags( get_the_excerpt() );
        }

        $teachers = apply_filters(
            'politeia_program_teachers_placeholder',
            array(
                array(
                    'name'  => __( 'Próximamente', 'politeia-course-group' ),
                    'role'  => __( 'Profesor/a', 'politeia-course-group' ),
                    'image' => '',
                ),
            ),
            $program_id
        );
        $featured_image_url   = get_the_post_thumbnail_url( $program_id, 'large' );
        $header_style         = $featured_image_url ? 'background-image: url(' . esc_url( $featured_image_url ) . ');' : 'background-color: #000;';
        $header_class         = $featured_image_url ? 'has-featured-image' : 'no-featured-image';
        $program_duration     = get_post_meta( $program_id, 'politeia_program_duration', true );
        $header_meta_segments = array();

        if ( $group_count > 0 ) {
            $header_meta_segments[] = sprintf( _n( '%s Grupo', '%s Grupos', $group_count, 'politeia-course-group' ), number_format_i18n( $group_count ) );
        }

        if ( ! empty( $program_duration ) ) {
            $header_meta_segments[] = wp_strip_all_tags( $program_duration );
        }

        if ( ! empty( $program_price ) ) {
            $header_meta_segments[] = wp_strip_all_tags( $program_price );
        }

        /**
         * Filters the pieces that compose the program header metadata line.
         *
         * @param string[] $header_meta_segments The metadata segments to output.
         * @param int      $program_id           The current program ID.
         */
        $header_meta_segments = apply_filters( 'politeia_program_header_meta_segments', $header_meta_segments, $program_id );

        $header_meta_text = implode( ' · ', array_filter( array_map( 'trim', $header_meta_segments ) ) );
        ?>

        <main id="primary" class="site-main" role="main">
            <div class="pcg-program-wrap">
                <header id="politeia-program-header" class="<?php echo esc_attr( $header_class ); ?>" style="<?php echo esc_attr( $header_style ); ?>">
                    <div class="politeia-program-header-inner">
                        <h1 class="pcg-program-title"><?php the_title(); ?></h1>

                        <?php if ( ! empty( $header_meta_text ) ) : ?>
                            <p class="program-meta"><?php echo esc_html( $header_meta_text ); ?></p>
                        <?php endif; ?>

                        <div class="pcg-header-content">
                            <?php if ( ! empty( $program_summary ) ) : ?>
                                <p class="pcg-header-summary"><?php echo esc_html( $program_summary ); ?></p>
                            <?php endif; ?>

                            <?php if ( $group_count > 0 || ! empty( $program_price ) ) : ?>
                                <div class="pcg-header-tags">
                                    <?php if ( $group_count > 0 ) : ?>
                                        <span class="pcg-header-tag">
                                            <?php printf( _n( '%s Ramo', '%s Ramos', $group_count, 'politeia-course-group' ), number_format_i18n( $group_count ) ); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ( ! empty( $program_price ) ) : ?>
                                        <span class="pcg-header-tag pcg-header-tag--muted">
                                            <?php echo esc_html( $program_price ); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </header>

                <section id="politeia-program-content" class="pcg-program-content">
                    <div class="pcg-program-description">
                        <h2 class="pcg-section-title"><?php esc_html_e( 'Descripción Programa', 'politeia-course-group' ); ?></h2>
                        <div class="pcg-card pcg-description-card" id="descripcion-texto">
                            <?php the_content(); ?>
                        </div>
                    </div>

                    <aside class="pcg-program-ramos">
                        <div class="lg:col-span-1">
                            <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2"><?php esc_html_e( 'Ramos', 'politeia-course-group' ); ?></h2>
                            <div class="bg-white p-5 rounded-lg border border-gray-300 card-shadow">
                                <?php
                                $group_ids = get_post_meta( get_the_ID(), 'politeia_program_groups', true );
                                if ( is_string( $group_ids ) ) {
                                    $decoded_group_ids = json_decode( $group_ids, true );
                                    if ( json_last_error() === JSON_ERROR_NONE ) {
                                        $group_ids = $decoded_group_ids;
                                    }
                                }

                                if ( ! empty( $group_ids ) && is_array( $group_ids ) ) {
                                    echo '<div class="space-y-4">';
                                    $has_valid_groups = false;

                                    foreach ( $group_ids as $group_id ) {
                                        $group_id = absint( $group_id );

                                        if ( 0 === $group_id ) {
                                            continue;
                                        }

                                        $group = get_post( $group_id );

                                        if ( $group && 'groups' === $group->post_type ) {
                                            $has_valid_groups   = true;
                                            $group_title        = get_the_title( $group_id );
                                            $group_permalink    = get_permalink( $group_id );
                                            $group_image        = get_the_post_thumbnail_url( $group_id, 'thumbnail' );
                                            $group_placeholder  = 'https://placehold.co/80x80/777/fff?text=GR';
                                            $group_image_output = $group_image ? $group_image : $group_placeholder;

                                            printf(
                                                '<a href="%1$s" class="flex items-center p-2 rounded-lg hover:bg-gray-50 transition duration-150" style="display:flex;align-items:center;justify-content:flex-start;gap:10px;">
                                                    <img src="%2$s" alt="%3$s" class="w-10 h-10 object-cover shadow-md" style="border-radius:6px;" />
                                                    <span class="text-gray-800 font-medium">%4$s</span>
                                                </a>',
                                                esc_url( $group_permalink ),
                                                esc_url( $group_image_output ),
                                                esc_attr( $group_title ),
                                                esc_html( $group_title )
                                            );
                                        }
                                    }

                                    if ( ! $has_valid_groups ) {
                                        echo '<p class="text-gray-600 italic">' . esc_html__( 'No hay grupos asociados a este programa.', 'politeia-course-group' ) . '</p>';
                                    }

                                    echo '</div>';
                                } else {
                                    echo '<p class="text-gray-600 italic">' . esc_html__( 'No hay grupos asociados a este programa.', 'politeia-course-group' ) . '</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </aside>
                </section>

                <section id="politeia-program-teachers" class="pcg-program-teachers">
                    <h2 class="pcg-section-title"><?php esc_html_e( 'Profesores', 'politeia-course-group' ); ?></h2>
                    <div id="profesores-list" class="pcg-card pcg-teachers">
                        <?php if ( ! empty( $teachers ) ) : ?>
                            <?php foreach ( $teachers as $teacher ) :
                                $teacher_name  = isset( $teacher['name'] ) ? $teacher['name'] : '';
                                $teacher_role  = isset( $teacher['role'] ) ? $teacher['role'] : '';
                                $teacher_image = isset( $teacher['image'] ) ? $teacher['image'] : '';
                                ?>
                                <article class="pcg-teacher">
                                    <div class="pcg-teacher-avatar">
                                        <?php if ( ! empty( $teacher_image ) ) : ?>
                                            <img src="<?php echo esc_url( $teacher_image ); ?>" alt="<?php echo esc_attr( $teacher_name ); ?>" />
                                        <?php else : ?>
                                            <span class="pcg-teacher-initial">
                                                <?php
                                                $teacher_initial = '';
                                                if ( ! empty( $teacher_name ) ) {
                                                    if ( function_exists( 'mb_substr' ) ) {
                                                        $teacher_initial = mb_substr( $teacher_name, 0, 1 );
                                                    } else {
                                                        $teacher_initial = substr( $teacher_name, 0, 1 );
                                                    }
                                                }
                                                echo esc_html( $teacher_initial );
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pcg-teacher-info">
                                        <h3 class="pcg-teacher-name"><?php echo esc_html( $teacher_name ); ?></h3>
                                        <?php if ( ! empty( $teacher_role ) ) : ?>
                                            <p class="pcg-teacher-role"><?php echo esc_html( $teacher_role ); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="pcg-teachers-empty"><?php esc_html_e( 'Pronto anunciaremos a los profesores de este programa.', 'politeia-course-group' ); ?></p>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>

        <?php
    }
}

get_footer();
