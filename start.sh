#!/bin/sh

echo "=== Corriendo migraciones ==="
php artisan migrate --force || echo "Migraciones fallaron o ya estaban al dia"

echo "=== Corriendo seeder ==="
php artisan db:seed --class=ClinicSeeder --force || echo "Seeder ya corrido o fallo"

echo "=== Limpiando cache ==="
php artisan config:clear || true
php artisan cache:clear || true

echo "=== Iniciando servidor ==="
exec php artisan serve --host=0.0.0.0 --port=8000
