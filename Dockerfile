# ---- Build frontend assets ----
FROM node:20-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- Application image ----
# Uses cm2network/steamcmd as base — Debian with SteamCMD pre-installed at /home/steam/steamcmd/steamcmd.sh
# Following the same pattern as https://github.com/fugasjunior/arma-server-manager
FROM cm2network/steamcmd AS runtime

USER root

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Install PHP 8.4, Nginx, Supervisor, and SQLite
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
    php8.4-fpm \
    php8.4-cli \
    php8.4-sqlite3 \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-curl \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-tokenizer \
    && apt-get clean autoclean \
    && apt-get autoremove --yes \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP-FPM
RUN sed -i 's|^listen = .*|listen = /run/php/php-fpm.sock|' /etc/php/8.4/fpm/pool.d/www.conf \
    && sed -i 's|^;listen.owner = .*|listen.owner = www-data|' /etc/php/8.4/fpm/pool.d/www.conf \
    && sed -i 's|^;listen.group = .*|listen.group = www-data|' /etc/php/8.4/fpm/pool.d/www.conf \
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

# Install Composer dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Create default SQLite database
RUN touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite

# Storage directories for server files and mods
RUN mkdir -p /data/servers /data/mods \
    && chown -R www-data:www-data /data

# SteamCMD is at /home/steam/steamcmd/steamcmd.sh in cm2network image
ENV STEAMCMD_PATH=/home/steam/steamcmd/steamcmd.sh
ENV SERVERS_BASE_PATH=/data/servers
ENV MODS_BASE_PATH=/data/mods

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
