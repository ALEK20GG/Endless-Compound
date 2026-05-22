FROM php:8.2-apache

# Installa estensioni necessarie PHP e strumenti di base
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    curl \
    gnupg \
    && docker-php-ext-install pdo pdo_pgsql zip

# Installa Node.js (per build Vite)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copia il progetto
WORKDIR /var/www/html
COPY . .

# Installa dipendenze PHP
RUN composer install --optimize-autoloader --no-dev

# Installa dipendenze JS e builda gli asset Vite
RUN (npm ci || npm install) && npm run build

# Configura Apache per Laravel
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && a2enmod rewrite

# Aumenta timeout Apache per le chiamate LLM lunghe
RUN echo "Timeout 300" >> /etc/apache2/apache2.conf \
    && echo "ProxyTimeout 300" >> /etc/apache2/apache2.conf

# Script di avvio
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]