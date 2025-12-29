<?php
/**
 * Plugin Name: Newera
 * Plugin URI: https://github.com/newera/plugin
 * Description: A modern WordPress plugin with comprehensive bootstrap and module architecture.
 * Version: 1.0.0
 * Author: Newera Team
 * Author URI: https://newera.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: newera
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NEWERA_VERSION', '1.0.0');
define('NEWERA_PLUGIN_FILE', __FILE__);
define('NEWERA_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('NEWERA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('NEWERA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEWERA_INCLUDES_PATH', NEWERA_PLUGIN_PATH . 'includes/');
define('NEWERA_ASSETS_URL', NEWERA_PLUGIN_URL . 'assets/');
define('NEWERA_TEMPLATES_PATH', NEWERA_PLUGIN_PATH . 'templates/');
define('NEWERA_MODULES_PATH', NEWERA_PLUGIN_PATH . 'modules/');

// Check for required WordPress version
if (version_compare(get_bloginfo('version'), '5.0', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo __('Newera requires WordPress 5.0 or later.', 'newera');
        echo '</p></div>';
    });
    return;
}

// Check for required PHP version
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo __('Newera requires PHP 7.4 or later.', 'newera');
        echo '</p></div>';
    });
    return;
}

// Load Composer autoloader if it exists
if (file_exists(NEWERA_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once NEWERA_PLUGIN_PATH . 'vendor/autoload.php';
}

// Load core bootstrap files
require_once NEWERA_INCLUDES_PATH . 'Core/Bootstrap.php';
require_once NEWERA_INCLUDES_PATH . 'Core/StateManager.php';
require_once NEWERA_INCLUDES_PATH . 'Core/Logger.php';
require_once NEWERA_INCLUDES_PATH . 'Database/DBAdapterInterface.php';
require_once NEWERA_INCLUDES_PATH . 'Database/WPDBAdapter.php';
require_once NEWERA_INCLUDES_PATH . 'Database/ExternalDBAdapter.php';
require_once NEWERA_INCLUDES_PATH . 'Database/DBAdapterFactory.php';
require_once NEWERA_INCLUDES_PATH . 'Database/RepositoryBase.php';
require_once NEWERA_INCLUDES_PATH . 'Core/Crypto.php';

// Load Payments classes
require_once NEWERA_INCLUDES_PATH . 'Payments/StripeManager.php';
require_once NEWERA_INCLUDES_PATH . 'Payments/PlanManager.php';
require_once NEWERA_INCLUDES_PATH . 'Payments/SubscriptionRepository.php';
require_once NEWERA_INCLUDES_PATH . 'Payments/WebhookHandler.php';
require_once NEWERA_INCLUDES_PATH . 'Payments/WebhookEndpoint.php';
// Project tracking + integrations
require_once NEWERA_INCLUDES_PATH . 'Projects/ProjectManager.php';
require_once NEWERA_INCLUDES_PATH . 'Integrations/Linear/LinearManager.php';
require_once NEWERA_INCLUDES_PATH . 'Integrations/Notion/NotionManager.php';

// Module framework (auto-discovered modules live in /modules)
require_once NEWERA_INCLUDES_PATH . 'Modules/ModuleInterface.php';
require_once NEWERA_INCLUDES_PATH . 'Modules/BaseModule.php';
require_once NEWERA_INCLUDES_PATH . 'Modules/ModuleRegistry.php';

// Legacy registry (used by existing admin screens)
require_once NEWERA_INCLUDES_PATH . 'Core/ModulesRegistry.php';

require_once NEWERA_INCLUDES_PATH . 'Database/MigrationRunner.php';
require_once NEWERA_INCLUDES_PATH . 'Admin/AdminMenu.php';
require_once NEWERA_INCLUDES_PATH . 'Admin/Dashboard.php';
require_once NEWERA_INCLUDES_PATH . 'Admin/SetupWizard.php';
require_once NEWERA_INCLUDES_PATH . 'AI/CommandHandler.php';

// API endpoints
require_once NEWERA_INCLUDES_PATH . 'API/HealthCheck.php';

// Security features
require_once NEWERA_INCLUDES_PATH . 'Security/SecurityHeaders.php';
require_once NEWERA_INCLUDES_PATH . 'Security/RateLimiter.php';
require_once NEWERA_INCLUDES_PATH . 'Security/CORS.php';

/**
 * Plugin activation hook
 */
function newera_activate() {
    global $wpdb;
    
    try {
        // Run migrations
        $migration_runner = new \Newera\Database\MigrationRunner();
        $result = $migration_runner->run();
        
        if (!$result) {
            throw new \Exception('Failed to run migrations');
        }
        
        // Schedule WP-Cron events placeholder
        if (!wp_next_scheduled('newera_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'newera_daily_cleanup');
        }

        // Integration sync (best-effort; no-ops if integrations are not configured)
        if (!wp_next_scheduled('newera_linear_sync_cron')) {
            wp_schedule_event(time() + 300, 'hourly', 'newera_linear_sync_cron');
        }

        if (!wp_next_scheduled('newera_notion_sync_cron')) {
            wp_schedule_event(time() + 300, 'hourly', 'newera_notion_sync_cron');
        }
        
        // Set activation flag
        set_transient('newera_activated', true, 30);
        
        // Log activation
        if (class_exists('\\Newera\\Core\\Logger')) {
            $logger = new \Newera\Core\Logger();
            $logger->info('Newera plugin activated successfully');
        }
        
    } catch (\Exception $e) {
        // Log activation error
        if (class_exists('\\Newera\\Core\\Logger')) {
            $logger = new \Newera\Core\Logger();
            $logger->error('Plugin activation failed: ' . $e->getMessage());
        }
        
        // Show admin error notice
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo __('Newera plugin activation failed. Please check the error logs.', 'newera');
            echo '</p></div>';
        });
        
        return false;
    }
}
register_activation_hook(__FILE__, 'newera_activate');

/**
 * Plugin deactivation hook
 */
function newera_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('newera_daily_cleanup');
    wp_clear_scheduled_hook('newera_linear_sync_cron');
    wp_clear_scheduled_hook('newera_notion_sync_cron');
    
    // Clear activation flag
    delete_transient('newera_activated');
}
register_deactivation_hook(__FILE__, 'newera_deactivate');

/**
 * Initialize the plugin on plugins_loaded
 */
function newera_init() {
    // Load text domain for internationalization
    load_plugin_textdomain('newera', false, dirname(NEWERA_PLUGIN_BASENAME) . '/languages');
    
    // Initialize the Bootstrap
    \Newera\Core\Bootstrap::getInstance();
}
add_action('plugins_loaded', 'newera_init');

/**
 * Plugin initialization check
 */
add_action('init', function() {
    if (class_exists('\\Newera\\Core\\Bootstrap')) {
        \Newera\Core\Bootstrap::getInstance()->init();
    }
});