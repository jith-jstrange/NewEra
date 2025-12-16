<?php
/**
 * State Manager class for Newera Plugin
 *
 * Manages plugin state, options, configuration, and secure credential storage.
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
        'setup_wizard' => [
            'completed' => false,
            'completed_at' => null,
            'current_step' => 'intro',
            'completed_steps' => [],
            'data' => [],
            'last_updated' => null,
        ],
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
     * Crypto instance for encryption/decryption
     *
     * @var Crypto
     */
    private $crypto;

    /**
     * Constructor
     */
    public function __construct() {
        $this->state = $this->get_state();
        $this->crypto = new Crypto();
    }

    /**
     * Get Crypto instance
     *
     * @return Crypto
     */
    public function get_crypto() {
        return $this->crypto;
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

    /**
     * Store secure data for a module with encryption
     *
     * @param string $module Module namespace (e.g., 'payment_gateway', 'api_client')
     * @param string $key Identifier for the secure data
     * @param mixed $data Data to encrypt and store
     * @return bool Success status
     */
    public function setSecure($module, $key, $data) {
        $option_name = $this->get_secure_option_name($module, $key);
        
        // Encrypt the data
        $encrypted_data = $this->crypto->encrypt($data);
        
        if ($encrypted_data === false) {
            return false;
        }
        
        // Store the encrypted data
        return add_option($option_name, $encrypted_data, '', 'no');
    }

    /**
     * Retrieve and decrypt secure data for a module
     *
     * @param string $module Module namespace
     * @param string $key Identifier for the secure data
     * @param mixed $default Default value if data doesn't exist
     * @return mixed Decrypted data or default value
     */
    public function getSecure($module, $key, $default = null) {
        $option_name = $this->get_secure_option_name($module, $key);
        $encrypted_data = get_option($option_name);
        
        if (!$encrypted_data) {
            return $default;
        }
        
        // Decrypt the data
        $decrypted_data = $this->crypto->decrypt($encrypted_data);
        
        return $decrypted_data !== false ? $decrypted_data : $default;
    }

    /**
     * Delete secure data for a module
     *
     * @param string $module Module namespace
     * @param string $key Identifier for the secure data
     * @return bool Success status
     */
    public function deleteSecure($module, $key) {
        $option_name = $this->get_secure_option_name($module, $key);
        return delete_option($option_name);
    }

    /**
     * Check if secure data exists for a module
     *
     * @param string $module Module namespace
     * @param string $key Identifier for the secure data
     * @return bool Whether the secure data exists
     */
    public function hasSecure($module, $key) {
        $option_name = $this->get_secure_option_name($module, $key);
        return get_option($option_name) !== false;
    }

    /**
     * Get all secure data keys for a module
     *
     * @param string $module Module namespace
     * @return array Array of keys that have secure data stored
     */
    public function getSecureKeys($module) {
        global $wpdb;
        
        $pattern = $this->get_secure_option_pattern($module);
        $like_pattern = $this->escape_like($pattern);
        
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            '%' . $like_pattern . '%'
        ));
        
        $keys = [];
        foreach ($results as $option_name) {
            $key = $this->extract_key_from_option_name($option_name, $module);
            if ($key) {
                $keys[] = $key;
            }
        }
        
        return $keys;
    }

    /**
     * Get all secure data for a module
     *
     * @param string $module Module namespace
     * @return array Associative array of key => decrypted data
     */
    public function getAllSecure($module) {
        $keys = $this->getSecureKeys($module);
        $result = [];
        
        foreach ($keys as $key) {
            $result[$key] = $this->getSecure($module, $key);
        }
        
        return $result;
    }

    /**
     * Generate secure option name for storage
     *
     * @param string $module Module namespace
     * @param string $key Data key
     * @return string Option name
     */
    private function get_secure_option_name($module, $key) {
        $hash = hash('sha256', $module . $key . wp_salt('nonce'));
        return 'newera_secure_' . $hash;
    }

    /**
     * Generate pattern for secure option names
     *
     * @param string $module Module namespace
     * @return string Pattern for LIKE queries
     */
    private function get_secure_option_pattern($module) {
        return 'newera_secure_%';
    }

    /**
     * Extract key from option name
     *
     * @param string $option_name Option name from database
     * @param string $module Module namespace
     * @return string|false Key or false if not found
     */
    private function extract_key_from_option_name($option_name, $module) {
        // This is a simplified approach - in practice, we'd need to store
        // the key mapping in a separate option or use a different approach
        // For now, we'll return a hash-based identifier
        return substr(md5($option_name), 0, 8);
    }

    /**
     * Escape SQL LIKE pattern
     *
     * @param string $pattern Pattern to escape
     * @return string Escaped pattern
     */
    private function escape_like($pattern) {
        global $wpdb;
        
        $pattern = str_replace(['%', '_'], ['\\%', '\\_'], $pattern);
        return $pattern;
    }

    /**
     * Update existing secure data for a module
     *
     * @param string $module Module namespace
     * @param string $key Identifier for the secure data
     * @param mixed $data Data to encrypt and store
     * @return bool Success status
     */
    public function updateSecure($module, $key, $data) {
        $option_name = $this->get_secure_option_name($module, $key);
        
        // Encrypt the data
        $encrypted_data = $this->crypto->encrypt($data);
        
        if ($encrypted_data === false) {
            return false;
        }
        
        // Update the encrypted data
        return update_option($option_name, $encrypted_data, 'no');
    }

    /**
     * Bulk store secure data for a module
     *
     * @param string $module Module namespace
     * @param array $data_array Associative array of key => data
     * @return array Results array with key => success status
     */
    public function setBulkSecure($module, $data_array) {
        $results = [];
        
        foreach ($data_array as $key => $data) {
            $results[$key] = $this->setSecure($module, $key, $data);
        }
        
        return $results;
    }

    /**
     * Bulk retrieve secure data for a module
     *
     * @param string $module Module namespace
     * @param array $keys Array of keys to retrieve
     * @return array Associative array of key => decrypted data
     */
    public function getBulkSecure($module, $keys) {
        $results = [];
        
        foreach ($keys as $key) {
            $results[$key] = $this->getSecure($module, $key);
        }
        
        return $results;
    }

    /**
     * Check if crypto is available and working
     *
     * @return bool
     */
    public function is_crypto_available() {
        return $this->crypto->is_available();
    }

    /**
     * Get metadata for encrypted data
     *
     * @param string $module Module namespace
     * @param string $key Identifier for the secure data
     * @return array Metadata array
     */
    public function getSecureMetadata($module, $key) {
        $option_name = $this->get_secure_option_name($module, $key);
        $encrypted_data = get_option($option_name);
        
        if (!$encrypted_data) {
            return [];
        }
        
        return $this->crypto->get_metadata($encrypted_data);
    }
}