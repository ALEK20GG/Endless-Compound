FROM php:8.2-apache

# Installa estensioni necessarie
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql zip

# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copia il progetto
WORKDIR /var/www/html
COPY . .

# Installa dipendenze
RUN composer install --optimize-autoloader --no-dev

# Configura Apache per Laravel
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

# Permessi storage
RUN chown -R www-data:www-data storage bootstrap/cache

# Script di avvio
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]