<?php
/**
 * Module Registry for Newera Plugin
 *
 * Auto-discovers modules under /modules and boots them during plugin init.
 */

namespace Newera\Modules;

use Newera\Core\Logger;
use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ModuleRegistry
 */
class ModuleRegistry {
    /**
     * @var StateManager|null
     */
    private $state_manager;

    /**
     * @var Logger|null
     */
    private $logger;

    /**
     * @var ModuleInterface[]
     */
    private $modules = [];

    /**
     * @var string
     */
    private $modules_path;

    /**
     * @param StateManager|null $state_manager
     * @param Logger|null $logger
     * @param string|null $modules_path
     */
    public function __construct($state_manager = null, $logger = null, $modules_path = null) {
        $this->state_manager = $state_manager;
        $this->logger = $logger;

        if ($modules_path) {
            $this->modules_path = $modules_path;
        } elseif (defined('NEWERA_MODULES_PATH')) {
            $this->modules_path = NEWERA_MODULES_PATH;
        } else {
            $this->modules_path = trailingslashit(NEWERA_PLUGIN_PATH . 'modules');
        }
    }

    /**
     * Discover modules and populate internal registry.
     */
    public function init() {
        $this->discover_modules();
    }

    /**
     * @return ModuleInterface[]
     */
    public function get_modules() {
        return $this->modules;
    }

    /**
     * @param string $module_id
     * @return ModuleInterface|null
     */
    public function get_module($module_id) {
        return isset($this->modules[$module_id]) ? $this->modules[$module_id] : null;
    }

    /**
     * Boot all discovered modules.
     */
    public function boot() {
        foreach ($this->modules as $module_id => $module) {
            $enabled = $this->is_enabled_in_state($module_id);
            $configured = $module->isConfigured();

            $module->setActive($enabled && $configured);

            if ($this->logger) {
                $this->logger->info('Module registered', [
                    'id' => $module_id,
                    'class' => get_class($module),
                    'enabled' => $enabled,
                    'configured' => $configured,
                    'active' => $module->isActive(),
                ]);
            }

            $module->boot();
        }
    }

    /**
     * Discover module classes in /modules.
     */
    private function discover_modules() {
        if (!is_dir($this->modules_path)) {
            if ($this->logger) {
                $this->logger->info('Modules directory not found; skipping module discovery', [
                    'path' => $this->modules_path,
                ]);
            }
            return;
        }

        $files = $this->find_php_files($this->modules_path);

        foreach ($files as $file) {
            $class = $this->class_from_file($file);
            if (!$class) {
                continue;
            }

            require_once $file;

            if (!class_exists($class)) {
                if ($this->logger) {
                    $this->logger->warning('Module file loaded but class not found', [
                        'file' => $file,
                        'expected_class' => $class,
                    ]);
                }
                continue;
            }

            if (!is_subclass_of($class, ModuleInterface::class)) {
                continue;
            }

            try {
                $module = new $class($this->state_manager, $this->logger);

                $id = $module->getId();
                if (isset($this->modules[$id])) {
                    if ($this->logger) {
                        $this->logger->warning('Duplicate module id discovered; skipping', [
                            'id' => $id,
                            'class' => $class,
                        ]);
                    }
                    continue;
                }

                $this->modules[$id] = $module;
            } catch (\Throwable $e) {
                if ($this->logger) {
                    $this->logger->error('Failed to instantiate module', [
                        'class' => $class,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($this->logger) {
            $this->logger->info('Module discovery complete', [
                'count' => count($this->modules),
            ]);
        }
    }

    /**
     * @param string $path
     * @return array
     */
    private function find_php_files($path) {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $filename = $file->getFilename();
            if (substr($filename, -9) !== 'Module.php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /**
     * Convert a modules directory file path into its expected class name.
     *
     * modules/Auth/AuthModule.php => Newera\\Modules\\Auth\\AuthModule
     *
     * @param string $file
     * @return string|null
     */
    private function class_from_file($file) {
        $base = rtrim(str_replace('\\', '/', $this->modules_path), '/') . '/';
        $normalized_file = str_replace('\\', '/', $file);

        if (strpos($normalized_file, $base) !== 0) {
            return null;
        }

        $relative = substr($normalized_file, strlen($base));
        if (!$relative) {
            return null;
        }

        $relative = preg_replace('/\.php$/i', '', $relative);
        $relative = str_replace('/', '\\', $relative);

        return '\\Newera\\Modules\\' . $relative;
    }

    /**
     * @param string $module_id
     * @return bool
     */
    private function is_enabled_in_state($module_id) {
        if (!$this->state_manager) {
            return false;
        }

        $enabled = $this->state_manager->get_state_value('modules_enabled', []);

        if (!is_array($enabled)) {
            return false;
        }

        if (array_key_exists($module_id, $enabled)) {
            return (bool) $enabled[$module_id];
        }

        return in_array($module_id, $enabled, true);
    }
}
