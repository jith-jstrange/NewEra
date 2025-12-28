# Production Deployment Checklist

Use this checklist before deploying Newera to production.

## Pre-Deployment

### Environment Setup
- [ ] `.env` file created from `.env.example`
- [ ] All environment variables configured
- [ ] WordPress salts generated (https://api.wordpress.org/secret-key/1.1/salt/)
- [ ] Strong passwords set for all accounts
- [ ] Database credentials secured

### Security Configuration
- [ ] `WORDPRESS_DEBUG` set to `false`
- [ ] `WORDPRESS_DEBUG_DISPLAY` set to `false`
- [ ] `WORDPRESS_DEBUG_LOG` set to `true`
- [ ] HTTPS/SSL certificate installed
- [ ] Security headers configured
- [ ] Firewall rules configured
- [ ] Rate limiting enabled

### Application Configuration
- [ ] Composer dependencies installed (`composer install --no-dev --optimize-autoloader`)
- [ ] NPM dependencies installed (`npm install`)
- [ ] Production assets built (`npm run build`)
- [ ] Database migrations run
- [ ] File permissions set correctly (755/644)
- [ ] `.gitignore` configured properly

### WordPress Configuration
- [ ] File editing disabled (`DISALLOW_FILE_EDIT`)
- [ ] Auto-updates configured
- [ ] Spam protection enabled
- [ ] Comment moderation configured
- [ ] Permalink structure set
- [ ] Timezone configured

### Third-Party Services
- [ ] Stripe API keys (production) configured
- [ ] Webhook endpoints registered
- [ ] External database configured (if using)
- [ ] Redis/cache configured
- [ ] Email service configured
- [ ] Monitoring service configured

## Deployment

### Pre-Deploy Tests
- [ ] Run PHPUnit tests (`composer test`)
- [ ] Run frontend linters (`npm run lint`)
- [ ] Check for security vulnerabilities
- [ ] Test in staging environment
- [ ] Load testing completed
- [ ] Backup verification

### Deploy Process
- [ ] Create database backup
- [ ] Create file backup
- [ ] Put site in maintenance mode
- [ ] Pull latest code
- [ ] Run migrations
- [ ] Clear caches
- [ ] Test critical functionality
- [ ] Exit maintenance mode

### Post-Deploy Verification
- [ ] Homepage loads correctly
- [ ] Admin dashboard accessible
- [ ] Newera plugin active
- [ ] Database connection working
- [ ] API endpoints responding
- [ ] Health check passing (`/wp-json/newera/v1/health`)
- [ ] No PHP errors in logs
- [ ] SSL certificate valid

## Post-Deployment

### Monitoring Setup
- [ ] Error logging enabled
- [ ] Performance monitoring configured
- [ ] Uptime monitoring configured
- [ ] Log aggregation setup
- [ ] Alert notifications configured
- [ ] Backup automation configured

### Performance Optimization
- [ ] Redis cache enabled
- [ ] Object caching configured
- [ ] Page caching configured
- [ ] Image optimization enabled
- [ ] CDN configured (if applicable)
- [ ] Database optimization complete

### Security Hardening
- [ ] Admin username changed from "admin"
- [ ] Limit login attempts enabled
- [ ] Two-factor authentication enabled
- [ ] Regular security audits scheduled
- [ ] Vulnerability scanning configured
- [ ] Intrusion detection configured

### Documentation
- [ ] Deployment notes documented
- [ ] Architecture diagram updated
- [ ] API documentation current
- [ ] Runbook created
- [ ] Team trained on deployment

## Ongoing Maintenance

### Daily
- [ ] Check error logs
- [ ] Monitor performance metrics
- [ ] Review security alerts

### Weekly
- [ ] Check for WordPress updates
- [ ] Check for plugin updates
- [ ] Review backup status
- [ ] Analyze usage patterns

### Monthly
- [ ] Security audit
- [ ] Performance review
- [ ] Database optimization
- [ ] Update dependencies
- [ ] Review and rotate logs

### Quarterly
- [ ] Disaster recovery test
- [ ] Security penetration test
- [ ] Load testing
- [ ] Review and update documentation

## Rollback Plan

If deployment fails:

1. **Enable maintenance mode**
   ```bash
   wp maintenance-mode activate
   ```

2. **Restore database backup**
   ```bash
   wp db import backup.sql
   ```

3. **Restore code to previous version**
   ```bash
   git checkout <previous-tag>
   composer install --no-dev
   ```

4. **Clear caches**
   ```bash
   wp cache flush
   wp rewrite flush
   ```

5. **Verify functionality**
   - Test critical paths
   - Check error logs
   - Verify API endpoints

6. **Disable maintenance mode**
   ```bash
   wp maintenance-mode deactivate
   ```

## Emergency Contacts

- **Technical Lead**: [Name/Email]
- **DevOps**: [Name/Email]
- **Security**: security@yourproject.com
- **Hosting Support**: [Provider/Contact]

## Additional Resources

- [Deployment Guide](DEPLOYMENT.md)
- [Security Best Practices](SECURITY.md)
- [README](README.md)
- [Issue Tracker](https://github.com/jith-jstrange/NewEra/issues)

---

**Sign-Off**

- [ ] Deployed by: ________________
- [ ] Reviewed by: ________________
- [ ] Date: ________________
- [ ] Version: ________________
