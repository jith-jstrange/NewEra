<?php
/**
 * Bootstrap class for Newera Plugin
 *
 * Initializes core services (logger/state), migrations/modules, admin UI, and
 * exposes service locators via WordPress filters.
 */

namespace Newera\Core;

use Newera\Admin\AdminMenu;
use Newera\Admin\SetupWizard;
use Newera\AI\CommandHandler;
use Newera\Database\DBAdapterFactory;
use Newera\Integrations\Linear\LinearManager;
use Newera\Integrations\Notion\NotionManager;
use Newera\Modules\ModuleRegistry;
use Newera\Projects\ProjectManager;

if (!defined('ABSPATH')) {
    exit;
}

class Bootstrap {
    /**
     * @var Bootstrap|null
     */
    private static $instance = null;

    /**
     * @var StateManager
     */
    private $state_manager;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var DBAdapterFactory
     */
    private $db_factory;

    /**
     * @var ModuleRegistry
     */
    private $module_registry;

    /**
     * @var AdminMenu|null
     */
    private $admin_menu;

    /**
     * @var SetupWizard|null
     */
    private $setup_wizard;

    /**
     * @var CommandHandler|null
     */
    private $command_handler;

    /**
     * @var ProjectManager|null
     */
    private $project_manager;

    /**
     * @var LinearManager|null
     */
    private $linear_manager;

    /**
     * @var NotionManager|null
     */
    private $notion_manager;

    /**
     * @return Bootstrap
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        // Singleton.
    }

    /**
     * Initialize the plugin runtime.
     */
    public function init() {
        $this->logger = new Logger();

        $this->state_manager = new StateManager();
        $this->state_manager->init();

        $this->db_factory = new DBAdapterFactory($this->state_manager, $this->logger);

        $this->project_manager = new ProjectManager($this->logger);
        $this->project_manager->init();

        $this->linear_manager = new LinearManager($this->state_manager, $this->logger, $this->project_manager);
        $this->linear_manager->init();

        $this->notion_manager = new NotionManager($this->state_manager, $this->logger, $this->project_manager);
        $this->notion_manager->init();

        $this->module_registry = new ModuleRegistry($this->state_manager, $this->logger);
        $this->module_registry->init();

        $this->expose_services();

        // Boot modules after services are available.
        $this->module_registry->boot();

        // Admin UI.
        if (is_admin()) {
            $this->admin_menu = new AdminMenu();
            $this->admin_menu->init();

            $this->setup_wizard = new SetupWizard($this->state_manager);
            $this->setup_wizard->init();
        }

        // AI command API.
        $this->command_handler = new CommandHandler($this->state_manager, $this->logger, $this->module_registry);
        $this->command_handler->init();

        $this->maybe_show_activation_notice();

        $this->logger->info('Newera plugin initialized successfully');
    }

    private function maybe_show_activation_notice() {
        if (!get_transient('newera_activated')) {
            return;
        }

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Newera plugin activated successfully!', 'newera');
            echo '</p></div>';
        });

        delete_transient('newera_activated');
    }

    /**
     * Expose core services to other components via filters.
     */
    private function expose_services() {
        add_filter('newera_get_state_manager', function() {
            return $this->state_manager;
        });

        add_filter('newera_get_logger', function() {
            return $this->logger;
        });

        add_filter('newera_get_module_registry', function() {
            return $this->module_registry;
        });

        add_filter('newera_get_project_manager', function() {
            return $this->project_manager;
        });

        add_filter('newera_get_linear_manager', function() {
            return $this->linear_manager;
        });

        add_filter('newera_get_notion_manager', function() {
            return $this->notion_manager;
        });

        add_filter('newera_get_db_factory', function() {
            return $this->db_factory;
        });
    }
}
