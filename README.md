# Newera WordPress Plugin

A modern WordPress plugin with comprehensive bootstrap and module architecture.

## Overview

Newera is a foundational WordPress plugin scaffold that provides a robust architecture for building complex plugins. It includes:

- **PSR-4 Autoloading** with Composer support
- **Module-based Architecture** for extensible functionality
- **Database Migration System** for schema management
- **Advanced Logging System** with multiple log levels
- **State Management** for persistent plugin data
- **Admin Dashboard** with health monitoring and module management
- **WP-Cron Integration** for scheduled tasks
- **Security Best Practices** with capability checks and nonce validation

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Installation

### 1. Download and Extract

Download the plugin files and extract them to your WordPress plugins directory:

```bash
wp-content/plugins/newera/
```

### 2. Install Dependencies (No External Credentials Required)

This plugin is designed to work without external credentials. Dependencies are optional and can be installed locally:

```bash
# Navigate to plugin directory
cd wp-content/plugins/newera/

# Install Composer dependencies (optional - plugin works without this)
composer install --no-dev --optimize-autoloader

# Or install just for development
composer install
```

**Note:** The plugin will work perfectly fine without Composer. All required classes are included in the `/includes` directory and follow PSR-4 autoloading standards.

### 3. WordPress Installation

1. Upload the plugin files to `/wp-content/plugins/newera/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'Newera' in your admin menu to access the dashboard

### 4. Verify Installation

After activation:

1. Check the Newera dashboard for system health
2. Verify that all core modules are listed
3. Confirm that the migration system is working
4. Test that admin assets are loading properly

## Development

### Project Structure

```
newera/
├── newera.php              # Main plugin file
├── README.md               # This file
├── LICENSE                 # GPL license
├── includes/               # Core plugin classes
│   ├── Core/              # Core functionality
│   │   ├── Bootstrap.php   # Main bootstrap class
│   │   ├── Logger.php      # Logging system
│   │   ├── StateManager.php # State management
│   │   └── ModulesRegistry.php # Module registry
│   ├── Database/          # Database functionality
│   │   └── MigrationRunner.php # Migration system
│   ├── Admin/             # Admin interface
│   │   ├── AdminMenu.php  # Admin menu registration
│   │   └── Dashboard.php  # Dashboard logic
│   ├── Modules/           # Plugin modules
│   │   ├── DashboardModule.php
│   │   ├── SettingsModule.php
│   │   ├── ContentModule.php
│   │   └── ApiModule.php
│   └── Assets/            # Admin assets
│       ├── css/admin.css  # Admin styles
│       └── js/admin.js    # Admin JavaScript
├── templates/             # Admin templates
│   └── admin/            # Admin page templates
│       ├── dashboard.php
│       ├── settings.php
│       ├── modules.php
│       └── logs.php
└── database/              # Database migrations
    └── migrations/       # Migration files
```

### Key Classes

#### Bootstrap (`Newera\Core\Bootstrap`)
The main plugin initializer that coordinates all components:
- Initializes logging, state management, and module registry
- Registers admin menus and handles plugin activation/deactivation
- Provides access to all core services

#### StateManager (`Newera\Core\StateManager`)
Manages plugin state and configuration:
- Stores plugin settings and state information
- Handles version migrations and compatibility checks
- Provides health monitoring capabilities

#### ModulesRegistry (`Newera\Core\ModulesRegistry`)
Manages the plugin's modular architecture:
- Registers and enables/disables modules
- Handles module dependencies and capabilities
- Provides module status and access control

#### Logger (`Newera\Core\Logger`)
Comprehensive logging system:
- Multiple log levels (debug, info, warning, error)
- Automatic log rotation and cleanup
- Contextual logging with additional data

#### MigrationRunner (`Newera\Database\MigrationRunner`)
Database migration system:
- Tracks and runs database schema updates
- Supports rollback functionality
- Batch-based migration tracking

### Adding New Modules

1. Create a new class in `/includes/Modules/YourModule.php`
2. Extend the base module functionality as needed
3. Register the module in `ModulesRegistry::init_built_in_modules()`
4. Add proper capability checks and permissions

Example module structure:
```php
<?php
namespace Newera\Modules;

