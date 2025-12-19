<?php
namespace Newera\AI;

use Newera\Core\Logger;
use Newera\Core\StateManager;
use Newera\Modules\ModuleRegistry;

/**
 * AI Command Handler
 */
class CommandHandler {
    /**
     * @var StateManager
     */
    private $state_manager;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ModuleRegistry
     */
    private $module_registry;

    /**
     * Constructor
     *
     * @param StateManager $state_manager
     * @param Logger $logger
     * @param ModuleRegistry $module_registry
     */
    public function __construct(StateManager $state_manager, Logger $logger, ModuleRegistry $module_registry) {
        $this->state_manager = $state_manager;
        $this->logger = $logger;
        $this->module_registry = $module_registry;
    }

    /**
     * Initialize the command handler
     */
    public function init() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('newera/v1', '/ai-command', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_command'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    /**
     * Check permissions for the API endpoint
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function check_permission($request) {
        return current_user_can('manage_options');
    }

    /**
     * Handle the AI command
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_command($request) {
        $params = $request->get_json_params();
        $command = $params['command'] ?? '';
        $module_name = $params['module'] ?? '';
        $cmd_params = $params['params'] ?? [];

        $audit_log = [
            'timestamp' => current_time('mysql'),
            'user' => get_current_user_id(),
            'command' => $command,
            'module' => $module_name,
            'params' => $cmd_params,
        ];

        if (empty($command) || empty($module_name)) {
            return $this->error_response('Invalid command format. "command" and "module" are required.', $audit_log);
        }

        try {
            // Case-insensitive module lookup
            $module = $this->module_registry->get_module($module_name);
            if (!$module) {
                // Try lowercase
                $module = $this->module_registry->get_module(strtolower($module_name));
            }
            
            if (!$module) {
                throw new \Exception("Module '$module_name' not found");
            }

            // Capture state for rollback (Settings only)
            $modules_settings = $this->state_manager->get_setting('modules', []);
            $backup_module_settings = isset($modules_settings[$module->getId()]) ? $modules_settings[$module->getId()] : [];

            try {
                $result = $this->execute_module_command($module, $command, $cmd_params);
                
                $audit_log['status'] = 'success';
                $audit_log['result'] = $result;
                $this->log_audit($audit_log);

                return rest_ensure_response([
                    'status' => 'success',
                    'result' => $result,
                    'next_steps' => [], // TODO: Determine next steps
                ]);
            } catch (\Exception $e) {
                // Rollback settings
                $current_modules_settings = $this->state_manager->get_setting('modules', []);
                $current_modules_settings[$module->getId()] = $backup_module_settings;
                $this->state_manager->update_setting('modules', $current_modules_settings);
                throw $e;
            }

        } catch (\Exception $e) {
            $audit_log['status'] = 'error';
            $audit_log['error'] = $e->getMessage();
            $this->log_audit($audit_log);

            return $this->error_response($e->getMessage(), $audit_log, 500);
        }
    }

    /**
     * Execute command on module
     *
     * @param object $module
     * @param string $command
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    private function execute_module_command($module, $command, $params) {
        $method = $this->map_command_to_method($command);
        
        if (!method_exists($module, $method)) {
             throw new \Exception("Command '$command' (method: $method) not supported by module '" . $module->getId() . "'");
        }
        
        return $module->$method($params);
    }
    
    /**
     * Map command string to method name
     *
     * @param string $command
     * @return string
     */
    private function map_command_to_method($command) {
        switch ($command) {
            case 'Enable Google Sign-In': return 'enableGoogleSignIn';
            case 'Create a plan': return 'createPlan';
            case 'Switch database to Neon': return 'switchDatabaseToNeon';
            case 'Fetch Linear issues and create projects': return 'fetchLinearIssuesAndCreateProjects';
            case 'Generate usage report': return 'generateUsageReport';
            case 'Invite user to project': return 'inviteUserToProject';
            case 'Set rate limit': return 'setRateLimit';
            default:
                // CamelCase the command
                $str = str_replace(['-', '_'], ' ', $command);
                $str = ucwords($str);
                $str = str_replace(' ', '', $str);
                return lcfirst($str);
        }
    }

    /**
     * Log to audit trail
     *
     * @param array $log
     */
    private function log_audit($log) {
        $upload_dir = wp_upload_dir();
        $file = $upload_dir['basedir'] . '/newera-audit.log';
        if (!file_exists($upload_dir['basedir'])) {
            wp_mkdir_p($upload_dir['basedir']);
        }
        $entry = json_encode($log) . "\n";
        file_put_contents($file, $entry, FILE_APPEND);
        
        $this->logger->info("AI Command executed", $log);
    }

    /**
     * Return error response
     *
     * @param string $message
     * @param array $audit_log
     * @param int $status
     * @return \WP_REST_Response
     */
    private function error_response($message, $audit_log, $status = 400) {
        return new \WP_REST_Response([
            'status' => 'error',
            'error' => $message,
            'audit_log' => $audit_log
        ], $status);
    }
}
