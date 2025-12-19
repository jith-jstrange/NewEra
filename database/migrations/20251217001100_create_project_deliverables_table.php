<?php
/**
 * Create Project Deliverables Table Migration for Newera Plugin
 *
 * Creates the wp_project_deliverables table for tracking deliverables per project.
 */

namespace Newera\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CreateProjectDeliverablesTable
 */
class CreateProjectDeliverablesTable {
    /**
     * Run the migration.
     */
    public function up() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'project_deliverables';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            milestone_id bigint(20) unsigned NULL,
            title varchar(255) NOT NULL,
            description longtext,
            status varchar(50) DEFAULT 'pending',
            due_date date NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at datetime NULL,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY milestone_id (milestone_id),
            KEY status (status),
            KEY due_date (due_date),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Rollback the migration.
     */
    public function down() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'project_deliverables';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
