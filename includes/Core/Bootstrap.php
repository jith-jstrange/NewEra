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
     * Modules Registry instance
     *
     * @var ModulesRegistry
     */
    private $modules_registry;

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
        $this->init_modules_registry();
        $this->init_admin_menu();
        
        // Initialize modules
        $this->init_modules();
        
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
     * Initialize the Modules Registry
     */
    private function init_modules_registry() {
        $this->modules_registry = new ModulesRegistry();
        $this->modules_registry->init();
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
     * Initialize all registered modules
     */
    private function init_modules() {
        $modules = $this->modules_registry->get_modules();
        
        foreach ($modules as $module_name => $module_config) {
            if (class_exists($module_config['class'])) {
                try {
                    $module = new $module_config['class']();
                    if (method_exists($module, 'init')) {
                        $module->init();
                    }
                    $this->logger->info("Module {$module_name} initialized");
                } catch (\Exception $e) {
                    $this->logger->error("Failed to initialize module {$module_name}: " . $e->getMessage());
                }
            }
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
     * Get Modules Registry
     *
     * @return ModulesRegistry
     */
    public function get_modules_registry() {
        return $this->modules_registry;
    }

    /**
     * Get Logger
     *
     * @return Logger
     */
    public function get_logger() {
        return $this->logger;
    }
}