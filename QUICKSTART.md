# Quick Start Guide - Newera Plugin

Get up and running with Newera in 5 minutes!

## 🚀 Fastest Way: One Command

```bash
git clone https://github.com/jith-jstrange/NewEra.git && cd NewEra && ./deploy.sh
```

That's it! Access at http://localhost:8080

## 📋 Step-by-Step (Docker)

### 1. Clone Repository
```bash
git clone https://github.com/jith-jstrange/NewEra.git
cd NewEra
```

### 2. Configure Environment
```bash
cp .env.example .env
# Edit .env and set your passwords
nano .env
```

### 3. Start Application
```bash
docker-compose up -d
```

### 4. Access WordPress
- **Site**: http://localhost:8080
- **Admin**: http://localhost:8080/wp-admin
- **Credentials**: admin / admin (change immediately!)

## 🛠️ Using Make Commands

```bash
make setup      # Initial setup
make dev        # Start development
make logs       # View logs
make shell      # Access container
make test       # Run tests
make deploy     # Production build
```

## 📦 Manual Installation

### Prerequisites
- PHP 7.4+
- MySQL 5.6+
- Composer
- Node.js 18+

### Steps
```bash
# 1. Install dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# 2. Copy to WordPress
cp -r . /path/to/wordpress/wp-content/plugins/newera/

# 3. Activate
wp plugin activate newera
```

## 🔧 Development Setup

### With Docker
```bash
make setup
make dev-watch    # Start with asset watching
```

### Without Docker
```bash
# Install dependencies
composer install
npm install

# Build assets
npm run watch

# Run tests
composer test
```

## 📚 Key Locations

- **Admin Dashboard**: WP Admin → Newera
- **Settings**: Newera → Settings
- **Health Check**: `/wp-json/newera/v1/health`
- **Logs**: `wp-content/newera-logs/newera.log`

## 🔐 Configuration

### Required
```bash
# .env file
MYSQL_ROOT_PASSWORD=your-password
MYSQL_PASSWORD=your-password
WORDPRESS_AUTH_KEY=generate-at-wordpress.org
```

### Optional
```bash
# Stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...

# External Database
EXTERNAL_DB_CONNECTION_STRING=postgresql://...

# AI
OPENAI_API_KEY=sk-...
```

## 🧪 Testing

```bash
# Run all tests
make test

# Run specific tests
composer test-unit
composer test-coverage

# Lint code
npm run lint
```

## 🐛 Troubleshooting

### Container won't start
```bash
docker-compose down -v
docker-compose up -d --build
```

### Database connection failed
```bash
docker-compose logs db
docker-compose restart db
```

### Assets not loading
```bash
npm run build
docker-compose restart wordpress
```

## 📖 Next Steps

1. **Complete Setup Wizard**: Go to Newera → Setup
2. **Configure Integrations**: Newera → Integrations
3. **Review Documentation**: See [README.md](README.md)
4. **Check Security**: See [SECURITY.md](SECURITY.md)

## 🆘 Getting Help

- **Documentation**: [README.md](README.md)
- **Deployment**: [DEPLOYMENT.md](DEPLOYMENT.md)
- **Issues**: [GitHub Issues](https://github.com/jith-jstrange/NewEra/issues)

## 🎯 Common Tasks

### Create a Module
```php
<?php
namespace Newera\Modules\MyModule;

use Newera\Modules\BaseModule;

class MyModule extends BaseModule {
    public function getId() { return 'my-module'; }
    public function getName() { return 'My Module'; }
    public function getType() { return 'custom'; }
    
    public function registerHooks() {
        add_action('init', [$this, 'init']);
    }
}
```

### Add Custom Routes
```php
add_action('rest_api_init', function() {
    register_rest_route('newera/v1', '/custom', [
        'methods' => 'GET',
        'callback' => 'my_callback',
        'permission_callback' => '__return_true',
    ]);
});
```

### Store Secure Data
```php
$state_manager = newera_get_state_manager();
$state_manager->setSecure('my_module', 'api_key', $api_key);
$api_key = $state_manager->getSecure('my_module', 'api_key');
```

## 🌟 Pro Tips

1. **Use Make**: Simplifies common tasks
2. **Enable Debug**: Set `WP_DEBUG=true` for development
3. **Check Logs**: View `docker-compose logs -f` for issues
4. **Use Health Check**: Monitor at `/wp-json/newera/v1/health`
5. **Test Before Deploy**: Always run `make test`

## 📊 Architecture Overview

```
NewEra Plugin
├── Core (Bootstrap, Logger, State)
├── Database (Migrations, Repositories)
├── Admin (Dashboard, Settings)
├── Modules (Auto-discovered from /modules)
├── Security (Headers, Rate Limiting, CORS)
└── API (REST endpoints, Webhooks)
```

Happy coding! 🚀
