<?php
/**
 * Admin Menu class for Newera Plugin
 *
 * Handles admin menu registration and page setup.
 */

namespace Newera\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Menu class
 */
class AdminMenu {
    /**
     * Menu slug
     *
     * @var string
     */
    private $menu_slug = 'newera';

    /**
     * Constructor
     */
    public function __construct() {
        // Constructor
    }

    /**
     * Initialize the admin menu
     */
    public function init() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_newera_dismiss_notice', [$this, 'dismiss_notice']);
    }

    /**
     * Register admin menu
     */
    public function register_menu() {
        // Main menu page
        add_menu_page(
            __('Newera', 'newera'),                    // Page title
            __('Newera', 'newera'),                    // Menu title
            'manage_options',                           // Capability required
            $this->menu_slug,                           // Menu slug
            [$this, 'display_dashboard'],               // Callback function
            'data:image/svg+xml;base64,' . base64_encode($this->get_menu_icon()), // Icon
            30                                          // Position
        );

        // Dashboard submenu (same as main menu)
        add_submenu_page(
            $this->menu_slug,                           // Parent slug
            __('Dashboard', 'newera'),                  // Page title
            __('Dashboard', 'newera'),                  // Menu title
            'manage_options',                           // Capability required
            $this->menu_slug,                           // Menu slug
            [$this, 'display_dashboard']                // Callback function
        );

        // Settings submenu
        add_submenu_page(
            $this->menu_slug,                           // Parent slug
            __('Settings', 'newera'),                   // Page title
            __('Settings', 'newera'),                   // Menu title
            'manage_options',                           // Capability required
            $this->menu_slug . '-settings',             // Menu slug
            [$this, 'display_settings']                 // Callback function
        );

        // Modules submenu
        add_submenu_page(
            $this->menu_slug,                           // Parent slug
            __('Modules', 'newera'),                    // Page title
            __('Modules', 'newera'),                    // Menu title
            'manage_options',                           // Capability required
            $this->menu_slug . '-modules',              // Menu slug
            [$this, 'display_modules']                  // Callback function
        );

        // Logs submenu
        add_submenu_page(
            $this->menu_slug,                           // Parent slug
            __('Logs', 'newera'),                       // Page title
            __('Logs', 'newera'),                       // Menu title
            'manage_options',                           // Capability required
            $this->menu_slug . '-logs',                 // Menu slug
            [$this, 'display_logs']                     // Callback function
        );

        // Projects submenu
        add_submenu_page(
            $this->menu_slug,
            __('Projects', 'newera'),
            __('Projects', 'newera'),
            'manage_options',
            $this->menu_slug . '-projects',
            [$this, 'display_projects']
        );

        // Integrations submenu
        add_submenu_page(
            $this->menu_slug,
            __('Integrations', 'newera'),
            __('Integrations', 'newera'),
            'manage_options',
            $this->menu_slug . '-integrations',
            [$this, 'display_integrations']
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on Newera admin pages
        if (strpos($hook, 'newera') === false) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'newera-admin',
            NEWERA_ASSETS_URL . 'css/admin.css',
            [],
            NEWERA_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'newera-admin',
            NEWERA_ASSETS_URL . 'js/admin.js',
            ['jquery'],
            NEWERA_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('newera-admin', 'newera_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('newera_admin_nonce'),
            'strings' => [
                'confirm_action' => __('Are you sure?', 'newera'),
                'loading' => __('Loading...', 'newera'),
                'error' => __('An error occurred', 'newera')
            ]
        ]);
    }

    /**
     * Display the main dashboard
     */
    public function display_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'newera'));
        }

        $dashboard = new Dashboard();
        $dashboard->display();
    }

    /**
     * Display the settings page
     */
    public function display_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'newera'));
        }

        // Handle form submission
        if (isset($_POST['newera_save_settings']) && wp_verify_nonce($_POST['newera_settings_nonce'], 'newera_settings_action')) {
            $this->save_settings($_POST);
        }

        $this->render_settings_page();
    }

    /**
     * Display the modules page
     */
    public function display_modules() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'newera'));
        }

        // Handle module status changes
        if (isset($_POST['action']) && $_POST['action'] === 'toggle_module') {
            $this->handle_module_toggle($_POST['module_id'], $_POST['enable'] === 'true');
        }

        $this->render_modules_page();
    }

    /**
     * Display the logs page
     */
    public function display_logs() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'newera'));
        }

        $this->render_logs_page();
    }

    /**
     * Display the projects page.
     */
    public function display_projects() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'newera'));
        }

        if (
            isset($_POST['newera_projects_action'], $_POST['newera_projects_nonce'])
            && wp_verify_nonce($_POST['newera_projects_nonce'], 'newera_projects_action')
        ) {
            $this->handle_projects_action($_POST);
        }

        $this->render_projects_page();
    }

    /**
     * Display the integrations page.
     */
    public function display_integrations() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'newera'));
        }

        if (
            isset($_POST['newera_integrations_action'], $_POST['newera_integrations_nonce'])
            && wp_verify_nonce($_POST['newera_integrations_nonce'], 'newera_integrations_action')
        ) {
            $this->handle_integrations_action($_POST);
        }

        $this->render_integrations_page();
    }

    /**
     * Save settings
     *
     * @param array $data POST data
     */
    private function save_settings($data) {
        $settings = [];

        // Sanitize and save settings
        foreach ($data as $key => $value) {
            if (strpos($key, 'newera_setting_') === 0) {
                $setting_key = str_replace('newera_setting_', '', $key);
                $settings[$setting_key] = sanitize_text_field($value);
            }
        }

        // Update settings via StateManager
        if (class_exists('\\Newera\\Core\\StateManager')) {
            $state_manager = new \Newera\Core\StateManager();
            $state_manager->update_state('settings', $settings);
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'newera') . '</p></div>';
            });
        }
    }

    /**
     * Handle module toggle
     *
     * @param string $module_id
     * @param bool $enable
     */
    private function handle_module_toggle($module_id, $enable) {
        if (!wp_verify_nonce($_POST['module_nonce'], 'newera_module_action')) {
            wp_die(__('Security check failed.', 'newera'));
        }

        // Update module status via ModulesRegistry
        if (class_exists('\\Newera\\Core\\ModulesRegistry')) {
            $modules_registry = new \Newera\Core\ModulesRegistry();
            
            if ($enable) {
                $modules_registry->enable_module($module_id);
            } else {
                $modules_registry->disable_module($module_id);
            }
            
            add_action('admin_notices', function() use ($enable) {
                $message = $enable ? __('Module enabled successfully!', 'newera') : __('Module disabled successfully!', 'newera');
                echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
            });
        }
    }

    /**
     * Render settings page
     */
    private function render_settings_page() {
        $settings = [];
        
        // Get current settings
        if (class_exists('\\Newera\\Core\\StateManager')) {
            $state_manager = new \Newera\Core\StateManager();
            $settings = $state_manager->get_settings();
        }

        include NEWERA_PLUGIN_PATH . 'templates/admin/settings.php';
    }

    /**
     * Render modules page
     */
    private function render_modules_page() {
        $modules = [];
        
        // Get modules registry
        if (class_exists('\\Newera\\Core\\ModulesRegistry')) {
            $modules_registry = new \Newera\Core\ModulesRegistry();
            $modules = $modules_registry->get_modules();
        }

        include NEWERA_PLUGIN_PATH . 'templates/admin/modules.php';
    }

    /**
     * Render logs page
     */
    private function render_logs_page() {
        $log_file = WP_CONTENT_DIR . '/newera-logs/newera.log';
        $log_content = file_exists($log_file) ? file_get_contents($log_file) : '';

        include NEWERA_PLUGIN_PATH . 'templates/admin/logs.php';
    }

    /**
     * Handle create/delete actions for projects.
     *
     * @param array $post
     */
    private function handle_projects_action($post) {
        $action = isset($post['projects_action_type']) ? sanitize_key($post['projects_action_type']) : '';

        $project_manager = function_exists('newera_get_project_manager') ? newera_get_project_manager() : null;
        if (!$project_manager || !method_exists($project_manager, 'create_project')) {
            return;
        }

        if ($action === 'create') {
            $result = $project_manager->create_project([
                'client_id' => isset($post['client_id']) ? (int) $post['client_id'] : 0,
                'title' => isset($post['title']) ? sanitize_text_field($post['title']) : '',
                'description' => isset($post['description']) ? wp_kses_post($post['description']) : '',
                'status' => isset($post['status']) ? sanitize_key($post['status']) : 'pending',
                'progress' => isset($post['progress']) ? (int) $post['progress'] : 0,
            ], get_current_user_id());

            if (is_wp_error($result)) {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>' . esc_html__('Project created.', 'newera') . '</p></div>';
                });
            }
        }

        if ($action === 'delete' && !empty($post['project_id'])) {
            $result = $project_manager->delete_project((int) $post['project_id'], get_current_user_id());

            if (is_wp_error($result)) {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>' . esc_html__('Project deleted.', 'newera') . '</p></div>';
                });
            }
        }
    }

    /**
     * Render projects page.
     */
    private function render_projects_page() {
        $project_manager = function_exists('newera_get_project_manager') ? newera_get_project_manager() : null;
        $projects = $project_manager && method_exists($project_manager, 'list_projects_for_user')
            ? $project_manager->list_projects_for_user(get_current_user_id(), ['limit' => 100])
            : [];

        include NEWERA_PLUGIN_PATH . 'templates/admin/projects.php';
    }

    /**
     * Handle integration settings.
     *
     * @param array $post
     */
    private function handle_integrations_action($post) {
        $linear = function_exists('newera_get_linear_manager') ? newera_get_linear_manager() : null;
        $notion = function_exists('newera_get_notion_manager') ? newera_get_notion_manager() : null;
        $state_manager = function_exists('newera_get_state_manager') ? newera_get_state_manager() : null;

        if ($linear) {
            if (!empty($post['linear_api_key'])) {
                $linear->set_api_key($post['linear_api_key']);
            }

            if (!empty($post['linear_webhook_secret'])) {
                $linear->set_webhook_secret($post['linear_webhook_secret']);
            }

            if (!empty($post['linear_team_id'])) {
                $linear->set_team_id($post['linear_team_id']);
            }

            $visible = isset($post['linear_visible_states']) && is_array($post['linear_visible_states'])
                ? array_values(array_filter(array_map('sanitize_text_field', $post['linear_visible_states'])))
                : [];

            if ($state_manager && method_exists($state_manager, 'update_state')) {
                $state_manager->update_state('integrations_linear_visible_states', $visible);
            }

            if (!empty($post['linear_sync_now'])) {
                $linear->sync_now();
            }
        }

        if ($notion) {
            if (!empty($post['notion_api_key'])) {
                $notion->set_api_key($post['notion_api_key']);
            }

            if (!empty($post['notion_webhook_secret'])) {
                $notion->set_webhook_secret($post['notion_webhook_secret']);
            }

            if (!empty($post['notion_projects_database_id'])) {
                $notion->set_projects_database_id($post['notion_projects_database_id']);
            }

            $mappings = [];
            if (isset($post['notion_db_map']) && is_array($post['notion_db_map'])) {
                foreach ($post['notion_db_map'] as $db_id => $project_id) {
                    $db_id = sanitize_text_field($db_id);
                    $project_id = (int) $project_id;
                    if ($db_id !== '' && $project_id > 0) {
                        $mappings[$db_id] = $project_id;
                    }
                }
            }

            if (isset($post['notion_db_map'])) {
                $notion->set_database_mappings($mappings);
            }

            if (!empty($post['notion_sync_now'])) {
                $notion->sync_now();
            }
        }

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . esc_html__('Integrations saved.', 'newera') . '</p></div>';
        });
    }

    /**
     * Render integrations page.
     */
    private function render_integrations_page() {
        $linear = function_exists('newera_get_linear_manager') ? newera_get_linear_manager() : null;
        $notion = function_exists('newera_get_notion_manager') ? newera_get_notion_manager() : null;
        $state_manager = function_exists('newera_get_state_manager') ? newera_get_state_manager() : null;
        $project_manager = function_exists('newera_get_project_manager') ? newera_get_project_manager() : null;

        $projects = $project_manager && method_exists($project_manager, 'list_projects_for_user')
            ? $project_manager->list_projects_for_user(get_current_user_id(), ['limit' => 200])
            : [];

        $linear_states = [];
        $linear_teams = [];

        if ($linear && method_exists($linear, 'is_configured') && $linear->is_configured()) {
            $linear_states = $linear->fetch_workflow_states();
            if (is_wp_error($linear_states)) {
                $linear_states = [];
            }

            $linear_teams = $linear->fetch_teams();
            if (is_wp_error($linear_teams)) {
                $linear_teams = [];
            }
        }

        $visible_states = $state_manager && method_exists($state_manager, 'get_state_value')
            ? $state_manager->get_state_value('integrations_linear_visible_states', [])
            : [];

        if (!is_array($visible_states)) {
            $visible_states = [];
        }

        $notion_databases = [];
        if ($notion && method_exists($notion, 'is_configured') && $notion->is_configured()) {
            $notion_databases = $notion->search_databases();
            if (is_wp_error($notion_databases)) {
                $notion_databases = [];
            }
        }

        $notion_mappings = $notion && method_exists($notion, 'get_database_mappings')
            ? $notion->get_database_mappings()
            : [];

        include NEWERA_PLUGIN_PATH . 'templates/admin/integrations.php';
    }

    /**
     * Get menu icon SVG
     *
     * @return string
     */
    private function get_menu_icon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>';
    }

    /**
     * Dismiss notice handler
     */
    public function dismiss_notice() {
        check_ajax_referer('newera_admin_nonce', 'nonce');
        
        $notice_id = sanitize_text_field($_POST['notice_id']);
        
        // Mark notice as dismissed for current user
        $dismissed_notices = get_user_meta(get_current_user_id(), 'newera_dismissed_notices', true);
        if (!is_array($dismissed_notices)) {
            $dismissed_notices = [];
        }
        
        $dismissed_notices[] = $notice_id;
        update_user_meta(get_current_user_id(), 'newera_dismissed_notices', $dismissed_notices);
        
        wp_send_json_success();
    }
}