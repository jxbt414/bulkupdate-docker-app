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
      && docker-php-ext-configure gd --with-freetype --with-jpeg \
      && docker-php-ext-install -j$(nproc) \
          pdo_mysql \
          mbstring \
          exif \
          pcntl \
          bcmath \
          gd \
          zip \
          intl \
          opcache

  # Install Composer
  COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

  WORKDIR /var/www/html

  # Copy composer files first for caching
  COPY composer.json composer.lock ./
  RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

  # Copy application code
  COPY . .

  # Generate optimized autoload and run post-install scripts
  RUN composer dump-autoload --optimize \
      && php artisan config:cache \
      && php artisan route:cache \
      && php artisan view:cache

  # Set permissions
  RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

  # Copy nginx and supervisor configs
  COPY docker/nginx.conf /etc/nginx/nginx.conf
  COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

  EXPOSE 80

  CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

  Supporting files you'll need:

  docker/nginx.conf:
  worker_processes auto;
  error_log /dev/stderr warn;

  events {
      worker_connections 1024;
  }

  http {
      include /etc/nginx/mime.types;
      default_type application/octet-stream;
      access_log /dev/stdout;

      server {
          listen 80;
          root /var/www/html/public;
          index index.php;

          location / {
              try_files $uri $uri/ /index.php?$query_string;
          }

          location ~ \.php$ {
              fastcgi_pass 127.0.0.1:9000;
              fastcgi_index index.php;
              fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
              include fastcgi_params;
          }
      }
  }

  docker/supervisord.conf:
  [supervisord]
  nodaemon=true
  logfile=/dev/null
  logfile_maxbytes=0

  [program:php-fpm]
  command=php-fpm
  autostart=true
  autorestart=true
  stdout_logfile=/dev/stdout
  stdout_logfile_maxbytes=0
  stderr_logfile=/dev/stderr
  stderr_logfile_maxbytes=0

  [program:nginx]
  command=nginx -g "daemon off;"
  autostart=true
  autorestart=true
  stdout_logfile=/dev/stdout
  stdout_logfile_maxbytes=0
  stderr_logfile=/dev/stderr
  stderr_logfile_maxbytes=0

  Build and run:
  docker build -t laravel-app .
  docker run -p 8080:80 --env-file .env laravel-app
