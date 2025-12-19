<?php
/**
 * Create Subscriptions Table Migration for Newera Plugin
 *
 * Creates the wp_subscriptions table for managing subscription data.
 */

namespace Newera\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CreateSubscriptionsTable class
 */
class CreateSubscriptionsTable {
    /**
     * Run the migration
     */
    public function up() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . 'subscriptions';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id bigint(20) unsigned NOT NULL,
            plan varchar(100) NOT NULL,
            status varchar(50) DEFAULT 'active',
            amount decimal(15, 2),
            encrypted_amount longtext,
            billing_cycle varchar(50),
            start_date date,
            end_date date,
            auto_renew tinyint(1) DEFAULT 1,
            stripe_subscription_id varchar(255),
            stripe_customer_id varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at datetime NULL,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY status (status),
            KEY plan (plan),
            KEY stripe_subscription_id (stripe_subscription_id),
            KEY stripe_customer_id (stripe_customer_id),
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

        $table_name = $wpdb->prefix . 'subscriptions';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
