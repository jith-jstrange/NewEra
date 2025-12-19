<?php
/**
 * Stripe Payments Module for Newera Plugin
 */

namespace Newera\Modules\Payments;

if (!defined('ABSPATH')) {
    exit;
}

// Register the StripeManager as a module
add_action('newera_modules_loaded', function() {
    if (class_exists('\\Newera\\Core\\ModulesRegistry')) {
        $registry = new \Newera\Core\ModulesRegistry();
        $stripe_manager = new StripeManager();
        $registry->register_module($stripe_manager);
    }
});