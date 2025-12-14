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
            id int(11) NOT NULL AUTO_INCREMENT,
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

        // Create plugin data table
        $table_name = $wpdb->prefix . 'newera_data';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            data_key varchar(255) NOT NULL,
            data_value longtext,
            data_type varchar(50) DEFAULT 'string',
            expires_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY data_key (data_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Create activity log table
        $table_name = $wpdb->prefix . 'newera_activity_log';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NULL,
            action varchar(100) NOT NULL,
            description text,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
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

        // Drop tables in reverse order
        $tables = [
            $wpdb->prefix . 'newera_activity_log',
            $wpdb->prefix . 'newera_data',
            $wpdb->prefix . 'newera_settings'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}