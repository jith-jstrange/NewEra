<?php
/**
 * Create Project Members Table Migration for Newera Plugin
 *
 * Creates the wp_project_members table for assigning WP users to projects.
 */

namespace Newera\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CreateProjectMembersTable
 */
class CreateProjectMembersTable {
    /**
     * Run the migration.
     */
    public function up() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'project_members';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            role varchar(50) DEFAULT 'member',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY project_user (project_id, user_id),
            KEY project_id (project_id),
            KEY user_id (user_id),
            KEY role (role)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Rollback the migration.
     */
    public function down() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'project_members';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
