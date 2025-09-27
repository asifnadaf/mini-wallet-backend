FROM php:8.2-cli AS base

ARG DEBIAN_FRONTEND=noninteractive

# ---------- System deps ----------
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zlib1g-dev \
    pkg-config \
    libsqlite3-dev \
    build-essential \
    supervisor \
    tzdata \
    nano \
    vim \
    default-mysql-client \
    cron \
    && rm -rf /var/lib/apt/lists/*

# ---------- Timezone ----------
ENV TZ=Asia/Dubai
RUN ln -sf /usr/share/zoneinfo/Asia/Dubai /etc/localtime \
 && dpkg-reconfigure -f noninteractive tzdata

# ---------- PHP extensions ----------
RUN docker-php-ext-install pdo pdo_mysql mbstring zip bcmath pcntl opcache \
&& pecl install redis \
&& docker-php-ext-enable redis
   
# ---------- Install Composer ----------
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# ---------- Install Swoole ----------
RUN pecl install swoole-5.1.0 \
 && docker-php-ext-enable swoole

# ---------- PHP settings ----------
RUN echo "date.timezone = Asia/Dubai" > /usr/local/etc/php/conf.d/99-timezone.ini \
 && echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/99-memory.ini \
 && echo "opcache.enable=1" > /usr/local/etc/php/conf.d/99-opcache.ini \
 && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/99-opcache.ini \
 && echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/99-opcache.ini \
 && echo "opcache.validate_timestamp=1" >> /usr/local/etc/php/conf.d/99-opcache.ini

# ---------- Workdir ----------
WORKDIR /var/www

# Only copy composer files for layer caching
COPY composer.json composer.lock /var/www/

# Install PHP deps (vendor dir will be overridden by volume mount in dev)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader || true

# ---------- Entrypoint ----------
COPY ./entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Supervisor config
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./laravel-cron /etc/cron.d/laravel-cron
RUN chmod 0644 /etc/cron.d/laravel-cron && crontab /etc/cron.d/laravel-cron

# Create required dirs
RUN mkdir -p storage bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache /var/log/supervisor \
 && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "octane:start", "--server=swoole", "--workers=4", "--max-requests=500", "--host=0.0.0.0", "--port=8000"]
