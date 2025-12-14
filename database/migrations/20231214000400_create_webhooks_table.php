<?php
/**
 * Create Webhooks Table Migration for Newera Plugin
 *
 * Creates the wp_webhooks table for managing webhook configurations.
 */

namespace Newera\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CreateWebhooksTable class
 */
class CreateWebhooksTable {
    /**
     * Run the migration
     */
    public function up() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . 'webhooks';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            event varchar(100) NOT NULL,
            status varchar(50) DEFAULT 'active',
            secret varchar(255),
            encrypted_secret longtext,
            retry_count int(3) DEFAULT 0,
            max_retries int(3) DEFAULT 3,
            last_triggered_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at datetime NULL,
            PRIMARY KEY (id),
            KEY event (event),
            KEY status (status),
            KEY url (url(100)),
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

        $table_name = $wpdb->prefix . 'webhooks';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
