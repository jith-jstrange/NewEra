# Security Best Practices - Newera Plugin

## Overview

The Newera WordPress plugin implements comprehensive security measures to protect against common vulnerabilities and ensure data safety.

## Security Features

### 1. Security Headers

Automatically applied to all requests:

- **X-Frame-Options**: SAMEORIGIN (prevents clickjacking)
- **X-XSS-Protection**: Enabled with mode=block
- **X-Content-Type-Options**: nosniff (prevents MIME type sniffing)
- **Referrer-Policy**: strict-origin-when-cross-origin
- **Permissions-Policy**: Restricts browser features
- **HSTS**: Strict-Transport-Security (when using HTTPS)
- **Content-Security-Policy**: Optional, can be enabled via filter

### 2. Rate Limiting

API endpoints are protected with configurable rate limits:

```php
// Default: 60 requests per minute
// Webhook endpoint: 100 requests per minute
// Health check: 120 requests per minute

// Custom rate limits via filter
add_filter('newera_rate_limits', function($limits) {
    $limits['/newera/v1/custom'] = [
        'limit' => 30,
        'window' => 60,
    ];
    return $limits;
});
```

### 3. CORS Configuration

Cross-Origin Resource Sharing is configured for API security:

- Allowed origins include site URL and home URL
- Development mode allows localhost origins
- Custom origins can be added via filter

```php
// Add custom allowed origins
add_filter('newera_cors_allowed_origins', function($origins) {
    $origins[] = 'https://yourdomain.com';
    return $origins;
});
```

### 4. Credential Encryption

All sensitive credentials are encrypted using AES-256-CBC:

- Stripe API keys
- Database connection strings
- Third-party API tokens
- OAuth credentials

**Key Management:**
- Uses WordPress salts for key derivation
- PBKDF2 with 10,000 iterations
- Unique IV for each encryption
- Timing-safe comparison for validation

### 5. WordPress Security Best Practices

- **Nonce Verification**: All forms and AJAX requests
- **Capability Checks**: User permission validation
- **Prepared Statements**: SQL injection prevention
- **Output Escaping**: XSS protection
- **Input Sanitization**: Data validation
- **Direct Access Prevention**: ABSPATH checks

## Production Security Checklist

Before deploying to production:

### Environment Configuration

- [ ] Set `WORDPRESS_DEBUG=false`
- [ ] Set `WORDPRESS_DEBUG_DISPLAY=false`
- [ ] Set `WORDPRESS_DEBUG_LOG=true`
- [ ] Generate unique WordPress salts
- [ ] Use strong database passwords
- [ ] Configure proper file permissions (755 for directories, 644 for files)

### WordPress Configuration

```php
// Recommended wp-config.php settings
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);
define('FORCE_SSL_ADMIN', true);
define('WP_AUTO_UPDATE_CORE', 'minor');
```

### Server Configuration

- [ ] Enable HTTPS/SSL
- [ ] Configure firewall rules
- [ ] Disable directory listing
- [ ] Set security headers at server level
- [ ] Configure fail2ban for brute force protection
- [ ] Regular security updates

### Database Security

- [ ] Use unique database prefix (not `wp_`)
- [ ] Restrict database user privileges
- [ ] Use separate database user for read-only operations
- [ ] Enable database connection encryption
- [ ] Regular database backups

### File Permissions

```bash
# Recommended permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 600 wp-config.php
chmod 600 .env
```

### API Security

- [ ] Implement authentication for sensitive endpoints
- [ ] Use HTTPS for all API requests
- [ ] Validate webhook signatures
- [ ] Log all API access
- [ ] Monitor for unusual activity

## Security Headers Configuration

### Nginx

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

### Apache (.htaccess)

```apache
<IfModule mod_headers.c>
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set X-Content-Type-Options "nosniff"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    Header set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>
```

## Monitoring and Logging

### Access Logging

All API requests are logged with:
- Timestamp
- IP address
- User agent
- Request path
- Response status
- Execution time

### Security Event Logging

Critical security events are logged:
- Failed authentication attempts
- Rate limit violations
- Suspicious activity patterns
- Configuration changes
- Database errors

### Log Analysis

```bash
# View recent security events
tail -f wp-content/newera-logs/newera.log | grep -i "security"

# Count failed authentication attempts
grep "authentication failed" wp-content/newera-logs/newera.log | wc -l

# Find suspicious IP addresses
grep "rate_limit_exceeded" wp-content/newera-logs/newera.log | cut -d' ' -f5 | sort | uniq -c | sort -nr
```

## Vulnerability Disclosure

If you discover a security vulnerability:

1. **DO NOT** create a public GitHub issue
2. Email security details to: security@yourproject.com
3. Include:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if available)

We will respond within 48 hours and work with you to resolve the issue.

## Security Updates

### Keeping Up-to-Date

```bash
# Update WordPress core
wp core update

# Update all plugins
wp plugin update --all

# Update Newera plugin
cd wp-content/plugins/newera
git pull origin main
composer install --no-dev --optimize-autoloader
```

### Automated Updates

Enable automated dependency updates:
- GitHub Dependabot is configured
- Weekly checks for security updates
- Automatic PRs for vulnerable dependencies

## Common Security Issues

### 1. XSS (Cross-Site Scripting)

**Prevention:**
```php
// Always escape output
echo esc_html($user_input);
echo esc_attr($attribute);
echo esc_url($url);
```

### 2. SQL Injection

**Prevention:**
```php
// Always use prepared statements
$wpdb->prepare("SELECT * FROM table WHERE id = %d", $id);
```

### 3. CSRF (Cross-Site Request Forgery)

**Prevention:**
```php
// Verify nonce on all forms
wp_verify_nonce($_POST['_wpnonce'], 'action_name');
```

### 4. File Upload Vulnerabilities

**Prevention:**
```php
// Validate file types
$allowed_types = ['jpg', 'png', 'pdf'];
$file_ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
if (!in_array($file_ext, $allowed_types)) {
    wp_die('Invalid file type');
}
```

## Security Auditing

### Manual Audit

```bash
# Check for hardcoded secrets
grep -r "api_key\|password\|secret" --include="*.php" .

# Find deprecated functions
grep -r "mysql_\|eval(" --include="*.php" .

# Check file permissions
find . -type f -perm 777
```

### Automated Scanning

```bash
# Run security scanner
npm run security-scan

# Check PHP vulnerabilities
composer audit

# WordPress security check
wp plugin verify-checksums --all
```

## Compliance

### GDPR Compliance

- User data encryption at rest
- Secure data transmission
- Data retention policies
- Right to be forgotten support

### PCI DSS (for payment processing)

- Encrypted payment data
- Secure API communication
- Regular security audits
- Access control and logging

## Resources

- [WordPress Security Codex](https://wordpress.org/support/article/hardening-wordpress/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [WordPress Plugin Security](https://developer.wordpress.org/plugins/security/)

## Support

For security-related questions:
- Documentation: See DEPLOYMENT.md
- Issues: GitHub Issues (non-security)
- Security: security@yourproject.com

---

Last Updated: December 2024
