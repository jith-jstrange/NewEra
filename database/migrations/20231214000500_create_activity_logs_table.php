<?php
/**
 * Create Activity Logs Table Migration for Newera Plugin
 *
 * Creates the wp_activity_logs table for logging user actions.
 */

namespace Newera\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CreateActivityLogsTable class
 */
class CreateActivityLogsTable {
    /**
     * Run the migration
     */
    public function up() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . 'activity_logs';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned,
            action varchar(100) NOT NULL,
            entity_type varchar(100),
            entity_id bigint(20) unsigned,
            description text,
            ip_address varchar(45),
            user_agent text,
            status varchar(50) DEFAULT 'success',
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY entity_type (entity_type),
            KEY entity_id (entity_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Rollback the migration
     */
    public function down() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'activity_logs';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
