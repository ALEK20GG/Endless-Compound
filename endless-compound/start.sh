#!/bin/bash
set -e

# Permessi storage — fatto a runtime per coprire filesystem montati da Render
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
mkdir -p /var/www/html/storage/logs
touch /var/www/html/storage/logs/laravel.log
chmod 664 /var/www/html/storage/logs/laravel.log 2>/dev/null || true

# Genera APP_KEY se mancante
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY mancante, la genero..."
    php artisan key:generate --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrate con retry — il DNS potrebbe non essere subito disponibile
echo "==> Running migrations..."
for i in 1 2 3 4 5; do
    php artisan migrate --force && break
    echo "Tentativo $i fallito, riprovo tra 5 secondi..."
    sleep 5
done

apache2-foreground
