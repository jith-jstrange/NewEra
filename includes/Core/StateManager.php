<?php
/**
 * State Manager class for Newera Plugin
 *
 * Manages plugin state, options, and configuration.
 */

namespace Newera\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * State Manager class
 */
class StateManager {
    /**
     * Option name for storing plugin state
     *
     * @var string
     */
    const OPTION_NAME = 'newera_plugin_state';

    /**
     * Default plugin state
     *
     * @var array
     */
    private $default_state = [
        'version' => NEWERA_VERSION,
        'activated' => false,
        'install_date' => null,
        'last_migration' => null,
        'modules_enabled' => [],
        'settings' => [],
        'health_check' => [
            'last_run' => null,
            'status' => 'ok',
            'issues' => []
        ]
    ];

    /**
     * Current plugin state
     *
     * @var array
     */
    private $state;

    /**
     * Constructor
     */
    public function __construct() {
        $this->state = $this->get_state();
    }

    /**
     * Initialize the state manager
     */
    public function init() {
        $this->ensure_default_state();
        $this->check_version_compatibility();
    }

    /**
     * Get current plugin state
     *
     * @return array
     */
    public function get_state() {
        if ($this->state === null) {
            $saved_state = get_option(self::OPTION_NAME);
            $this->state = $saved_state ? $saved_state : $this->default_state;
        }
        return $this->state;
    }

    /**
     * Update plugin state
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function update_state($key, $value) {
        $this->state[$key] = $value;
        return $this->save_state();
    }

    /**
     * Get a specific state value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get_state_value($key, $default = null) {
        $state = $this->get_state();
        return isset($state[$key]) ? $state[$key] : $default;
    }

    /**
     * Set a specific state value
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function set_state_value($key, $value) {
        return $this->update_state($key, $value);
    }

    /**
     * Get all plugin settings
     *
     * @return array
     */
    public function get_settings() {
        return $this->get_state_value('settings', []);
    }

    /**
     * Get a specific setting
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get_setting($key, $default = null) {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Update a specific setting
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function update_setting($key, $value) {
        $settings = $this->get_settings();
        $settings[$key] = $value;
        return $this->update_state('settings', $settings);
    }

    /**
     * Check if plugin is properly activated
     *
     * @return bool
     */
    public function is_activated() {
        return $this->get_state_value('activated', false);
    }

    /**
     * Mark plugin as activated
     *
     * @return bool
     */
    public function mark_as_activated() {
        $this->update_state('activated', true);
        return $this->update_state('install_date', current_time('mysql'));
    }

    /**
     * Check version compatibility
     *
     * @return bool
     */
    private function check_version_compatibility() {
        $current_version = $this->get_state_value('version');
        
        if ($current_version !== NEWERA_VERSION) {
            // Update version and run migration checks
            $this->update_state('version', NEWERA_VERSION);
            
            // Trigger version migration if needed
            do_action('newera_version_update', $current_version, NEWERA_VERSION);
        }
        
        return true;
    }

    /**
     * Ensure default state is set
     */
    private function ensure_default_state() {
        $current_state = $this->get_state();
        $updated_state = array_merge($this->default_state, $current_state);
        
        if ($current_state !== $updated_state) {
            $this->state = $updated_state;
            $this->save_state();
        }
    }

    /**
     * Save current state to database
     *
     * @return bool
     */
    private function save_state() {
        return update_option(self::OPTION_NAME, $this->state);
    }

    /**
     * Reset plugin state (for development/testing)
     *
     * @return bool
     */
    public function reset_state() {
        delete_option(self::OPTION_NAME);
        $this->state = $this->default_state;
        return $this->save_state();
    }

    /**
     * Get health check information
     *
     * @return array
     */
    public function get_health_info() {
        return $this->get_state_value('health_check', []);
    }

    /**
     * Update health check information
     *
     * @param string $status
     * @param array $issues
     * @return bool
     */
    public function update_health_info($status = 'ok', $issues = []) {
        $health_check = [
            'last_run' => current_time('mysql'),
            'status' => $status,
            'issues' => $issues
        ];
        
        return $this->update_state('health_check', $health_check);
    }
}