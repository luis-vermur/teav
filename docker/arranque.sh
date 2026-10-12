#!/bin/sh
#
# Se ejecuta en cada arranque del contenedor, antes de que nginx reciba
# tráfico. Tiene que ser idempotente: Railway reinicia el servicio por su
# cuenta (escalado, caída, redespliegue) y esto va a correr muchas veces.
set -e

echo "→ Preparando el volumen persistente"
# Los expedientes y los respaldos viven aquí, no en el disco del contenedor.
mkdir -p "${TEAV_DISCO_PERSISTENTE:-/var/www/html/storage/app}/documentos" \
         "${TEAV_DISCO_PERSISTENTE:-/var/www/html/storage/app}/respaldos"

echo "→ Migraciones"
# --force porque en producción Laravel pregunta, y aquí no hay quién conteste.
php /var/www/html/artisan migrate --force

echo "→ Cachés de producción"
# Se reconstruyen en cada despliegue: si quedaran las del build anterior,
# una ruta o una variable nueva no aparecería y el fallo sería invisible.
php /var/www/html/artisan config:cache
php /var/www/html/artisan route:cache
php /var/www/html/artisan view:cache
php /var/www/html/artisan filament:cache-components

echo "→ Listo"
