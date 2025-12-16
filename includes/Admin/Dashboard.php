<?php
/**
 * Dashboard class for Newera Plugin Admin
 *
 * Handles the main dashboard page display and functionality.
 */

namespace Newera\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard class
 */
class Dashboard {
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor
    }

    /**
     * Display the dashboard
     */
    public function display() {
        // Get plugin state and statistics
        $stats = $this->get_dashboard_stats();
        $recent_logs = $this->get_recent_logs();
        $modules_status = $this->get_modules_status();
        $health_info = $this->get_health_info();
        $db_health = $this->get_database_health();

        // Include the dashboard template
        include NEWERA_PLUGIN_PATH . 'templates/admin/dashboard.php';
    }

    /**
     * Get dashboard statistics
     *
     * @return array
     */
    private function get_dashboard_stats() {
        $stats = [
            'plugin_version' => NEWERA_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'active_modules' => 0,
            'total_modules' => 0,
            'last_migration' => null,
            'activation_date' => null
        ];

        // Get state manager data
        if (class_exists('\\Newera\\Core\\StateManager')) {
            $state_manager = new \Newera\Core\StateManager();
            
            $stats['last_migration'] = $state_manager->get_state_value('last_migration');
            $stats['activation_date'] = $state_manager->get_state_value('install_date');
        }

        // Get modules registry data
        if (class_exists('\\Newera\\Core\\ModulesRegistry')) {
            $modules_registry = new \Newera\Core\ModulesRegistry();
            $modules = $modules_registry->get_modules();
            
            $stats['total_modules'] = count($modules);
            $stats['active_modules'] = count(array_filter($modules, function($module) {
                return $module['enabled'];
            }));
        }

        return $stats;
    }

    /**
     * Get recent logs (last 50 lines)
     *
     * @return array
     */
    private function get_recent_logs() {
        $log_file = WP_CONTENT_DIR . '/newera-logs/newera.log';
        
        if (!file_exists($log_file)) {
            return [];
        }

        $lines = file($log_file, FILE_IGNORE_NEW_LINES);
        
        if (!$lines) {
            return [];
        }

        // Get last 50 lines
        $recent_lines = array_slice($lines, -50);
        
        // Parse log entries
        $log_entries = [];
        foreach (array_reverse($recent_lines) as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $entry = $this->parse_log_line($line);
            if ($entry) {
                $log_entries[] = $entry;
            }
        }

        return $log_entries;
    }

    /**
     * Parse a single log line
     *
     * @param string $line
     * @return array|null
     */
    private function parse_log_line($line) {
        // Expected format: [2023-12-14 11:00:00] [INFO] [Class::method] Message {"context":"data"}
        $pattern = '/\[([^\]]+)\] \[([^\]]+)\] \[([^\]]+)\] (.+)/';
        
        if (preg_match($pattern, $line, $matches)) {
            $timestamp = $matches[1];
            $level = strtolower($matches[2]);
            $caller = $matches[3];
            $message = $matches[4];
            
            // Try to parse context if present
            $context = [];
            if (preg_match('/\{.+\}$/', $message, $context_matches)) {
                $message = trim(substr($message, 0, strrpos($message, $context_matches[0])));
                $context = json_decode($context_matches[0], true);
            }
            
            return [
                'timestamp' => $timestamp,
                'level' => $level,
                'caller' => $caller,
                'message' => $message,
                'context' => $context
            ];
        }
        
        return null;
    }

    /**
     * Get modules status
     *
     * @return array
     */
    private function get_modules_status() {
        $modules_status = [];

        if (!class_exists('\\Newera\\Core\\ModulesRegistry')) {
            return $modules_status;
        }

        $modules_registry = new \Newera\Core\ModulesRegistry();
        $modules = $modules_registry->get_modules();

        foreach ($modules as $module_id => $module_config) {
            $can_access = $modules_registry->user_can_access_module($module_id);
            $is_enabled = $module_config['enabled'];
            
            $modules_status[$module_id] = [
                'name' => $module_config['name'],
                'description' => $module_config['description'],
                'enabled' => $is_enabled,
                'can_access' => $can_access,
                'class_exists' => class_exists($module_config['class']),
                'dependencies' => $module_config['dependencies'],
                'capabilities' => $module_config['capabilities']
            ];
        }

        return $modules_status;
    }

    /**
     * Get health information
     *
     * @return array
     */
    private function get_health_info() {
        $health_info = [
            'status' => 'ok',
            'issues' => [],
            'last_run' => null,
            'checks' => []
        ];

        // Perform basic health checks
        $health_info['checks'] = $this->perform_health_checks();
        
        // Determine overall status
        $has_critical_issues = false;
        $has_warning_issues = false;
        
        foreach ($health_info['checks'] as $check) {
            if ($check['status'] === 'error') {
                $has_critical_issues = true;
            } elseif ($check['status'] === 'warning') {
                $has_warning_issues = true;
            }
        }

        if ($has_critical_issues) {
            $health_info['status'] = 'error';
        } elseif ($has_warning_issues) {
            $health_info['status'] = 'warning';
        }

        // Collect issues
        foreach ($health_info['checks'] as $check) {
            if ($check['status'] !== 'ok') {
                $health_info['issues'][] = $check['message'];
            }
        }

        // Get last run time from state manager
        if (class_exists('\\Newera\\Core\\StateManager')) {
            $state_manager = new \Newera\Core\StateManager();
            $state_health = $state_manager->get_health_info();
            
            if (isset($state_health['last_run'])) {
                $health_info['last_run'] = $state_health['last_run'];
            }
        }

        return $health_info;
    }

    /**
     * Perform health checks
     *
     * @return array
     */
    private function perform_health_checks() {
        $checks = [];

        // Check WordPress version
        $checks[] = [
            'name' => 'WordPress Version',
            'status' => version_compare(get_bloginfo('version'), '5.0', '>=') ? 'ok' : 'error',
            'message' => 'WordPress version: ' . get_bloginfo('version') . ' (requires 5.0+)'
        ];

        // Check PHP version
        $checks[] = [
            'name' => 'PHP Version',
            'status' => version_compare(PHP_VERSION, '7.4', '>=') ? 'ok' : 'error',
            'message' => 'PHP version: ' . PHP_VERSION . ' (requires 7.4+)'
        ];

        // Check plugin files
        $plugin_files = [
            NEWERA_PLUGIN_PATH . 'includes/Core/Bootstrap.php',
            NEWERA_PLUGIN_PATH . 'includes/Core/StateManager.php',
            NEWERA_PLUGIN_PATH . 'includes/Core/ModulesRegistry.php',
            NEWERA_PLUGIN_PATH . 'includes/Core/Logger.php'
        ];

        $missing_files = [];
        foreach ($plugin_files as $file) {
            if (!file_exists($file)) {
                $missing_files[] = basename($file);
            }
        }

        $checks[] = [
            'name' => 'Plugin Files',
            'status' => empty($missing_files) ? 'ok' : 'error',
            'message' => empty($missing_files) ? 'All core files present' : 'Missing files: ' . implode(', ', $missing_files)
        ];

        // Check directory permissions
        $writable_dirs = [
            WP_CONTENT_DIR . '/newera-logs/',
            WP_CONTENT_DIR . '/uploads/'
        ];

        $unwritable_dirs = [];
        foreach ($writable_dirs as $dir) {
            if (!wp_is_writable($dir)) {
                $unwritable_dirs[] = basename($dir);
            }
        }

        $checks[] = [
            'name' => 'Directory Permissions',
            'status' => empty($unwritable_dirs) ? 'ok' : 'warning',
            'message' => empty($unwritable_dirs) ? 'Writable directories: logs' : 'Unwritable: ' . implode(', ', $unwritable_dirs)
        ];

        // Check database connection
        global $wpdb;
        $checks[] = [
            'name' => 'Database Connection',
            'status' => $wpdb->last_error ? 'error' : 'ok',
            'message' => $wpdb->last_error ? 'Database error: ' . $wpdb->last_error : 'Database connection OK'
        ];

        return $checks;
    }

    /**
     * Format timestamp for display
     *
     * @param string $timestamp
     * @return string
     */
    public function format_timestamp($timestamp) {
        if (!$timestamp) {
            return __('Never', 'newera');
        }

        $datetime = new \DateTime($timestamp);
        $now = new \DateTime();
        
        $diff = $now->diff($datetime);
        
        if ($diff->days > 0) {
            return sprintf(__('%s days ago', 'newera'), $diff->days);
        } elseif ($diff->h > 0) {
            return sprintf(__('%s hours ago', 'newera'), $diff->h);
        } elseif ($diff->i > 0) {
            return sprintf(__('%s minutes ago', 'newera'), $diff->i);
        } else {
            return __('Just now', 'newera');
        }
    }

    /**
     * Get log level badge HTML
     *
     * @param string $level
     * @return string
     */
    public function get_log_level_badge($level) {
        $badges = [
            'debug' => '<span class="newera-badge newera-badge-gray">DEBUG</span>',
            'info' => '<span class="newera-badge newera-badge-blue">INFO</span>',
            'warning' => '<span class="newera-badge newera-badge-yellow">WARNING</span>',
            'error' => '<span class="newera-badge newera-badge-red">ERROR</span>'
        ];

        return isset($badges[$level]) ? $badges[$level] : '<span class="newera-badge newera-badge-gray">' . strtoupper($level) . '</span>';
    }

    /**
     * Get health status badge HTML
     *
     * @param string $status
     * @return string
     */
    public function get_health_status_badge($status) {
        $badges = [
            'ok' => '<span class="newera-badge newera-badge-green">OK</span>',
            'warning' => '<span class="newera-badge newera-badge-yellow">WARNING</span>',
            'error' => '<span class="newera-badge newera-badge-red">ERROR</span>'
        ];

        return isset($badges[$status]) ? $badges[$status] : '<span class="newera-badge newera-badge-gray">UNKNOWN</span>';
    }

    /**
     * Get database health information
     *
     * @return array
     */
    private function get_database_health() {
        $db_factory = apply_filters('newera_get_db_factory', null);
        
        if (!$db_factory) {
            return [
                'available' => false,
                'message' => 'Database factory not available'
            ];
        }

        $metrics = $db_factory->get_health_metrics();
        
        return [
            'available' => true,
            'adapter_type' => $metrics['adapter_type'] ?? 'unknown',
            'fallback_active' => $metrics['fallback_active'] ?? false,
            'connected' => $metrics['connected'] ?? false,
            'health_status' => $metrics['health_status'] ?? 'unknown',
            'connection_details' => $metrics['connection_details'] ?? [],
            'last_check' => $metrics['last_check'] ?? null,
        ];
    }
}
