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

# Migrate con retry — il DNS potrebbe non essere subito disponibile
echo "==> Running migrations..."
for i in 1 2 3 4 5; do
    php artisan migrate --force && break
    echo "Tentativo $i fallito, riprovo tra 5 secondi..."
    sleep 5
done

apache2-foreground
