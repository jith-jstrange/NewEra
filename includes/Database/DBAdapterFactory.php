<?php
/**
 * Database Adapter Factory for Newera Plugin
 *
 * Manages database adapter selection with fallback logic.
 * Supports WordPress DB and external PostgreSQL-compatible databases.
 */

namespace Newera\Database;

use Newera\Core\StateManager;
use Newera\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Adapter Factory class
 */
class DBAdapterFactory {
    /**
     * StateManager instance
     *
     * @var StateManager
     */
    private $state_manager;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Active adapter instance
     *
     * @var DBAdapterInterface
     */
    private $adapter;

    /**
     * Fallback adapter (WordPress DB)
     *
     * @var WPDBAdapter
     */
    private $fallback_adapter;

    /**
     * Whether fallback is active
     *
     * @var bool
     */
    private $is_fallback_active = false;

    /**
     * Constructor
     *
     * @param StateManager|null $state_manager
     * @param Logger|null $logger
     */
    public function __construct($state_manager = null, $logger = null) {
        $this->state_manager = $state_manager instanceof StateManager ? $state_manager : new StateManager();
        $this->logger = $logger instanceof Logger ? $logger : new Logger();
        $this->fallback_adapter = new WPDBAdapter();
    }

    /**
     * Get active database adapter
     *
     * @return DBAdapterInterface
     */
    public function get_adapter() {
        if ($this->adapter !== null) {
            return $this->adapter;
        }

        $db_config = $this->get_database_config();

        if ($db_config['type'] === 'external' && !empty($db_config['enabled'])) {
            $this->adapter = $this->create_external_adapter($db_config);
            
            if (!$this->adapter->test_connection()) {
                $this->logger->warning('External database connection failed, falling back to WordPress database');
                $this->is_fallback_active = true;
                $this->adapter = $this->fallback_adapter;
                
                $this->state_manager->update_health_info('degraded', [
                    'External database connection failed - using fallback WordPress database'
                ]);
            } else {
                $this->logger->info('External database connection established successfully');
            }
        } else {
            $this->adapter = $this->fallback_adapter;
        }

        return $this->adapter;
    }

    /**
     * Create external database adapter
     *
     * @param array $config
     * @return ExternalDBAdapter
     */
    private function create_external_adapter($config) {
        $adapter_config = [];

        if (!empty($config['connection_string'])) {
            $adapter_config['connection_string'] = $config['connection_string'];
        } else {
            $adapter_config['driver'] = $config['driver'] ?? 'pgsql';
            $adapter_config['host'] = $config['host'] ?? '';
            $adapter_config['port'] = $config['port'] ?? 5432;
            $adapter_config['database'] = $config['database'] ?? '';
            $adapter_config['username'] = $config['username'] ?? '';
            $adapter_config['password'] = $config['password'] ?? '';
            $adapter_config['sslmode'] = $config['sslmode'] ?? 'prefer';
        }

        $adapter_config['table_prefix'] = $config['table_prefix'] ?? $this->fallback_adapter->get_table_prefix();
        $adapter_config['persistent'] = $config['persistent'] ?? false;

        return new ExternalDBAdapter($adapter_config);
    }

    /**
     * Get database configuration from secure storage
     *
     * @return array
     */
    private function get_database_config() {
        $wizard_state = $this->state_manager->get_state_value('setup_wizard', []);
        $db_step_data = $wizard_state['data']['database'] ?? [];

        $db_type = $db_step_data['db_type'] ?? 'wordpress';

        if ($db_type === 'external') {
            $connection_string = $this->state_manager->getSecure('database', 'connection_string', '');
            
            if (empty($connection_string)) {
                return ['type' => 'wordpress', 'enabled' => false];
            }

            return [
                'type' => 'external',
                'enabled' => true,
                'connection_string' => $connection_string,
                'table_prefix' => $db_step_data['table_prefix'] ?? 'wp_',
                'persistent' => $db_step_data['persistent'] ?? false,
            ];
        }

        return ['type' => 'wordpress', 'enabled' => false];
    }

    /**
     * Test database connection
     *
     * @param string $connection_string
     * @return array Array with 'success' boolean and 'message' string
     */
    public function test_connection($connection_string) {
        try {
            $validation = ExternalDBAdapter::validate_connection_string($connection_string);
            
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['error']
                ];
            }

            $adapter = new ExternalDBAdapter(['connection_string' => $connection_string]);
            
            if ($adapter->test_connection()) {
                $status = $adapter->get_connection_status();
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'details' => [
                        'driver' => $status['driver'] ?? 'unknown',
                        'host' => $status['host'] ?? 'unknown',
                        'database' => $status['database'] ?? 'unknown',
                        'version' => $status['version'] ?? 'unknown',
                    ]
                ];
            }

            return [
                'success' => false,
                'message' => 'Connection test failed'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Database connection test failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Save database configuration
     *
     * @param array $config
     * @return bool
     */
    public function save_configuration($config) {
        try {
            if ($config['db_type'] === 'external') {
                $connection_string = $config['connection_string'] ?? '';
                
                if (empty($connection_string)) {
                    return false;
                }

                $this->state_manager->setSecure('database', 'connection_string', $connection_string);
            }

            $wizard_state = $this->state_manager->get_state_value('setup_wizard', []);
            $wizard_state['data']['database'] = [
                'db_type' => $config['db_type'],
                'table_prefix' => $config['table_prefix'] ?? 'wp_',
                'persistent' => $config['persistent'] ?? false,
            ];
            $this->state_manager->update_state('setup_wizard', $wizard_state);

            $this->adapter = null;

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to save database configuration', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if fallback is active
     *
     * @return bool
     */
    public function is_fallback_active() {
        return $this->is_fallback_active;
    }

    /**
     * Get adapter status
     *
     * @return array
     */
    public function get_status() {
        $adapter = $this->get_adapter();
        $status = $adapter->get_connection_status();

        return [
            'type' => $this->adapter instanceof WPDBAdapter ? 'wordpress' : 'external',
            'fallback_active' => $this->is_fallback_active,
            'connected' => $status['connected'] ?? false,
            'details' => $status
        ];
    }

    /**
     * Run migrations on external database
     *
     * @return bool
     */
    public function run_external_migrations() {
        $db_config = $this->get_database_config();

        if ($db_config['type'] !== 'external' || empty($db_config['enabled'])) {
            return false;
        }

        try {
            $adapter = $this->create_external_adapter($db_config);
            
            if (!$adapter->test_connection()) {
                throw new \Exception('External database connection failed');
            }

            $migration_runner = new MigrationRunner();
            $result = $migration_runner->run();

            if ($result) {
                $this->logger->info('External database migrations completed successfully');
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('External database migration failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get health metrics
     *
     * @return array
     */
    public function get_health_metrics() {
        $adapter = $this->get_adapter();
        $status = $this->get_status();

        return [
            'adapter_type' => $status['type'],
            'fallback_active' => $this->is_fallback_active,
            'connected' => $status['connected'],
            'connection_details' => $status['details'],
            'health_status' => $status['connected'] ? 'healthy' : 'unhealthy',
            'last_check' => current_time('mysql'),
        ];
    }
}
