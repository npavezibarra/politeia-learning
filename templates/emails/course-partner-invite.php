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
$logo_url = function_exists('pl_get_politeia_logo_url') ? pl_get_politeia_logo_url() : (defined('PL_URL') ? (string) PL_URL . 'assets/images/politeia-logo.png' : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación al Curso - Politeia</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f9fafb;
            font-family: 'Poppins', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .header {
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 11px;
            color: #6b7280;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .logo-img {
            display: block;
            height: 33px;
            width: auto;
        }

        .divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 0 30px;
        }

        .content {
            padding: 50px 40px;
            text-align: center;
        }

        .greeting {
            font-size: 20px;
            color: #111827;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .invitation-text {
            font-size: 16px;
            color: #4b5563;
            margin-bottom: 20px;
        }

        .course-name {
            display: block;
            font-size: 24px;
            color: #111827;
            font-weight: 700;
            margin: 25px 0;
            line-height: 1.2;
        }

        .instruction-box {
            font-size: 15px;
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 35px;
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #f1f5f9;
        }

        .highlight {
            color: #000000;
            font-weight: 600;
        }

        .btn-container {
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            background-color: #000000;
            color: #ffffff;
            padding: 12px 28px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.85;
        }

        .footer {
            padding: 20px 40px 40px 40px;
            text-align: center;
        }

        .footer-text {
            font-size: 13px;
            color: #9ca3af;
        }

        @media (max-width: 600px) {
            .email-container {
                margin: 0;
                width: 100%;
                border-radius: 0;
            }
            .content { padding: 40px 20px; }
            .course-name { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <span class="header-title">Course Invitation</span>

            <img class="logo-img" src="<?php echo esc_url($logo_url); ?>" alt="Politeia">
        </div>

        <div class="divider"></div>

        <div class="content">
            <p class="greeting">
                <?php
                $name = trim($invitee_name);
                if ($name === '') {
                    $name = '¡Hola!';
                    echo esc_html($name);
                } else {
                    echo esc_html(sprintf('¡Hola %s!', $name));
                }
                ?>
            </p>

            <p class="invitation-text">
                <?php echo esc_html(sprintf('%s te ha invitado a sumarte como partner en el curso:', $inviter_name)); ?>
            </p>

            <span class="course-name">
                <?php echo esc_html('"' . ($course_name !== '' ? $course_name : '(curso)') . '"'); ?>
            </span>

            <div class="instruction-box">
                ¡Estamos muy emocionados de que comiences este camino! Al aceptar, no solo <span class="highlight">podrás acceder a todo el contenido del curso</span>, sino que también participarás en una experiencia de aprendizaje colaborativo única: <span class="highlight">podrás tomar la Evaluación Final a tu partner</span>.
                <br><br>
                Esta evaluación cruzada es clave para su crecimiento: debe ser <span class="highlight">grabada en vivo, subida a tu canal de YouTube y publicada</span> para obtener tu certificado final. ¡Anímate a completar este desafío y demostrar todo lo aprendido!
            </div>

            <div class="btn-container">
                <a href="<?php echo esc_url($accept_url); ?>" class="btn">ACEPTAR INVITACIÓN</a>
            </div>
        </div>

        <div class="footer">
            <p class="footer-text">
                Si no esperabas este correo, puedes ignorarlo con seguridad.
            </p>
        </div>
    </div>
</body>
</html>
