<?php
/**
 * External Database Adapter for Newera Plugin
 *
 * Supports PostgreSQL-compatible databases including Neon, Supabase, and standard PostgreSQL.
 * Provides connection pooling, error handling, and health monitoring.
 */

namespace Newera\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * External Database Adapter
 */
class ExternalDBAdapter implements DBAdapterInterface {
    /**
     * Connection configuration
     *
     * @var array
     */
    private $config = [];

    /**
     * Database connection (PDO)
     *
     * @var \PDO|null
     */
    private $connection = null;

    /**
     * Last insert ID
     *
     * @var int
     */
    private $insert_id = 0;

    /**
     * Rows affected by last query
     *
     * @var int
     */
    private $rows_affected = 0;

    /**
     * Connection retry count
     *
     * @var int
     */
    private $retry_count = 0;

    /**
     * Max connection retries
     *
     * @var int
     */
    private $max_retries = 3;

    /**
     * Logger instance
     *
     * @var \Newera\Core\Logger|null
     */
    private $logger = null;

    /**
     * Constructor
     *
     * @param array $config Configuration array
     */
    public function __construct($config = []) {
        $this->config = $config;
        if (class_exists('\\Newera\\Core\\Logger')) {
            $this->logger = new \Newera\Core\Logger();
        }
    }

    /**
     * Set connection configuration
     *
     * @param array $config
     */
    public function set_config($config) {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get connection configuration
     *
     * @return array
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Set the database connection
     *
     * @param \PDO $connection
     */
    public function set_connection($connection) {
        $this->connection = $connection;
    }

    /**
     * Get the database connection
     *
     * @return \PDO|null
     */
    public function get_connection() {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Establish database connection
     *
     * @return bool
     */
    private function connect() {
        if ($this->connection !== null) {
            return true;
        }

        try {
            $dsn = $this->build_dsn();
            $username = $this->config['username'] ?? '';
            $password = $this->config['password'] ?? '';
            $options = $this->get_pdo_options();

            $this->connection = new \PDO($dsn, $username, $password, $options);
            $this->retry_count = 0;

            if ($this->logger) {
                $this->logger->info('External database connection established', [
                    'driver' => $this->config['driver'] ?? 'pgsql',
                    'host' => $this->config['host'] ?? 'unknown'
                ]);
            }

            return true;

        } catch (\PDOException $e) {
            if ($this->logger) {
                $this->logger->error('External database connection failed', [
                    'error' => $e->getMessage(),
                    'retry_count' => $this->retry_count
                ]);
            }

            $this->retry_count++;
            if ($this->retry_count < $this->max_retries) {
                sleep(1);
                return $this->connect();
            }

            return false;
        }
    }

    /**
     * Build DSN string from configuration
     *
     * @return string
     */
    private function build_dsn() {
        if (!empty($this->config['dsn'])) {
            return $this->config['dsn'];
        }

        if (!empty($this->config['connection_string'])) {
            return $this->parse_connection_string($this->config['connection_string']);
        }

        $driver = $this->config['driver'] ?? 'pgsql';
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 5432;
        $database = $this->config['database'] ?? '';

        $dsn = "{$driver}:host={$host};port={$port}";
        
        if (!empty($database)) {
            $dsn .= ";dbname={$database}";
        }

        if (!empty($this->config['sslmode'])) {
            $dsn .= ";sslmode={$this->config['sslmode']}";
        }

        return $dsn;
    }

    /**
     * Parse connection string into DSN
     *
     * @param string $connection_string
     * @return string
     */
    private function parse_connection_string($connection_string) {
        $parts = parse_url($connection_string);
        
        if ($parts === false) {
            throw new \Exception('Invalid connection string format');
        }

        $driver = isset($parts['scheme']) ? str_replace('postgresql', 'pgsql', $parts['scheme']) : 'pgsql';
        $host = $parts['host'] ?? 'localhost';
        $port = $parts['port'] ?? 5432;
        $database = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

        if (isset($parts['user'])) {
            $this->config['username'] = $parts['user'];
        }
        if (isset($parts['pass'])) {
            $this->config['password'] = $parts['pass'];
        }

        $dsn = "{$driver}:host={$host};port={$port}";
        
        if (!empty($database)) {
            $dsn .= ";dbname={$database}";
        }

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query_params);
            if (isset($query_params['sslmode'])) {
                $dsn .= ";sslmode={$query_params['sslmode']}";
            }
        }

        return $dsn;
    }

