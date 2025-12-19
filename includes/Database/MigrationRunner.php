<?php
/**
 * Migration Runner class for Newera Plugin
 *
 * Handles database migrations and schema updates.
 */

namespace Newera\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration Runner class
 */
class MigrationRunner {
    /**
     * Migration table name
     *
     * @var string
     */
    private $migration_table;

    /**
     * Migration files directory
     *
     * @var string
     */
    private $migration_path;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->migration_table = $wpdb->prefix . 'newera_migrations';
        $this->migration_path = NEWERA_PLUGIN_PATH . 'database/migrations/';
    }

    /**
     * Run all pending migrations
     *
     * @return bool
     */
    public function run() {
        try {
            // Ensure migration table exists
            $this->create_migration_table();
            
            // Get applied migrations
            $applied_migrations = $this->get_applied_migrations();
            
            // Get migration files
            $migration_files = $this->get_migration_files();
            
            // Run pending migrations
            $pending_migrations = array_diff($migration_files, $applied_migrations);
            
            if (empty($pending_migrations)) {
                // Log that there are no pending migrations
                if (class_exists('\\Newera\\Core\\Logger')) {
                    $logger = new \Newera\Core\Logger();
                    $logger->info('No pending migrations to run');
                }
                return true;
            }
            
            foreach ($pending_migrations as $migration) {
                $this->run_migration($migration);
                if (class_exists('\\Newera\\Core\\Logger')) {
                    $logger = new \Newera\Core\Logger();
                    $logger->info('Migration executed: ' . $migration);
                }
            }
            
            return true;
            
        } catch (\Exception $e) {
            // Log error if logger is available
            if (class_exists('\\Newera\\Core\\Logger')) {
                $logger = new \Newera\Core\Logger();
                $logger->error('Migration failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            }
            
            return false;
        }
    }

    /**
     * Create the migrations tracking table
     *
     * @return bool
     */
    private function create_migration_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->migration_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            migration varchar(255) NOT NULL,
            batch int(11) NOT NULL,
            executed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY migration (migration)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        return true;
    }

    /**
     * Get list of applied migrations
     *
     * @return array
     */
    private function get_applied_migrations() {
        global $wpdb;
        
        $results = $wpdb->get_col("SELECT migration FROM {$this->migration_table}");
        
        return $results ? $results : [];
    }

    /**
     * Get list of migration files
     *
     * @return array
     */
    private function get_migration_files() {
        if (!file_exists($this->migration_path)) {
            return [];
        }
        
        $files = glob($this->migration_path . '*.php');
        
        if (!$files) {
            return [];
        }
        
        // Extract migration names from filenames
        $migrations = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $migration_name = pathinfo($filename, PATHINFO_FILENAME);
            $migrations[] = $migration_name;
        }
        
        sort($migrations);
        return $migrations;
    }

    /**
     * Run a specific migration
     *
     * @param string $migration_name
     * @return bool
     */
    private function run_migration($migration_name) {
        global $wpdb;
        
        $migration_file = $this->migration_path . $migration_name . '.php';
        
        if (!file_exists($migration_file)) {
            throw new \Exception("Migration file not found: {$migration_file}");
        }
        
        // Load migration file
        require_once $migration_file;
        
        // Create migration class name
        $class_name = 'Newera\\Database\\Migrations\\' . $this->get_migration_class_name($migration_name);
        
        if (!class_exists($class_name)) {
            throw new \Exception("Migration class not found: {$class_name}");
        }
        
        // Instantiate and run migration
        $migration = new $class_name();
        
        if (!method_exists($migration, 'up')) {
            throw new \Exception("Migration {$migration_name} does not have an 'up' method");
        }
        
        // Begin transaction if supported
        $wpdb->query('START TRANSACTION');
        
        try {
            // Run the migration
            $migration->up();
            
            // Record the migration (use INSERT IGNORE for idempotency)
            $batch = $this->get_next_batch_number();
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$this->migration_table} (migration, batch) VALUES (%s, %d)",
                $migration_name,
                $batch
            ));
            
            $wpdb->query('COMMIT');
            
            return true;
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Get the next batch number for migrations
     *
     * @return int
     */
    private function get_next_batch_number() {
        global $wpdb;
        
        $max_batch = $wpdb->get_var("SELECT MAX(batch) FROM {$this->migration_table}");
        
        return $max_batch ? $max_batch + 1 : 1;
    }

    /**
     * Convert migration filename to class name
     *
     * @param string $migration_name
     * @return string
     */
    private function get_migration_class_name($migration_name) {
        // Filenames are timestamp-prefixed (e.g. 20231214000200_create_projects_table).
        // Class names omit the timestamp (e.g. CreateProjectsTable).
        $migration_name = preg_replace('/^\d+_/', '', (string) $migration_name);

        // Convert snake_case to PascalCase
        $class_name = str_replace('_', '', ucwords($migration_name, '_'));
        return $class_name;
    }

    /**
     * Rollback migrations (placeholder)
     *
     * @param int $steps
     * @return bool
     */
    public function rollback($steps = 1) {
        // This is a placeholder for migration rollback functionality
        // Implementation would depend on specific requirements
        
        global $wpdb;
        
        $applied_migrations = $this->get_applied_migrations();
        
        if (empty($applied_migrations)) {
            return false;
        }
        
        // Get migrations to rollback (in reverse order)
        $migrations_to_rollback = array_reverse(array_slice($applied_migrations, -$steps));
        
        foreach ($migrations_to_rollback as $migration) {
            $this->run_rollback($migration);
        }
        
        return true;
    }

    /**
     * Run a migration rollback
     *
     * @param string $migration_name
     * @return bool
     */
    private function run_rollback($migration_name) {
        global $wpdb;
        
        $migration_file = $this->migration_path . $migration_name . '.php';
        
        if (!file_exists($migration_file)) {
            return false;
        }
        
        // Load migration file
        require_once $migration_file;
        
        // Create migration class name
        $class_name = 'Newera\\Database\\Migrations\\' . $this->get_migration_class_name($migration_name);
        
        if (!class_exists($class_name)) {
            return false;
        }
        
        $migration = new $class_name();
        
        if (!method_exists($migration, 'down')) {
            return false;
        }
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $migration->down();
            
            // Remove migration record
            $wpdb->delete(
                $this->migration_table,
                ['migration' => $migration_name],
                ['%s']
            );
            
            $wpdb->query('COMMIT');
            return true;
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }

    /**
     * Get migration status
     *
     * @return array
     */
    public function get_status() {
        $applied_migrations = $this->get_applied_migrations();
        $all_migrations = $this->get_migration_files();
        
        return [
            'total_migrations' => count($all_migrations),
            'applied_migrations' => count($applied_migrations),
            'pending_migrations' => count(array_diff($all_migrations, $applied_migrations)),
            'last_batch' => $this->get_last_batch_number()
        ];
    }

    /**
     * Get the last batch number
     *
     * @return int
     */
    private function get_last_batch_number() {
        global $wpdb;
        
        $max_batch = $wpdb->get_var("SELECT MAX(batch) FROM {$this->migration_table}");
        
        return $max_batch ? $max_batch : 0;
    }
}