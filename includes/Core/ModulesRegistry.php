<?php
/**
 * Modules Registry class for Newera Plugin
 *
 * Manages and registers all plugin modules.
 */

namespace Newera\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Modules Registry class
 */
class ModulesRegistry {
    /**
     * Registered modules
     *
     * @var array
     */
    private $modules = [];

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize with built-in modules
        $this->init_built_in_modules();
    }

    /**
     * Initialize the modules registry
     */
    public function init() {
        // Register modules from filters
        $this->register_modules_from_filters();
        
        // Allow other plugins/themes to register modules
        do_action('newera_register_modules', $this);
        
        // Sort modules by priority
        uasort($this->modules, [$this, 'sort_modules_by_priority']);
    }

    /**
     * Initialize built-in modules
     */
    private function init_built_in_modules() {
        // These are placeholder modules - implement actual modules as needed
        $this->register_module('dashboard', [
            'name' => 'Dashboard',
            'description' => 'Main dashboard module',
            'class' => '\\Newera\\Modules\\DashboardModule',
            'priority' => 10,
            'dependencies' => [],
            'capabilities' => ['manage_options']
        ]);

        $this->register_module('settings', [
            'name' => 'Settings',
            'description' => 'Plugin settings management',
            'class' => '\\Newera\\Modules\\SettingsModule',
            'priority' => 15,
            'dependencies' => [],
            'capabilities' => ['manage_options']
        ]);

        $this->register_module('content', [
            'name' => 'Content',
            'description' => 'Content management module',
            'class' => '\\Newera\\Modules\\ContentModule',
            'priority' => 20,
            'dependencies' => [],
            'capabilities' => ['edit_posts']
        ]);

        $this->register_module('api', [
            'name' => 'API',
            'description' => 'REST API endpoints',
            'class' => '\\Newera\\Modules\\ApiModule',
            'priority' => 5,
            'dependencies' => [],
            'capabilities' => []
        ]);
    }

    /**
     * Register a module
     *
     * @param string $module_id
     * @param array $module_config
     */
    public function register_module($module_id, $module_config) {
        // Set default configuration
        $default_config = [
            'name' => '',
            'description' => '',
            'class' => '',
            'priority' => 10,
            'dependencies' => [],
            'capabilities' => [],
            'enabled' => true
        ];

        $module_config = array_merge($default_config, $module_config);
        $module_config['id'] = $module_id;

        $this->modules[$module_id] = $module_config;
    }

    /**
     * Unregister a module
     *
     * @param string $module_id
     */
    public function unregister_module($module_id) {
        unset($this->modules[$module_id]);
    }

    /**
     * Get all registered modules
     *
     * @return array
     */
    public function get_modules() {
        return $this->modules;
    }

    /**
     * Get a specific module
     *
     * @param string $module_id
     * @return array|null
     */
    public function get_module($module_id) {
        return isset($this->modules[$module_id]) ? $this->modules[$module_id] : null;
    }

    /**
     * Check if a module is enabled
     *
     * @param string $module_id
     * @return bool
     */
    public function is_module_enabled($module_id) {
        $module = $this->get_module($module_id);
        return $module && $module['enabled'];
    }

    /**
     * Enable a module
     *
     * @param string $module_id
     * @return bool
     */
    public function enable_module($module_id) {
        if (isset($this->modules[$module_id])) {
            $this->modules[$module_id]['enabled'] = true;
            return true;
        }
        return false;
    }

    /**
     * Disable a module
     *
     * @param string $module_id
     * @return bool
     */
    public function disable_module($module_id) {
        if (isset($this->modules[$module_id])) {
            $this->modules[$module_id]['enabled'] = false;
            return true;
        }
        return false;
    }

    /**
     * Check if user has capability to access a module
     *
     * @param string $module_id
     * @param int $user_id
     * @return bool
     */
    public function user_can_access_module($module_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $module = $this->get_module($module_id);
        if (!$module || !$module['enabled']) {
            return false;
        }

        // If no capabilities required, allow access
        if (empty($module['capabilities'])) {
            return true;
        }

        // Check if user has any of the required capabilities
        foreach ($module['capabilities'] as $capability) {
            if (user_can($user_id, $capability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get modules by priority
     *
     * @return array
     */
    public function get_modules_by_priority() {
        $modules = $this->get_modules();
        uasort($modules, [$this, 'sort_modules_by_priority']);
        return $modules;
    }

    /**
     * Sort modules by priority
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private function sort_modules_by_priority($a, $b) {
        return ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10);
    }

    /**
     * Register modules from WordPress filters
     */
    private function register_modules_from_filters() {
        $external_modules = apply_filters('newera_external_modules', []);
        
        foreach ($external_modules as $module_id => $module_config) {
            $this->register_module($module_id, $module_config);
        }
    }

    /**
     * Get enabled modules for current user
     *
     * @param int $user_id
     * @return array
     */
    public function get_enabled_modules_for_user($user_id = null) {
        $enabled_modules = [];
        
        foreach ($this->modules as $module_id => $module_config) {
            if ($this->is_module_enabled($module_id) && 
                $this->user_can_access_module($module_id, $user_id)) {
                $enabled_modules[$module_id] = $module_config;
            }
        }
        
        return $enabled_modules;
    }
}