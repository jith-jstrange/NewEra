# External Database Support

Newera plugin supports external PostgreSQL-compatible databases including Neon, Supabase, and standard PostgreSQL installations.

## Features

### Core Capabilities

- **Dual-Mode Operation**: Seamlessly switch between WordPress database and external PostgreSQL
- **Automatic Fallback**: Gracefully degrades to WordPress DB if external connection fails
- **Connection Pooling**: Persistent connections for improved performance
- **Schema Conversion**: Automatic MySQL to PostgreSQL syntax translation
- **Secure Storage**: Connection credentials encrypted with AES-256-CBC
- **Health Monitoring**: Real-time connection status and metrics
- **AJAX Testing**: Live connection validation in Setup Wizard

### Supported Databases

- **Neon**: Serverless PostgreSQL platform
- **Supabase**: Open-source Firebase alternative with PostgreSQL
- **PostgreSQL**: Standard PostgreSQL 10+ installations
- **Any PostgreSQL-compatible database**

## Setup

### Via Setup Wizard (Recommended)

1. Navigate to **Newera > Setup Wizard** in WordPress admin
2. Proceed to the **Database** step
3. Select **External Database (PostgreSQL/Neon/Supabase)**
4. Enter your connection string
5. Click **Test Connection** to verify
6. Save to automatically run migrations

### Connection String Format

```
postgresql://username:password@host:port/database?sslmode=require
```

#### Examples

**Neon:**
```
postgresql://user:pass@ep-cool-cloud-123456.us-east-2.aws.neon.tech/neondb?sslmode=require
```

**Supabase:**
```
postgresql://postgres:password@db.abcdefghijklmn.supabase.co:5432/postgres
```

**Standard PostgreSQL:**
```
postgresql://dbuser:dbpass@localhost:5432/myapp
```

**With SSL:**
```
postgresql://user:pass@db.example.com:5432/mydb?sslmode=require
```

## Configuration

### Option 1: Setup Wizard (GUI)

The easiest way to configure external database:

1. Go to Newera > Setup Wizard
2. Navigate to Database step
3. Choose "External Database"
4. Enter connection string
5. Test connection
6. Save configuration

### Option 2: Programmatic

```php
<?php
// Get database factory
$db_factory = apply_filters('newera_get_db_factory', null);

// Test connection
$result = $db_factory->test_connection(
    'postgresql://user:pass@host:5432/db'
);

if ($result['success']) {
    // Save configuration
    $db_factory->save_configuration([
        'db_type' => 'external',
        'connection_string' => 'postgresql://user:pass@host:5432/db',
        'table_prefix' => 'wp_',
        'persistent' => false
    ]);
    
    // Run migrations
    $db_factory->run_external_migrations();
}
```

## Usage

### Getting Database Adapter

```php
<?php
// Get the active database adapter
$db_factory = apply_filters('newera_get_db_factory', null);
$db = $db_factory->get_adapter();

// Check if fallback is active
if ($db_factory->is_fallback_active()) {
    error_log('Using WordPress database as fallback');
}
```

### Database Operations

The adapter implements the full DBAdapterInterface:

```php
<?php
// Query operations
$results = $db->get_results("SELECT * FROM {$prefix}users WHERE active = ?", [1]);
$row = $db->get_row("SELECT * FROM {$prefix}users WHERE id = ?", [123]);
$value = $db->get_var("SELECT COUNT(*) FROM {$prefix}users");

// Insert
$id = $db->insert("{$prefix}users", [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Update
$affected = $db->update("{$prefix}users", 
    ['status' => 'active'],
    ['id' => 123]
);

// Delete
$affected = $db->delete("{$prefix}users", ['id' => 123]);

// Transactions
$db->begin_transaction();
try {
    $db->insert(...);
    $db->update(...);
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
}
```

## Health Monitoring

### Dashboard View

Navigate to **Newera > Dashboard** to view:

- Adapter type (WordPress or External)
- Connection status
- Fallback status
- Database version
- Host and database name
- Last health check timestamp

### Programmatic Monitoring

```php
<?php
$db_factory = apply_filters('newera_get_db_factory', null);

// Get health metrics
$health = $db_factory->get_health_metrics();
/*
Array (
    [adapter_type] => external
    [fallback_active] => false
    [connected] => true
    [connection_details] => Array (
        [driver] => pgsql
        [host] => db.example.com
        [database] => mydb
        [version] => PostgreSQL 15.1
    )
    [health_status] => healthy
    [last_check] => 2024-01-15 10:30:45
)
*/

// Get status
$status = $db_factory->get_status();
```

## Migrations

Migrations automatically run when external database is first configured. They can also be run manually:

```php
<?php
$db_factory = apply_filters('newera_get_db_factory', null);
$result = $db_factory->run_external_migrations();

if ($result) {
    echo "Migrations completed successfully";
} else {
    echo "Migration failed - check logs";
}
```

### Schema Conversion

The adapter automatically converts MySQL syntax to PostgreSQL:

