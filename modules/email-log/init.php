<?php
/**
 * Module Name: Email Log
 * Description: Registro de todos los correos enviados desde la plataforma.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-pl-email-log-db.php';
require_once __DIR__ . '/includes/class-pl-email-log-manager.php';

PL_Email_Log_Manager::init();

