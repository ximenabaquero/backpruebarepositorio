FROM php:8.4-fpm

# Instalador de extensiones PHP pre-compiladas (mucho más rápido que docker-php-ext-install)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Instalamos herramientas del sistema y extensiones PHP en un solo paso
RUN apt-get update && apt-get install -y git curl zip unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_mysql mbstring exif pcntl bcmath gd zip

# Copiamos Composer desde su imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copiamos solo los archivos de dependencias primero (mejor cache de capas)
COPY composer.json composer.lock ./

ENV COMPOSER_MEMORY_LIMIT=-1
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

# Copiamos el resto del proyecto
COPY . .

RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && mkdir -p /var/www/storage/framework/sessions \
               /var/www/storage/framework/views \
               /var/www/storage/framework/cache \
               /var/www/storage/logs \
    && chmod -R 777 /var/www/storage /var/www/bootstrap/cache

EXPOSE 8000
CMD ["sh", "-c", "php artisan migrate --force || true && php artisan db:seed --class=ClinicSeeder --force || true && php artisan serve --host=0.0.0.0 --port=8000"]
