# Usamos PHP 8.2 con FPM (FastCGI Process Manager — el que se comunica con Nginx)
FROM php:8.2-fpm

# Instalamos dependencias del sistema operativo que necesitan las extensiones de PHP
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instalamos las extensiones de PHP que usa Laravel
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Copiamos Composer desde su imagen oficial (no necesitamos instalarlo manualmente)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Definimos /var/www como directorio de trabajo dentro del contenedor
WORKDIR /var/www

# Copiamos todo el proyecto al contenedor
COPY . .

# Instalamos dependencias PHP (sin las de dev, optimizado para producción)
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

# Damos permisos a Laravel sobre las carpetas que necesita escribir
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# PHP-FPM escucha en el puerto 9000
EXPOSE 9000

CMD ["php-fpm"]
