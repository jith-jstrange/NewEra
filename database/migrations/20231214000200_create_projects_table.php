<?php
/**
 * Create Projects Table Migration for Newera Plugin
 *
 * Creates the wp_projects table for managing project data.
 */

namespace Newera\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CreateProjectsTable class
 */
class CreateProjectsTable {
    /**
     * Run the migration
     */
    public function up() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . 'projects';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            description longtext,
            status varchar(50) DEFAULT 'pending',
            budget decimal(15, 2),
            encrypted_budget longtext,
            progress int(3) DEFAULT 0,
            start_date date,
            end_date date,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at datetime NULL,
            PRIMARY KEY (id),
            KEY client_id (client_id),
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

        $table_name = $wpdb->prefix . 'projects';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
