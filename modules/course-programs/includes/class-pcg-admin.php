<?php
class PCG_Admin_Menu {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_page' ], 90 );
    }

    public function add_admin_page() {
        $parent_slug = 'learndash-lms';

        add_submenu_page(
            $parent_slug,
            'Programa Politeia',
            'Programa Politeia',
            'manage_options',
            'pcg-programa',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        if ( ! class_exists( 'PCG_Programs_Table' ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'class-pcg-programs-table.php';
        }

        $table = new PCG_Programs_Table();
        $table->prepare_items();

        $new_program_url = admin_url( 'post-new.php?post_type=course_program' );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Programa Politeia</h1>
            <a href="<?php echo esc_url( $new_program_url ); ?>" class="page-title-action">Agregar nuevo Programa</a>
            <hr class="wp-header-end">
            <?php $table->display(); ?>
        </div>
        <?php
    }
}
