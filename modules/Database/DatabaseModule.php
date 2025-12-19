<?php
/**
 * Database Module (stub)
 *
 * Demonstrates storing external database credentials per module.
 */

namespace Newera\Modules\Database;

use Newera\Modules\BaseModule;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DatabaseModule
 */
class DatabaseModule extends BaseModule {
    /**
     * @return string
     */
    public function getId() {
        return 'database';
    }

    /**
     * @return string
     */
    public function getName() {
        return 'Database';
    }

    /**
     * @return string
     */
    public function getDescription() {
        return 'Database module stub (external connection credentials example).';
    }

    /**
     * @return string
     */
    public function getType() {
        return 'database';
    }

    /**
     * @return array
     */
    public function getSettingsSchema() {
        return [
            'credentials' => [
                'host' => [
                    'type' => 'string',
                    'label' => 'Host',
                    'required' => true,
                    'secure' => false,
                ],
                'username' => [
                    'type' => 'string',
                    'label' => 'Username',
                    'required' => true,
                    'secure' => true,
                ],
                'password' => [
                    'type' => 'string',
                    'label' => 'Password',
                    'required' => true,
                    'secure' => true,
                ],
            ],
        ];
    }

    /**
     * @param array $credentials
     * @return array
     */
    public function validateCredentials($credentials) {
        $errors = [];

        if (empty($credentials['host'])) {
            $errors['host'] = 'Host is required.';
        }

        if (empty($credentials['username'])) {
            $errors['username'] = 'Username is required.';
        }

        if (empty($credentials['password'])) {
            $errors['password'] = 'Password is required.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param array $credentials
     * @return bool
     */
    public function saveCredentials($credentials) {
        $validation = $this->validateCredentials($credentials);
        if (!$validation['valid']) {
            $this->log_warning('Database credentials validation failed', [
                'errors' => $validation['errors'],
            ]);
            return false;
        }

        $ok1 = $this->set_credential('host', $credentials['host']);
        $ok2 = $this->set_credential('username', $credentials['username']);
        $ok3 = $this->set_credential('password', $credentials['password']);

        return $ok1 && $ok2 && $ok3;
    }

    /**
     * @return bool
     */
    public function isConfigured() {
        return $this->has_credential('host') && $this->has_credential('username') && $this->has_credential('password');
    }

    /**
     * Switch Database to Neon
     * 
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function switchDatabaseToNeon($params) {
        $connection_string = $params['connection_string'] ?? '';
        
        if (empty($connection_string)) {
            throw new \Exception('Connection string is required');
        }
        
        $this->update_setting('db_provider', 'neon');
        $this->set_credential('connection_string', $connection_string);
        
        return ['message' => 'Database switched to Neon successfully'];
    }

    /**
     * Register hooks only when active.
     */
    public function registerHooks() {
        add_action('init', [$this, 'init_database']);
    }

    /**
     * Stub hook.
     */
    public function init_database() {
        $this->log_info('Database module active');
    }
}