    /**
     * Get PDO connection options
     *
     * @return array
     */
    private function get_pdo_options() {
        return [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_PERSISTENT => !empty($this->config['persistent']),
        ];
    }

    /**
     * Execute a query
     *
     * @param string $query
     * @param array $args
     * @return mixed
     */
    public function query($query, $args = []) {
        if ($this->get_connection() === null) {
            return false;
        }

        try {
            $query = $this->convert_mysql_to_postgres($query);
            
            if (!empty($args)) {
                $stmt = $this->connection->prepare($query);
                $stmt->execute($args);
            } else {
                $stmt = $this->connection->query($query);
            }

            $this->rows_affected = $stmt->rowCount();
            return $this->rows_affected;

        } catch (\PDOException $e) {
            if ($this->logger) {
                $this->logger->error('Query execution failed', [
                    'error' => $e->getMessage(),
                    'query' => $query
                ]);
            }
            return false;
        }
    }

    /**
     * Get results from a query
     *
     * @param string $query
     * @param array $args
     * @param string $output
     * @return mixed
     */
    public function get_results($query, $args = [], $output = 'OBJECT') {
        if ($this->get_connection() === null) {
            return [];
        }

        try {
            $query = $this->convert_mysql_to_postgres($query);
            
            if (!empty($args)) {
                $stmt = $this->connection->prepare($query);
                $stmt->execute($args);
            } else {
                $stmt = $this->connection->query($query);
            }

            $fetch_mode = $output === 'ARRAY_A' ? \PDO::FETCH_ASSOC : 
                         ($output === 'ARRAY_N' ? \PDO::FETCH_NUM : \PDO::FETCH_OBJ);
            
            $results = $stmt->fetchAll($fetch_mode);
            return $results;

        } catch (\PDOException $e) {
            if ($this->logger) {
                $this->logger->error('Query execution failed', [
                    'error' => $e->getMessage(),
                    'query' => $query
                ]);
            }
            return [];
        }
    }

    /**
     * Get a single row
     *
     * @param string $query
     * @param array $args
     * @param string $output
     * @return mixed
     */
    public function get_row($query, $args = [], $output = 'OBJECT') {
        if ($this->get_connection() === null) {
            return null;
        }

        try {
            $query = $this->convert_mysql_to_postgres($query);
            
            if (!empty($args)) {
                $stmt = $this->connection->prepare($query);
                $stmt->execute($args);
            } else {
                $stmt = $this->connection->query($query);
            }

            $fetch_mode = $output === 'ARRAY_A' ? \PDO::FETCH_ASSOC : 
                         ($output === 'ARRAY_N' ? \PDO::FETCH_NUM : \PDO::FETCH_OBJ);
            
            $row = $stmt->fetch($fetch_mode);
            return $row !== false ? $row : null;

        } catch (\PDOException $e) {
            if ($this->logger) {
                $this->logger->error('Query execution failed', [
                    'error' => $e->getMessage(),
                    'query' => $query
                ]);
            }
            return null;
        }
    }

    /**
     * Get a single column value
     *
     * @param string $query
     * @param array $args
     * @param int $x
     * @return mixed
     */
    public function get_var($query, $args = [], $x = 0) {
        if ($this->get_connection() === null) {
            return null;
        }

        try {
            $query = $this->convert_mysql_to_postgres($query);
            
            if (!empty($args)) {
                $stmt = $this->connection->prepare($query);
                $stmt->execute($args);
            } else {
                $stmt = $this->connection->query($query);
            }

            $value = $stmt->fetchColumn($x);
            return $value !== false ? $value : null;

        } catch (\PDOException $e) {
            if ($this->logger) {
                $this->logger->error('Query execution failed', [
                    'error' => $e->getMessage(),
                    'query' => $query
                ]);
            }
            return null;
        }
    }

