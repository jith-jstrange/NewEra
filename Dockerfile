# Newera WordPress Plugin - Docker Image
# This creates a complete WordPress environment with the Newera plugin pre-installed

FROM wordpress:6.4-php8.2-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    zip \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    opcache \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set recommended PHP.ini settings for WordPress
RUN { \
    echo 'upload_max_filesize = 64M'; \
    echo 'post_max_size = 64M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 300'; \
    echo 'max_input_vars = 3000'; \
    echo 'max_input_time = 300'; \
    echo 'opcache.enable = 1'; \
    echo 'opcache.memory_consumption = 128'; \
    echo 'opcache.interned_strings_buffer = 8'; \
    echo 'opcache.max_accelerated_files = 4000'; \
    echo 'opcache.revalidate_freq = 60'; \
    echo 'opcache.fast_shutdown = 1'; \
} > /usr/local/etc/php/conf.d/newera-recommended.ini

# Enable Apache modules
RUN a2enmod rewrite expires headers

# Create plugin directory
RUN mkdir -p /var/www/html/wp-content/plugins/newera

# Set working directory
WORKDIR /var/www/html/wp-content/plugins/newera

# Copy plugin files
COPY . .

# Install Composer dependencies (production only)
RUN if [ -f composer.json ]; then \
    composer install --no-dev --optimize-autoloader --no-interaction; \
    fi

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/wp-content/plugins/newera \
    && chmod -R 755 /var/www/html/wp-content/plugins/newera

# Create logs directory
RUN mkdir -p /var/www/html/wp-content/newera-logs \
    && chown -R www-data:www-data /var/www/html/wp-content/newera-logs \
    && chmod -R 755 /var/www/html/wp-content/newera-logs

# Set working directory back to WordPress root
WORKDIR /var/www/html

# Add custom entrypoint for plugin activation
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
