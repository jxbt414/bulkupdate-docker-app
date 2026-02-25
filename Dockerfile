FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    libxml2-dev \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions
# - SOAP required for Google Ads PHP library
# - Other extensions for Laravel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        soap

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure PHP
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/custom.ini"

# Configure nginx
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Configure supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY . .

# Generate optimized autoload and discover packages (no-dev)
RUN composer install --no-dev --optimize-autoloader --prefer-dist

# Build frontend assets (production only)
RUN if [ -f package.json ]; then \
        apk add --no-cache nodejs npm && \
        npm ci && \
        npm run build && \
        apk del nodejs npm; \
    fi

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create required directories
RUN mkdir -p /var/log/supervisor /run/nginx

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
