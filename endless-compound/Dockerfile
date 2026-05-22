FROM php:8.2-apache

# Installa estensioni necessarie PHP e strumenti di base
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    curl \
    gnupg \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Installa Node.js (per build Vite)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copia SOLO i file delle dipendenze prima — così questo layer viene cachato
# e non viene rieseguito se solo il codice cambia
COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-dev --no-scripts

COPY package.json package-lock.json ./
RUN npm ci

# Ora copia il resto del progetto
COPY . .

# Esegui gli script post-install di composer (package:discover ecc.)
RUN composer run-script post-autoload-dump || true

# Build assets Vite
RUN npm run build

# Configura Apache per Laravel
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && a2enmod rewrite

# Aumenta timeout Apache
RUN echo "Timeout 300" >> /etc/apache2/apache2.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Aumenta timeout PHP per le chiamate LLM
RUN echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/custom.ini

# Script di avvio
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
