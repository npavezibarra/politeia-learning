<?php
/**
 * Custom My Account Template for Politeia
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('WC')) {
    wp_die(esc_html__('WooCommerce es necesario para ver esta página.', 'politeia-learning'));
}

if (function_exists('wc_enqueue_scripts')) {
    wc_enqueue_scripts();
}

$user = wp_get_current_user();
$first_name = isset($user->first_name) ? trim((string) $user->first_name) : '';
$display_name = isset($user->display_name) ? trim((string) $user->display_name) : '';
$hello_name = $first_name !== '' ? $first_name : ($display_name !== '' ? $display_name : 'Hola');

$initial_tab = 'escritorio';
$requested_tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS);
if ($requested_tab !== null && in_array($requested_tab, ['cursos', 'pedidos', 'direcciones', 'detalles'])) {
    $initial_tab = $requested_tab;
}
if (function_exists('is_wc_endpoint_url')) {
    if (is_wc_endpoint_url('orders') || is_wc_endpoint_url('view-order')) {
        $initial_tab = 'pedidos';
    } elseif (is_wc_endpoint_url('edit-address')) {
        $initial_tab = 'direcciones';
    } elseif (is_wc_endpoint_url('edit-account')) {
        $initial_tab = 'detalles';
    }
}

$orders = [];
if (function_exists('wc_get_orders') && is_user_logged_in()) {
    $orders = wc_get_orders([
        'customer' => get_current_user_id(),
        'limit' => 50,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
}

$courses = [];
if (is_user_logged_in()) {
    $user_id = (int) get_current_user_id();
    // Use our Learni module system for enrollments.
    if (class_exists('Learni\Database\Enrollments')) {
        $learni_enrollments = \Learni\Database\Enrollments::get_for_user($user_id);
        foreach ($learni_enrollments as $e) {
            $course_ids[] = $e['courseId'];
        }
    }

    $course_ids = array_values(array_unique(array_filter(array_map('intval', $course_ids))));

    foreach ($course_ids as $course_id) {
        $post = get_post($course_id);
        if (!$post || $post->post_status !== 'publish') continue;

        $image_url = get_the_post_thumbnail_url($post, 'medium_large');
        $courses[] = [
            'id' => $course_id,
            'title' => get_the_title($post),
            'url' => get_permalink($post),
            'instructor' => get_the_author_meta('display_name', $post->post_author),
            'image' => $image_url ?: '',
        ];
    }
}

$myaccount_url = wc_get_page_permalink('myaccount');
$logout_url = wp_logout_url($myaccount_url);

pl_template_open();
?>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    #pl-ma-body { font-family: 'Inter', sans-serif; background-color: #fff; color: #000; }
    .text-huge { font-size: 2.5rem; line-height: 1.1; font-weight: 700; }
    .label-caps { font-size: 0.85rem; letter-spacing: 0.15em; font-weight: 700; text-transform: uppercase; color: #737373; }
    .nav-item.active { background-color: #000; color: #fff; }
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeInUp 0.4s ease-out forwards; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div id="pl-ma-body" class="antialiased py-12 px-4">
    <div id="politeia-ma-container" class="max-w-7xl mx-auto py-16">
        <h1 class="text-3xl font-semibold mb-10">Mi cuenta</h1>
        
        <?php if (!is_user_logged_in()) : ?>
            <div class="text-center py-20 bg-neutral-50 rounded-lg">
                <h2 class="text-2xl font-bold mb-4">Debes iniciar sesión</h2>
                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="bg-black text-white px-8 py-3 rounded-md font-bold uppercase tracking-widest text-xs">Login</a>
            </div>
        <?php else : ?>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-16">
                <!-- Sidebar -->
                <nav class="md:col-span-3 space-y-2">
                    <button onclick="showTab('escritorio')" class="nav-item active w-full text-left py-4 px-6 border-b border-neutral-200 uppercase tracking-widest text-[10px] font-bold">Escritorio</button>
                    <button onclick="showTab('cursos')" class="nav-item w-full text-left py-4 px-6 border-b border-neutral-200 uppercase tracking-widest text-[10px] font-bold">Mis Cursos</button>
                    <button onclick="showTab('pedidos')" class="nav-item w-full text-left py-4 px-6 border-b border-neutral-200 uppercase tracking-widest text-[10px] font-bold">Pedidos</button>
                    <button onclick="showTab('direcciones')" class="nav-item w-full text-left py-4 px-6 border-b border-neutral-200 uppercase tracking-widest text-[10px] font-bold">Direcciones</button>
                    <button onclick="window.location.href='<?php echo esc_url($logout_url); ?>'" class="w-full text-left py-4 px-6 text-neutral-400 font-bold uppercase tracking-widest text-[10px] mt-8">Cerrar Sesión</button>
                </nav>

                <!-- Content -->
                <main class="md:col-span-9">
                    <section id="escritorio" class="tab-content active">
                        <h2 class="text-huge mb-6"><?php echo esc_html("Hola, $hello_name."); ?></h2>
                        <p class="text-lg text-neutral-500 max-w-xl">Desde aquí puedes ver tus pedidos, gestionar tus direcciones y editar los detalles de tu cuenta.</p>
                    </section>

                    <section id="cursos" class="tab-content">
                        <h2 class="text-3xl font-bold mb-8 border-b border-black pb-4">Mis Cursos</h2>
                        <div class="space-y-8">
                            <?php if (empty($courses)) : ?>
                                <p class="text-neutral-500">Aún no tienes cursos.</p>
                            <?php else : ?>
                                <?php foreach ($courses as $c) : ?>
                                    <div class="flex items-center gap-6 group">
                                        <div class="w-32 h-20 bg-neutral-100 rounded-md overflow-hidden flex-shrink-0">
                                            <?php if ($c['image']) : ?>
                                                <img src="<?php echo esc_url($c['image']); ?>" class="w-full h-full object-cover">
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="<?php echo esc_url($c['url']); ?>" class="text-xl font-bold hover:underline"><?php echo esc_html($c['title']); ?></a>
                                            <p class="text-xs text-neutral-400 uppercase tracking-widest mt-1">Prof. <?php echo esc_html($c['instructor']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section id="pedidos" class="tab-content">
                        <h2 class="text-3xl font-bold mb-8 border-b border-black pb-4">Pedidos</h2>
                        <div class="divide-y divide-neutral-100">
                            <?php foreach ($orders as $o) : ?>
                                <div class="py-6 flex items-center justify-between">
                                    <div>
                                        <span class="label-caps block mb-1">Orden</span>
                                        <span class="font-bold">#<?php echo esc_html($o->get_order_number()); ?></span>
                                    </div>
                                    <a href="<?php echo esc_url($o->get_view_order_url()); ?>" class="border border-black px-6 py-2 rounded-full text-[10px] font-bold uppercase tracking-widest hover:bg-black hover:text-white transition-all">Ver</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section id="direcciones" class="tab-content">
                        <h2 class="text-3xl font-bold mb-8 border-b border-black pb-4">Direcciones</h2>
                        <p class="text-neutral-500">Puedes editar tus direcciones en el flujo de <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="underline font-bold text-black">Checkout</a> para mayor comodidad.</p>
                    </section>
                </main>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function showTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        
        const target = document.getElementById(tabId);
        if (target) target.classList.add('active');
        
        const btn = Array.from(document.querySelectorAll('.nav-item')).find(b => b.innerText.toLowerCase().includes(tabId));
        if (btn) btn.classList.add('active');
    }
</script>
<?php
pl_template_close();
