<?php
/**
 * Logger class for Newera Plugin
 *
 * Provides logging functionality for plugin operations.
 */

namespace Newera\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger class
 */
class Logger {
    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;

    /**
     * Constructor
     */
    public function __construct() {
        $this->log_file = WP_CONTENT_DIR . '/newera-logs/newera.log';
        
        // Create logs directory if it doesn't exist
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message
     * @param array $context
     */
    public function debug($message, $context = []) {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message
     * @param array $context
     */
    public function info($message, $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message
     * @param array $context
     */
    public function warning($message, $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message
     * @param array $context
     */
    public function error($message, $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Generic log method
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log($level, $message, $context = []) {
        if (!WP_DEBUG_LOG) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]['class']) ? $backtrace[1]['class'] . '::' . $backtrace[1]['function'] : 'N/A';

        $log_entry = sprintf(
            "[%s] [%s] [%s] %s %s%s",
            $timestamp,
            strtoupper($level),
            $caller,
            $message,
            empty($context) ? '' : ' - ' . wp_json_encode($context),
            PHP_EOL
        );

        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get log file path
     *
     * @return string
     */
    public function get_log_file() {
        return $this->log_file;
    }
}