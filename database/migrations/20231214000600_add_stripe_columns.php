<?php
/**
 * Add Stripe Integration Columns Migration for Newera Plugin
 *
 * Adds Stripe-related columns to clients and subscriptions tables.
 */

namespace Newera\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AddStripeColumns class
 */
class AddStripeColumns {
    /**
     * Run the migration
     */
    public function up() {
        global $wpdb;

        // Add Stripe columns to clients table if they don't exist
        $clients_table = $wpdb->prefix . 'clients';
        
        $stripe_customer_exists = $wpdb->query(
            "SHOW COLUMNS FROM {$clients_table} LIKE 'stripe_customer_id'"
        );

        if ($stripe_customer_exists === 0) {
            $wpdb->query(
                "ALTER TABLE {$clients_table} ADD COLUMN stripe_customer_id varchar(255) NULL UNIQUE KEY"
            );
        }

        // Add Stripe subscription ID to subscriptions table if it doesn't exist
        $subscriptions_table = $wpdb->prefix . 'subscriptions';

        $stripe_subscription_exists = $wpdb->query(
            "SHOW COLUMNS FROM {$subscriptions_table} LIKE 'stripe_subscription_id'"
        );

        if ($stripe_subscription_exists === 0) {
            $wpdb->query(
                "ALTER TABLE {$subscriptions_table} ADD COLUMN stripe_subscription_id varchar(255) NULL UNIQUE KEY"
            );
        }

        // Add Stripe charge ID to subscriptions if it doesn't exist
        $stripe_charge_exists = $wpdb->query(
            "SHOW COLUMNS FROM {$subscriptions_table} LIKE 'stripe_charge_id'"
        );

        if ($stripe_charge_exists === 0) {
            $wpdb->query(
                "ALTER TABLE {$subscriptions_table} ADD COLUMN stripe_charge_id varchar(255) NULL"
            );
        }

        // Add payment method to subscriptions if it doesn't exist
        $payment_method_exists = $wpdb->query(
            "SHOW COLUMNS FROM {$subscriptions_table} LIKE 'payment_method'"
        );

        if ($payment_method_exists === 0) {
            $wpdb->query(
                "ALTER TABLE {$subscriptions_table} ADD COLUMN payment_method varchar(50) DEFAULT 'card'"
            );
        }
    }

    /**
     * Rollback the migration
     */
    public function down() {
        global $wpdb;

        $clients_table = $wpdb->prefix . 'clients';
        $subscriptions_table = $wpdb->prefix . 'subscriptions';

        // Drop Stripe columns if they exist
        $wpdb->query(
            "ALTER TABLE {$clients_table} DROP COLUMN IF EXISTS stripe_customer_id"
        );

        $wpdb->query(
            "ALTER TABLE {$subscriptions_table} DROP COLUMN IF EXISTS stripe_subscription_id"
        );

        $wpdb->query(
            "ALTER TABLE {$subscriptions_table} DROP COLUMN IF EXISTS stripe_charge_id"
        );

        $wpdb->query(
            "ALTER TABLE {$subscriptions_table} DROP COLUMN IF EXISTS payment_method"
        );
    }
}