if (!defined('ABSPATH')) {
    exit;
}

class YourModule {
    public function init() {
        // Module initialization logic
        add_action('newera_yourmodule_init', [$this, 'your_init_method']);
    }
    
    public function your_init_method() {
        // Module-specific functionality
    }
}
```

### Creating Migrations

1. Create migration files in `/database/migrations/` directory
2. Use the naming convention: `YYYY_MM_DD_HHMMSS_description.php`
3. Follow this structure:

```php
<?php
namespace Newera\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

class YourMigration {
    public function up() {
        // Migration logic - create tables, add columns, etc.
        global $wpdb;
        
        // Example: create a custom table
        $table_name = $wpdb->prefix . 'newera_your_table';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        )";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function down() {
        // Rollback logic
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'newera_your_table';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
```

### Admin Assets

Admin styles and scripts are located in `/includes/Assets/`:
- `css/admin.css` - Admin page styles
- `js/admin.js` - Admin page JavaScript

Assets are automatically enqueued on Newera admin pages.

### Database

The plugin creates these database tables:
- `wp_newera_migrations` - Tracks database migrations
- Logs are stored in `wp-content/newera-logs/`

### Logging

Logs are automatically created in `wp-content/newera-logs/newera.log` when `WP_DEBUG_LOG` is enabled. The logging system supports:

- Different log levels (debug, info, warning, error)
- Contextual data in JSON format
- Automatic log rotation based on file size
- WordPress admin interface for viewing logs

### WP-Cron Integration

The plugin sets up scheduled events:
- `newera_daily_cleanup` - Daily cleanup tasks
- Events are automatically cleared on deactivation

## Security

The plugin implements WordPress security best practices:

- **Nonce Verification** for all admin actions
- **Capability Checks** for sensitive operations
- **SQL Injection Prevention** with prepared statements
- **XSS Protection** with proper data sanitization
- **Direct Access Prevention** with `ABSPATH` checks

## Configuration

### Basic Settings

Access the settings page via `Newera > Settings` in your WordPress admin:

- **Enable Logging** - Toggle plugin logging
- **Log Level** - Set minimum log level (debug, info, warning, error)
- **Auto Cleanup Logs** - Automatically clean old log files
- **Max Log Size** - Maximum log file size before rotation
- **Debug Mode** - Enable additional debugging (development only)

### Environment Variables

The plugin respects these WordPress constants:
- `WP_DEBUG` - Enable debug mode
- `WP_DEBUG_LOG` - Enable logging
- `WP_DEBUG_DISPLAY` - Display debug information

## Troubleshooting

### Common Issues

**Plugin doesn't activate:**
- Check WordPress version (requires 5.0+)
- Verify PHP version (requires 7.4+)
- Ensure proper file permissions
- Check PHP error logs

**Admin pages don't load:**
- Verify plugin files are complete
- Check for JavaScript errors in browser console
- Ensure admin assets are being enqueued
- Clear any caching plugins

**Logs not appearing:**
- Verify `WP_DEBUG_LOG` is enabled in wp-config.php
- Check file permissions for `wp-content/newera-logs/`
- Ensure log directory is writable

**Migration errors:**
- Check database user permissions
- Verify MySQL version compatibility
- Review migration files for syntax errors
- Check WordPress debug logs

### Debug Mode

Enable WordPress debug mode for detailed error information:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Log Files

Check these locations for debugging information:
- WordPress: `wp-content/debug.log`
- Plugin: `wp-content/newera-logs/newera.log`
- PHP Error Log (server-specific)

## Contributing

When contributing to the plugin:

1. Follow PSR-4 autoloading standards
2. Use WordPress coding standards
3. Add proper documentation for new features
4. Include unit tests for new functionality
5. Test with different WordPress and PHP versions

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support and bug reports, please use the plugin's issue tracker or contact the development team.

---

**Note:** This is a foundational plugin scaffold. Actual functionality should be added through custom modules that extend this architecture. The included modules are placeholders to demonstrate the system capabilities.