    /**
     * Get a single column as array
     *
     * @param string $query
     * @param array $args
     * @param int $x
     * @return array
     */
    public function get_col($query, $args = [], $x = 0) {
        if ($this->get_connection() === null) {
            return [];
        }

        try {
            $query = $this->convert_mysql_to_postgres($query);
            
            if (!empty($args)) {
                $stmt = $this->connection->prepare($query);
                $stmt->execute($args);
            } else {
                $stmt = $this->connection->query($query);
            }

            $results = [];
            while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                if (isset($row[$x])) {
                    $results[] = $row[$x];
                }
            }

            return $results;

        } catch (\PDOException $e) {
            if ($this->logger) {
                $this->logger->error('Query execution failed', [
                    'error' => $e->getMessage(),
                    'query' => $query
                ]);
            }
            return [];
        }
    }

    /**
     * Insert a row
     *
     * @param string $table
     * @param array $data
     * @param array $format
     * @return int|false
     */
    public function insert($table, $data, $format = []) {
        if ($this->get_connection() === null) {
            return false;
        }

        try {
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');
            
            $query = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', $columns),
                implode(', ', $placeholders)
            );

            $stmt = $this->connection->prepare($query);
            $stmt->execute(array_values($data));

            $this->insert_id = (int) $this->connection->lastInsertId();
            $this->rows_affected = $stmt->rowCount();

            return $this->insert_id;

        } catch (\PDOException $e) {
            if ($this->logger) {
                $this->logger->error('Insert failed', [
                    'error' => $e->getMessage(),
                    'table' => $table
                ]);
            }
            return false;
        }
    }

    /**
     * Update rows
     *
     * @param string $table
     * @param array $data
     * @param array $where
     * @param array $format
     * @param array $where_format
     * @return int|false
     */
    public function update($table, $data, $where, $format = [], $where_format = []) {
        if ($this->get_connection() === null) {
            return false;
        }

        try {
            $set_clauses = [];
            foreach (array_keys($data) as $column) {
                $set_clauses[] = "$column = ?";
            }

            $where_clauses = [];
            foreach (array_keys($where) as $column) {
                $where_clauses[] = "$column = ?";
            }

            $query = sprintf(
                'UPDATE %s SET %s WHERE %s',
                $table,
                implode(', ', $set_clauses),
                implode(' AND ', $where_clauses)
            );

            $values = array_merge(array_values($data), array_values($where));
            $stmt = $this->connection->prepare($query);
            $stmt->execute($values);

            $this->rows_affected = $stmt->rowCount();
            return $this->rows_affected;

        } catch (\PDOException $e) {
            if ($this->logger) {
                $this->logger->error('Update failed', [
                    'error' => $e->getMessage(),
                    'table' => $table
                ]);
            }
            return false;
        }
    }

    /**
     * Delete rows
     *
     * @param string $table
     * @param array $where
     * @param array $where_format
     * @return int|false
     */
    public function delete($table, $where, $where_format = []) {
        if ($this->get_connection() === null) {
            return false;
        }

        try {
            $where_clauses = [];
            foreach (array_keys($where) as $column) {
                $where_clauses[] = "$column = ?";
            }

            $query = sprintf(
                'DELETE FROM %s WHERE %s',
                $table,
                implode(' AND ', $where_clauses)
            );

            $stmt = $this->connection->prepare($query);
            $stmt->execute(array_values($where));

            $this->rows_affected = $stmt->rowCount();
            return $this->rows_affected;

        } catch (\PDOException $e) {
            if ($this->logger) {
                $this->logger->error('Delete failed', [
                    'error' => $e->getMessage(),
                    'table' => $table
                ]);
            }
            return false;
        }
    }

    /**
     * Get the last insert ID
     *
     * @return int
     */
    public function get_insert_id() {
        return $this->insert_id;
    }

    /**
     * Get the number of rows affected
     *
     * @return int
     */
    public function get_rows_affected() {
        return $this->rows_affected;
    }

    /**
     * Get the prefix for table names
     *
     * @return string
     */
    public function get_table_prefix() {
        return isset($this->config['table_prefix']) ? $this->config['table_prefix'] : 'wp_';
    }

    /**
     * Prepare a query
     *
     * @param string $query
     * @param array $args
     * @return string
     */
    public function prepare($query, $args = []) {
        if (empty($args)) {
            return $query;
        }

        $query = str_replace('?', '%s', $query);
        
        foreach ($args as $i => $arg) {
            if (is_int($arg)) {
                $args[$i] = $arg;
            } elseif (is_float($arg)) {
                $args[$i] = $arg;
            } else {
                $args[$i] = "'" . addslashes($arg) . "'";
            }
        }

        return vsprintf($query, $args);
    }

    /**
     * Get charset collation
     *
     * @return string
     */
    public function get_charset_collate() {
        return '';
    }

    /**
     * Begin a transaction
     *
     * @return bool
     */
    public function begin_transaction() {
        if ($this->get_connection() === null) {
            return false;
        }

        try {
            return $this->connection->beginTransaction();
        } catch (\PDOException $e) {
            if ($this->logger) {
                $this->logger->error('Transaction begin failed', ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    /**
     * Commit a transaction
     *
     * @return bool
     */
    public function commit() {
        if ($this->get_connection() === null) {
            return false;
        }

        try {
            return $this->connection->commit();
        } catch (\PDOException $e) {
            if ($this->logger) {
                $this->logger->error('Transaction commit failed', ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    /**
     * Rollback a transaction
     *
     * @return bool
     */
    public function rollback() {
        if ($this->get_connection() === null) {
            return false;
        }

        try {
            return $this->connection->rollBack();
        } catch (\PDOException $e) {
            if ($this->logger) {
                $this->logger->error('Transaction rollback failed', ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    /**
     * Test the connection
     *
     * @return bool
     */
    public function test_connection() {
        try {
            if ($this->get_connection() === null) {
                return false;
            }

            $stmt = $this->connection->query('SELECT 1');
            return $stmt !== false;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Connection test failed', ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    /**
     * Get connection status
     *
     * @return array
     */
    public function get_connection_status() {
        $connected = $this->test_connection();
        
        $status = [
            'connected' => $connected,
            'driver' => $this->config['driver'] ?? 'pgsql',
            'host' => $this->config['host'] ?? 'unknown',
            'database' => $this->config['database'] ?? 'unknown',
            'port' => $this->config['port'] ?? 5432,
            'retry_count' => $this->retry_count,
        ];

        if ($connected && $this->connection) {
            try {
                $version = $this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION);
                $status['version'] = $version;
            } catch (\PDOException $e) {
                $status['version'] = 'unknown';
            }
        }

        return $status;
    }

    /**
     * Convert MySQL syntax to PostgreSQL
     *
     * @param string $query
     * @return string
     */
    private function convert_mysql_to_postgres($query) {
        $query = str_ireplace('AUTO_INCREMENT', 'SERIAL', $query);
        $query = str_ireplace('`', '"', $query);
        $query = preg_replace('/DEFAULT CURRENT_TIMESTAMP/i', 'DEFAULT CURRENT_TIMESTAMP', $query);
        $query = preg_replace('/INT\(\d+\)/i', 'INTEGER', $query);
        $query = str_ireplace('TINYINT', 'SMALLINT', $query);
        $query = str_ireplace('MEDIUMTEXT', 'TEXT', $query);
        $query = str_ireplace('LONGTEXT', 'TEXT', $query);
        
        return $query;
    }

    /**
     * Validate connection string
     *
     * @param string $connection_string
     * @return array Array with 'valid' boolean and 'error' message
     */
    public static function validate_connection_string($connection_string) {
        if (empty($connection_string)) {
            return ['valid' => false, 'error' => 'Connection string is empty'];
        }

        $parts = parse_url($connection_string);
        
        if ($parts === false) {
            return ['valid' => false, 'error' => 'Invalid connection string format'];
        }

        if (!isset($parts['scheme']) || !in_array($parts['scheme'], ['postgresql', 'postgres', 'pgsql'])) {
            return ['valid' => false, 'error' => 'Invalid scheme. Use postgresql://, postgres://, or pgsql://'];
        }

        if (!isset($parts['host'])) {
            return ['valid' => false, 'error' => 'Host is required'];
        }

        if (!isset($parts['user'])) {
            return ['valid' => false, 'error' => 'Username is required'];
        }

        if (!isset($parts['pass'])) {
            return ['valid' => false, 'error' => 'Password is required'];
        }

        if (!isset($parts['path']) || empty(ltrim($parts['path'], '/'))) {
            return ['valid' => false, 'error' => 'Database name is required'];
        }

        return ['valid' => true, 'error' => ''];
    }

    /**
     * Close connection
     */
    public function close() {
        $this->connection = null;
    }

    /**
     * Get health metrics
     *
     * @return array
     */
    public function get_health_metrics() {
        $status = $this->get_connection_status();
        
        return [
            'connected' => $status['connected'],
            'driver' => $status['driver'] ?? 'unknown',
            'host' => $status['host'] ?? 'unknown',
            'database' => $status['database'] ?? 'unknown',
            'version' => $status['version'] ?? 'unknown',
            'retry_count' => $this->retry_count,
            'last_check' => current_time('mysql'),
        ];
    }
}
