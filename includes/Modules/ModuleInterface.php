<?php
/**
 * Module Interface for Newera Plugin
 *
 * Defines the lifecycle and metadata methods for integration modules.
 */

namespace Newera\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface ModuleInterface
 */
interface ModuleInterface {
    /**
     * Unique module identifier (used as namespace for config and secure credentials).
     *
     * @return string
     */
    public function getId();

    /**
     * Human readable name.
     *
     * @return string
     */
    public function getName();

    /**
     * Human readable description.
     *
     * @return string
     */
    public function getDescription();

    /**
     * Module type/category (e.g. auth, payments, database, integrations).
     *
     * @return string
     */
    public function getType();

    /**
     * Settings schema definition for configuration UIs (wizard/admin).
     *
     * @return array
     */
    public function getSettingsSchema();

    /**
     * Validate a credentials payload before storing.
     *
     * @param array $credentials
     * @return array Array with keys: valid (bool), errors (array)
     */
    public function validateCredentials($credentials);

    /**
     * Whether the module has enough configuration/credentials to be considered configured.
     *
     * @return bool
     */
    public function isConfigured();

    /**
     * Set active state (driven by wizard/config storage).
     *
     * @param bool $active
     */
    public function setActive($active);

    /**
     * Whether the module should execute hooks.
     *
     * @return bool
     */
    public function isActive();

    /**
     * Boot the module (called during plugin init for all discovered modules).
     */
    public function boot();

    /**
     * Register WordPress hooks/filters (should be a no-op when inactive).
     */
    public function registerHooks();
}
