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