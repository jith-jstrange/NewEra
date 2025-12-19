<?php
/**
 * Create Project External Links Table Migration for Newera Plugin
 *
 * Creates the wp_project_external_links table for mapping projects to external systems
 * (Linear issues, Notion pages/databases, etc.).
 */

namespace Newera\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CreateProjectExternalLinksTable
 */
class CreateProjectExternalLinksTable {
    /**
     * Run the migration.
     */
    public function up() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'project_external_links';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            provider varchar(50) NOT NULL,
            external_id varchar(191) NOT NULL,
            external_url varchar(500) NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY provider_external (provider, external_id),
            KEY project_id (project_id),
            KEY provider (provider),
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

        $table_name = $wpdb->prefix . 'project_external_links';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
