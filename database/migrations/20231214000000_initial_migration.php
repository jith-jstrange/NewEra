<?php
/**
 * Initial Database Migration for Newera Plugin
 *
 * Creates the basic tables needed for plugin functionality.
 */

namespace Newera\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * InitialMigration class
 */
class InitialMigration {
    /**
     * Run the migration
     */
    public function up() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create plugin settings table
        $table_name = $wpdb->prefix . 'newera_settings';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL,
            setting_value longtext,
            autoload varchar(20) DEFAULT 'yes',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Insert default settings
        $default_settings = [
            'plugin_version' => NEWERA_VERSION,
            'enable_logging' => '1',
            'log_level' => 'info',
            'auto_cleanup_logs' => '0',
            'max_log_size' => '10',
            'health_check_interval' => '24',
            'debug_mode' => '0'
        ];

        foreach ($default_settings as $key => $value) {
            $wpdb->insert(
                $wpdb->prefix . 'newera_settings',
                [
                    'setting_key' => $key,
                    'setting_value' => $value
                ],
                ['%s', '%s']
            );
        }
    }

    /**
     * Rollback the migration
     */
    public function down() {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'newera_settings'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}