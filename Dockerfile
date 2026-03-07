# ---- Install PHP dependencies (needed by both frontend and runtime) ----
FROM composer:2 AS composer-deps

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---- Build frontend assets ----
FROM node:24-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=composer-deps /app/vendor vendor

# The Reverb app key is hardcoded — it is an internal credential, not user-configurable.
# Host, port, and scheme are derived at runtime from window.location
# since Nginx reverse-proxies /app and /apps to Reverb internally.
ENV VITE_REVERB_APP_KEY=armaman-key

RUN npm run build

# ---- Application image ----
# Uses cm2network/steamcmd as base — Debian with SteamCMD pre-installed at /home/steam/steamcmd/steamcmd.sh
# Following the same pattern as https://github.com/fugasjunior/arma-server-manager
FROM cm2network/steamcmd AS runtime

USER root

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Install PHP 8.5, Nginx, Supervisor, and SQLite
RUN dpkg --add-architecture i386 \
    && apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    gnupg \
    lib32gcc-s1 \
    lib32stdc++6 \
    lsb-release \
    nginx \
    sqlite3 \
    supervisor \
    unzip \
    && curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /usr/share/keyrings/sury-php.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/sury-php.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
    php8.5-fpm \
    php8.5-cli \
    php8.5-sqlite3 \
    php8.5-mbstring \
    php8.5-xml \
    php8.5-curl \
    php8.5-zip \
    php8.5-bcmath \
    php8.5-intl \
    php8.5-tokenizer \
    && apt-get clean autoclean \
    && apt-get autoremove --yes \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP — upload limits for large PBO mission files (aligned with Livewire config)
COPY docker/php.ini /etc/php/8.5/fpm/conf.d/99-armaman.ini
COPY docker/php.ini /etc/php/8.5/cli/conf.d/99-armaman.ini

# Configure PHP-FPM
RUN sed -i 's|^listen = .*|listen = /run/php/php-fpm.sock|' /etc/php/8.5/fpm/pool.d/www.conf \
    && sed -i 's|^;listen.owner = .*|listen.owner = www-data|' /etc/php/8.5/fpm/pool.d/www.conf \
    && sed -i 's|^;listen.group = .*|listen.group = www-data|' /etc/php/8.5/fpm/pool.d/www.conf \
    && mkdir -p /run/php

# Configure Nginx
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Configure Supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
RUN mkdir -p /var/log/supervisor

# Copy application
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=frontend /app/public/build public/build
COPY --from=composer-deps /app/vendor vendor

# Finalize Composer autoloader
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-interaction

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Entrypoint script — runs migrations, generates APP_KEY if missing, then starts supervisord
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# SteamCMD is at /home/steam/steamcmd/steamcmd.sh in cm2network image
ENV STEAMCMD_PATH=/home/steam/steamcmd/steamcmd.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