- `AUTO_INCREMENT` → `SERIAL`
- `` ` `` (backticks) → `"` (double quotes)
- `INT(11)` → `INTEGER`
- `TINYINT` → `SMALLINT`
- `MEDIUMTEXT` → `TEXT`
- `LONGTEXT` → `TEXT`

## Security

### Credential Storage

Connection strings are:
- Encrypted with AES-256-CBC
- Derived keys use WordPress salts via PBKDF2
- Stored in WordPress options with unique hash-based names
- Never exposed in logs or error messages

### Validation

All connection strings are validated before use:
- Scheme must be postgresql/postgres/pgsql
- Host, username, password required
- Database name required
- SSL mode optional but recommended

## Error Handling

### Automatic Fallback

If external database connection fails:

1. Error logged to newera.log
2. Automatic fallback to WordPress database
3. Health status updated to "degraded"
4. Dashboard shows fallback warning
5. Application continues without interruption

### Connection Retry

The adapter includes retry logic:
- Configurable max retries (default: 3)
- 1-second delay between retries
- Exponential backoff available

### Error Logging

All database errors are logged with context:

```
[ERROR] External database connection failed
  error: SQLSTATE[08006] Connection refused
  retry_count: 3
  host: db.example.com
```

## Troubleshooting

### Connection Fails

1. Check connection string format
2. Verify database credentials
3. Ensure host/port are accessible
4. Check SSL requirements
5. Verify pdo_pgsql extension installed

### Check PHP Extensions

```bash
php -m | grep pdo_pgsql
```

If not installed:
```bash
# Ubuntu/Debian
sudo apt-get install php-pgsql

# CentOS/RHEL
sudo yum install php-pgsql

# macOS (Homebrew)
brew install php@8.1-pgsql
```

### Test Connection

```php
<?php
// Enable WordPress debug mode
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Test connection
$db_factory = apply_filters('newera_get_db_factory', null);
$result = $db_factory->test_connection('postgresql://...');

print_r($result);
// Check wp-content/debug.log and wp-content/newera-logs/ for errors
```

### Fallback Active

If fallback is active:
1. Check Dashboard for connection status
2. Review newera.log for error details
3. Test connection string manually
4. Verify external database is running
5. Check firewall/security group rules

## Performance

### Connection Pooling

Enable persistent connections for better performance:

```php
$config = [
    'db_type' => 'external',
    'connection_string' => '...',
    'persistent' => true  // Enable persistent connections
];
```

**Benefits:**
- Reduced connection overhead
- Better performance under load
- Lower latency for queries

**Considerations:**
- Higher memory usage
- More server connections
- May hit connection limits

### Query Optimization

The external adapter supports all standard optimizations:

```php
// Use prepared statements
$stmt = $db->prepare("SELECT * FROM users WHERE status = ?", ['active']);

// Use transactions for bulk operations
$db->begin_transaction();
for ($i = 0; $i < 1000; $i++) {
    $db->insert(...);
}
$db->commit();

// Batch queries when possible
$db->query("INSERT INTO users (name) VALUES ('a'), ('b'), ('c')");
```

## Best Practices

1. **Always test connections** before saving configuration
2. **Use SSL/TLS** for production databases (`sslmode=require`)
3. **Monitor health metrics** regularly via dashboard
4. **Keep fallback available** - don't disable WordPress DB
5. **Use persistent connections** for high-traffic sites
6. **Run migrations** in maintenance mode for large schemas
7. **Log rotation** - monitor log file sizes
8. **Backup credentials** securely outside WordPress
9. **Test failover** scenarios before production
10. **Monitor connection pool** usage

## API Reference

### DBAdapterFactory

```php
// Get active adapter
$adapter = $db_factory->get_adapter();

// Test connection
$result = $db_factory->test_connection($connection_string);

// Save configuration
$db_factory->save_configuration($config);

// Run migrations
$db_factory->run_external_migrations();

// Check fallback status
$is_fallback = $db_factory->is_fallback_active();

// Get status
$status = $db_factory->get_status();

// Get health metrics
$health = $db_factory->get_health_metrics();
```

### ExternalDBAdapter

```php
// Validate connection string
$validation = ExternalDBAdapter::validate_connection_string($conn_str);
if ($validation['valid']) {
    // Connection string is valid
}

// Get connection
$conn = $adapter->get_connection();

// Test connection
$is_connected = $adapter->test_connection();

// Get status
$status = $adapter->get_connection_status();

// Get health metrics
$metrics = $adapter->get_health_metrics();

// Close connection
$adapter->close();
```

## Support

For issues or questions:

1. Check the Dashboard health status
2. Review logs in `wp-content/newera-logs/`
3. Enable WP_DEBUG and check debug.log
4. Test connection string separately
5. Verify database is accessible
6. Check PHP extensions (pdo_pgsql)

## Changelog

### Version 1.0.0

- Initial external database support
- PostgreSQL/Neon/Supabase compatibility
- Automatic fallback mechanism
- Connection pooling
- Health monitoring
- Setup Wizard integration
- AJAX connection testing
- Schema conversion
- Secure credential storage
