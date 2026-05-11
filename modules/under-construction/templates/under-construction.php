<?php
if (!defined('ABSPATH')) {
    exit;
}

$message = 'Estamos construyendo este sitio.';
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
    <style>
        html, body { height: 100%; }
        body.pl-under-construction {
            margin: 0;
            background: #ffffff;
            color: #111111;
            font-family: "Poppins", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        }
        .pl-uc-wrap {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 18px;
            padding: 28px;
            box-sizing: border-box;
            text-align: center;
        }
        .pl-uc-logo {
            width: 220px;
            max-width: 70vw;
            height: auto;
        }
        .pl-uc-message {
            font-size: 16px;
            line-height: 1.4;
            margin: 0;
        }
        .pl-uc-button {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: 12px 18px;
            background: #111111;
            color: #ffffff;
            font-weight: 700;
            letter-spacing: .02em;
            cursor: pointer;
        }
        .pl-uc-button:focus { outline: 2px solid #111111; outline-offset: 2px; }
    </style>
</head>
<body class="pl-under-construction">
    <?php do_action('wp_body_open'); ?>
    <main class="pl-uc-wrap">
        <?php if (!empty($logo_url)) : ?>
            <img class="pl-uc-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <?php else : ?>
            <h1 style="margin:0; font-size: 22px;"><?php echo esc_html(get_bloginfo('name')); ?></h1>
        <?php endif; ?>

        <p class="pl-uc-message"><?php echo esc_html($message); ?></p>

        <button
            class="pl-uc-button"
            type="button"
            onclick="if(window.PLAuthOpenModal){window.PLAuthOpenModal('login');}else{window.location.href=<?php echo wp_json_encode((string) $login_fallback_url); ?>;}"
        >
            <?php echo esc_html__('INGRESAR', 'politeia-learning'); ?>
        </button>
    </main>
    <?php wp_footer(); ?>
</body>
</html>

