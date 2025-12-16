<?php
/**
 * Base Module class for Newera Plugin
 *
 * Provides common helpers for logging, state access, and secure credential storage.
 */

namespace Newera\Modules;

use Newera\Core\Logger;
use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BaseModule
 */
abstract class BaseModule implements ModuleInterface {
    /**
     * @var StateManager|null
     */
    protected $state_manager;

    /**
     * @var Logger|null
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $active = false;

    /**
     * @param StateManager|null $state_manager
     * @param Logger|null $logger
     */
    public function __construct($state_manager = null, $logger = null) {
        $this->state_manager = $state_manager;
        $this->logger = $logger;

        if ($this->state_manager === null && function_exists('apply_filters')) {
            $this->state_manager = apply_filters('newera_get_state_manager', null);
        }

        if ($this->logger === null && function_exists('apply_filters')) {
            $this->logger = apply_filters('newera_get_logger', null);
        }
    }

    /**
     * @return string
     */
    public function getDescription() {
        return '';
    }

    /**
     * @return array
     */
    public function getSettingsSchema() {
        return [];
    }

    /**
     * @param array $credentials
     * @return array
     */
    public function validateCredentials($credentials) {
        return [
            'valid' => true,
            'errors' => [],
        ];
    }

    /**
     * @return bool
     */
    public function isConfigured() {
        return false;
    }

    /**
     * @param bool $active
     */
    public function setActive($active) {
        $this->active = (bool) $active;
    }

    /**
     * @return bool
     */
    public function isActive() {
        return (bool) $this->active;
    }

    /**
     * Boot the module. By default this only registers hooks when active.
     */
    public function boot() {
        $this->log_info('Module boot', [
            'module' => $this->getId(),
            'active' => $this->isActive(),
            'configured' => $this->isConfigured(),
        ]);

        if ($this->isActive()) {
            $this->registerHooks();
        }
    }

    /**
     * Register WordPress hooks/filters.
     */
    public function registerHooks() {
        // No-op.
    }

    /**
     * @return StateManager|null
     */
    protected function get_state_manager() {
        return $this->state_manager;
    }

    /**
     * @return Logger|null
     */
    protected function get_logger() {
        return $this->logger;
    }

    /**
     * @param string $message
     * @param array $context
     */
    protected function log_info($message, $context = []) {
        if ($this->logger) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * @param string $message
     * @param array $context
     */
    protected function log_warning($message, $context = []) {
        if ($this->logger) {
            $this->logger->warning($message, $context);
        }
    }

    /**
     * @param string $message
     * @param array $context
     */
    protected function log_error($message, $context = []) {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }

    /**
     * Store a credential securely under this module namespace.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    protected function set_credential($key, $value) {
        if (!$this->state_manager) {
            $this->log_warning('StateManager unavailable; cannot store secure credential', [
                'module' => $this->getId(),
                'key' => $key,
            ]);
            return false;
        }

        if (method_exists($this->state_manager, 'hasSecure') && $this->state_manager->hasSecure($this->getId(), $key)) {
            return $this->state_manager->updateSecure($this->getId(), $key, $value);
        }

        return $this->state_manager->setSecure($this->getId(), $key, $value);
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function get_credential($key, $default = null) {
        if (!$this->state_manager) {
            return $default;
        }

        return $this->state_manager->getSecure($this->getId(), $key, $default);
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function has_credential($key) {
        if (!$this->state_manager) {
            return false;
        }

        return $this->state_manager->hasSecure($this->getId(), $key);
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function delete_credential($key) {
        if (!$this->state_manager) {
            return false;
        }

        return $this->state_manager->deleteSecure($this->getId(), $key);
    }

    /**
     * Get module configuration stored by wizard/admin in StateManager settings.
     *
     * @return array
     */
    protected function get_module_settings() {
        if (!$this->state_manager) {
            return [];
        }

        $modules = $this->state_manager->get_setting('modules', []);
        return isset($modules[$this->getId()]) && is_array($modules[$this->getId()]) ? $modules[$this->getId()] : [];
    }

    /**
     * Update module configuration stored by wizard/admin in StateManager settings.
     *
     * @param array $settings
     * @return bool
     */
    protected function update_module_settings($settings) {
        if (!$this->state_manager) {
            return false;
        }

        $modules = $this->state_manager->get_setting('modules', []);
        if (!is_array($modules)) {
            $modules = [];
        }

        $current = isset($modules[$this->getId()]) && is_array($modules[$this->getId()]) ? $modules[$this->getId()] : [];
        $modules[$this->getId()] = array_merge($current, $settings);

        return $this->state_manager->update_setting('modules', $modules);
    }
}
