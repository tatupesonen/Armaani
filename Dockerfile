# =============================================================================
# Stage 1: Base runtime image (PHP 8.5 + Caddy + Supervisor + SteamCMD)
# =============================================================================
FROM cm2network/steamcmd AS base

USER root

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg2 lsb-release \
    # ---- Sury PHP repository (Debian) ----
    && curl -sSLo /tmp/debsuryorg-archive-keyring.deb \
        https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] \
        https://packages.sury.org/php/ $(lsb_release -sc) main" \
        > /etc/apt/sources.list.d/sury-php.list \
    # ---- Caddy repository ----
    && curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
        | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg \
    && curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
        > /etc/apt/sources.list.d/caddy-stable.list \
    # ---- Install packages ----
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        caddy \
        procps \
        supervisor \
        xz-utils \
        # PHP FPM + CLI (php8.5-common is auto-installed and includes
        # opcache, pcntl, pdo, tokenizer, fileinfo, sockets, etc.)
        php8.5-fpm \
        php8.5-cli \
        # Additional PHP extensions
        php8.5-bcmath \
        php8.5-curl \
        php8.5-intl \
        php8.5-mbstring \
        php8.5-readline \
        php8.5-sqlite3 \
        php8.5-xml \
        php8.5-zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/*

# Configure PHP-FPM to run as root with a version-independent socket path
RUN sed -i 's|^listen = .*|listen = /run/php/php-fpm.sock|' /etc/php/8.5/fpm/pool.d/www.conf \
    && sed -i 's|^user = .*|user = root|' /etc/php/8.5/fpm/pool.d/www.conf \
    && sed -i 's|^group = .*|group = root|' /etc/php/8.5/fpm/pool.d/www.conf \
    && sed -i 's|^listen\.owner = .*|listen.owner = root|' /etc/php/8.5/fpm/pool.d/www.conf \
    && sed -i 's|^listen\.group = .*|listen.group = root|' /etc/php/8.5/fpm/pool.d/www.conf \
    && echo 'catch_workers_output = yes' >> /etc/php/8.5/fpm/pool.d/www.conf \
    && echo 'decorate_workers_output = no' >> /etc/php/8.5/fpm/pool.d/www.conf \
    && sed -i 's|^error_log = .*|error_log = /dev/stderr|' /etc/php/8.5/fpm/php-fpm.conf \
    && mkdir -p /run/php

# PHP production tuning
RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.interned_strings_buffer=8"; \
        echo "opcache.max_accelerated_files=10000"; \
        echo "opcache.validate_timestamps=0"; \
        echo "upload_max_filesize=512M"; \
        echo "post_max_size=512M"; \
    } | tee /etc/php/8.5/fpm/conf.d/99-production.ini \
              /etc/php/8.5/cli/conf.d/99-production.ini > /dev/null

WORKDIR /var/www/html

# =============================================================================
# Stage 2: Build (extends base — adds Node for Wayfinder + Vite)
# =============================================================================
FROM base AS build

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

# Temporary .env so artisan can bootstrap during build (Wayfinder needs routes)
RUN cp .env.example .env \
    && php -r "echo 'APP_KEY=base64:' . base64_encode(random_bytes(32)) . PHP_EOL;" >> .env \
    && mkdir -p storage/framework/{cache,sessions,views} \
    && touch database/database.sqlite

RUN composer install --no-dev --optimize-autoloader

RUN npm ci \
    && npm run build \
    && rm -rf node_modules .env

# =============================================================================
# Stage 2b: Test (extends build — keeps dev deps + tests for CI)
# =============================================================================
FROM build AS test

# Recreate .env (removed at end of build stage) and re-install with dev deps
RUN cp .env.example .env \
    && php -r "echo 'APP_KEY=base64:' . base64_encode(random_bytes(32)) . PHP_EOL;" >> .env \
    && composer install --optimize-autoloader

CMD ["php", "artisan", "test", "--compact"]

# =============================================================================
# Stage 3: Final runtime (base + built app, no Node)
# =============================================================================
FROM base

# Application files from build stage (includes vendor + public/build + wayfinder routes)
COPY --from=build /var/www/html ./

# Docker-specific configuration
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/env.docker /var/www/html/.env.docker

RUN chmod +x /entrypoint.sh

# Remove files not needed at runtime
RUN rm -rf node_modules tests .github docs docker \
    arma-server-manager .env .env.example .git

# Take ownership of SteamCMD directories for root
RUN chown -R root:root /home/steam \
    && chmod -R 755 /home/steam

EXPOSE 80 443

ENTRYPOINT ["/entrypoint.sh"]
