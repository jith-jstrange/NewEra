<?php
/**
 * Bootstrap class for Newera Plugin
 *
 * This class is responsible for initializing the plugin and its components.
 */

namespace Newera\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstrap class
 */
class Bootstrap {
    /**
     * Instance of this class
     *
     * @var Bootstrap|null
     */
    private static $instance = null;

    /**
     * State Manager instance
     *
     * @var StateManager
     */
    private $state_manager;

    /**
     * Module Registry instance (auto-discovered integration modules)
     *
     * @var \Newera\Modules\ModuleRegistry
     */
    private $module_registry;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Admin Menu instance
     *
     * @var \Newera\Admin\AdminMenu
     */
    private $admin_menu;

    /**
     * Get instance of Bootstrap
     *
     * @return Bootstrap
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Private constructor to prevent direct instantiation
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize components
        $this->init_logger();
        $this->init_state_manager();
        $this->init_module_registry();
        $this->init_admin_menu();
        
        // Boot discovered modules
        $this->init_modules();
        
        // Expose services to other components
        $this->expose_state_manager();
        
        // Log initialization
        $this->logger->info('Newera plugin initialized successfully');
        
        // Check for activation notice
        if (get_transient('newera_activated')) {
            add_action('admin_notices', [$this, 'activation_notice']);
            delete_transient('newera_activated');
        }
    }

    /**
     * Initialize the Logger
     */
    private function init_logger() {
        $this->logger = new Logger();
    }

    /**
     * Initialize the State Manager
     */
    private function init_state_manager() {
        $this->state_manager = new StateManager();
        $this->state_manager->init();
    }

    /**
     * Initialize the Module Registry
     */
    private function init_module_registry() {
        $this->module_registry = new \Newera\Modules\ModuleRegistry($this->state_manager, $this->logger);
        $this->module_registry->init();
    }

    /**
     * Initialize Admin Menu
     */
    private function init_admin_menu() {
        if (is_admin()) {
            $this->admin_menu = new \Newera\Admin\AdminMenu();
            $this->admin_menu->init();
        }
    }

    /**
     * Boot all discovered modules
     */
    private function init_modules() {
        if ($this->module_registry) {
            $this->module_registry->boot();
        }
    }

    /**
     * Display activation notice
     */
    public function activation_notice() {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . __('Newera plugin activated successfully!', 'newera') . '</p>';
        echo '</div>';
    }

    /**
     * Get State Manager
     *
     * @return StateManager
     */
    public function get_state_manager() {
        return $this->state_manager;
    }

    /**
     * Get Module Registry
     *
     * @return \Newera\Modules\ModuleRegistry|null
     */
    public function get_modules_registry() {
        return $this->module_registry;
    }

    /**
     * Get Logger
     *
     * @return Logger
     */
    public function get_logger() {
        return $this->logger;
    }
    
    /**
     * Expose core services to other components via filters.
     */
    private function expose_state_manager() {
        add_filter('newera_get_state_manager', function() {
            return $this->get_state_manager();
        });

        add_filter('newera_get_logger', function() {
            return $this->get_logger();
        });

        add_filter('newera_get_module_registry', function() {
            return $this->get_modules_registry();
        });

        if (!function_exists('newera_get_state_manager')) {
            function newera_get_state_manager() {
                return apply_filters('newera_get_state_manager', null);
            }
        }

        if (!function_exists('newera_get_logger')) {
            function newera_get_logger() {
                return apply_filters('newera_get_logger', null);
            }
        }

        if (!function_exists('newera_get_module_registry')) {
            function newera_get_module_registry() {
                return apply_filters('newera_get_module_registry', null);
            }
        }

        if ($this->state_manager && $this->state_manager->is_crypto_available()) {
            $this->logger->info('Secure credential storage is available via StateManager');
        } else {
            $this->logger->warning('Secure credential storage is not available - crypto functions missing');
        }
    }
}