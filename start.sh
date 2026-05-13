#!/bin/sh
set -e

echo "=== Corriendo migraciones ==="
php artisan migrate --force

echo "=== Corriendo seeder ==="
php artisan db:seed --class=ClinicSeeder --force || echo "Seeder ya corrido, continuando..."

echo "=== Limpiando cache ==="
php artisan config:clear
php artisan cache:clear

echo "=== Iniciando servidor ==="
php artisan serve --host=0.0.0.0 --port=8000
