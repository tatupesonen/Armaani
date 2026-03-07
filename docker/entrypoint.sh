#!/bin/bash
set -e

APP_KEY_FILE="/var/www/html/storage/.app_key"
REVERB_SECRET_FILE="/var/www/html/storage/.reverb_secret"

# ── Filesystem setup (must run before any artisan commands) ──────────────
# Bind mounts may start empty, so create the full Laravel storage structure.
mkdir -p /var/www/html/storage/arma/games \
         /var/www/html/storage/arma/servers \
         /var/www/html/storage/arma/mods \
         /var/www/html/storage/arma/missions \
         /var/www/html/storage/logs \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views

# SQLite database lives inside storage/ so a single volume mount persists everything.
# This overrides the default database_path('database.sqlite') from config/database.php.
export DB_DATABASE="/var/www/html/storage/database.sqlite"
touch "$DB_DATABASE"
chown www-data:www-data "$DB_DATABASE"

chown -R www-data:www-data /var/www/html/storage

# ── APP_KEY: env var > persisted file > generate new ────────────────────
if [ -n "$APP_KEY" ]; then
    echo "Using APP_KEY from environment."
elif [ -f "$APP_KEY_FILE" ]; then
    APP_KEY=$(cat "$APP_KEY_FILE")
    export APP_KEY
    echo "Loaded APP_KEY from ${APP_KEY_FILE}."
else
    APP_KEY=$(php /var/www/html/artisan key:generate --show --no-interaction)
    export APP_KEY
    echo "$APP_KEY" > "$APP_KEY_FILE"
    chmod 600 "$APP_KEY_FILE"
    echo "Generated and persisted APP_KEY to ${APP_KEY_FILE}."
fi

# ── REVERB_APP_SECRET: persisted file > generate new ───────────────────
# Used server-side only (signing broadcasts); must be unique per installation.
if [ -f "$REVERB_SECRET_FILE" ]; then
    REVERB_APP_SECRET=$(cat "$REVERB_SECRET_FILE")
    export REVERB_APP_SECRET
    echo "Loaded REVERB_APP_SECRET from ${REVERB_SECRET_FILE}."
else
    REVERB_APP_SECRET=$(openssl rand -hex 32)
    export REVERB_APP_SECRET
    echo "$REVERB_APP_SECRET" > "$REVERB_SECRET_FILE"
    chmod 600 "$REVERB_SECRET_FILE"
    echo "Generated and persisted REVERB_APP_SECRET to ${REVERB_SECRET_FILE}."
fi

# ── Configure Nginx listen port (default: 8080) ────────────────────────
sed -i "s/listen 80;/listen ${APP_PORT:-8080};/" /etc/nginx/sites-available/default

# ── Database migrations ────────────────────────────────────────────────
php /var/www/html/artisan migrate --force --no-interaction

# ── Create initial admin user if env vars are provided ─────────────────
if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
    php /var/www/html/artisan user:create-admin \
        --email="$ADMIN_EMAIL" \
        --password="$ADMIN_PASSWORD" \
        ${ADMIN_NAME:+--name="$ADMIN_NAME"} \
        --no-interaction
fi

# ── Cache configuration and routes for production ──────────────────────
php /var/www/html/artisan config:cache --no-interaction
php /var/www/html/artisan route:cache --no-interaction
php /var/www/html/artisan view:cache --no-interaction

# ── Start supervisord (nginx, php-fpm, queue worker, reverb) ───────────
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
