#!/bin/bash
set -e

# Genera APP_KEY se mancante
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY mancante, la genero..."
    php artisan key:generate --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
apache2-foreground
