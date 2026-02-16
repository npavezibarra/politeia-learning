<?php
/**
 * Handles automatic database upgrades for Politeia Learning.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Upgrader
{
    const DB_VERSION_OPTION = 'politeia_learning_db_version';

    /**
     * Check if a database upgrade is needed and run it.
     */
    public static function maybe_upgrade()
    {
        $stored_version = get_option(self::DB_VERSION_OPTION, '0.0.0');

        if (version_compare($stored_version, PL_DB_VERSION, '<')) {
            PL_Installer::install();
            update_option(self::DB_VERSION_OPTION, PL_DB_VERSION);
        }
    }
}
