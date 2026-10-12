#!/bin/sh
#
# Se ejecuta en cada arranque del contenedor, antes de que nginx reciba
# tráfico. Tiene que ser idempotente: Railway reinicia el servicio por su
# cuenta (escalado, caída, redespliegue) y esto va a correr muchas veces.
set -e

VOLUMEN="${TEAV_DISCO_PERSISTENTE:-/var/www/html/storage/app}"

echo "→ Preparando el volumen persistente"

# Railway monta el volumen con dueño root, pero el panel corre como
# www-data: sin ajustar el dueño, la primera subida de un documento falla
# con un "Permission denied" que en el navegador se ve como un error
# genérico de carga.
mkdir -p "$VOLUMEN/documentos" "$VOLUMEN/respaldos"

if ! chown -R www-data:www-data "$VOLUMEN" 2>/dev/null; then
    echo "  (sin privilegios para ajustar el dueño; se asume que ya está bien)"
fi

# De aquí en adelante, como www-data. Si estas órdenes corrieran como root,
# las cachés y los logs quedarían con dueño root y el panel ya no podría
# escribirlos después.
como_www () {
    if [ "$(id -u)" = "0" ]; then
        su www-data -s /bin/sh -c "$1"
    else
        sh -c "$1"
    fi
}

echo "→ Migraciones"
# --force porque en producción Laravel pregunta, y aquí no hay quién conteste.
como_www "php /var/www/html/artisan migrate --force"

echo "→ Roles y permisos"
# Son configuración, no datos: al resembrarlos en cada despliegue, un permiso
# nuevo aparece solo, sin que nadie tenga que acordarse de correr nada. El
# seeder usa firstOrCreate en todo, así que repetirlo no pisa nada, y el
# usuario de prueba no se crea en producción.
como_www "php /var/www/html/artisan db:seed --class=RolesPermisosSeeder --force"

echo "→ Catálogos"
# Marcas, modelos, colores, aseguradoras y tipos de documento son
# configuración, no datos del negocio: sin ellos no se puede dar de alta un
# vehículo ni subir un documento. Todos son idempotentes.
como_www "php /var/www/html/artisan db:seed --class=ColoresSeeder --force"
como_www "php /var/www/html/artisan db:seed --class=MarcasSeeder --force"
como_www "php /var/www/html/artisan db:seed --class=AseguradorasSeeder --force"
como_www "php /var/www/html/artisan db:seed --class=TiposDocumentoSeeder --force"
como_www "php /var/www/html/artisan db:seed --class=TiposMantenimientoSeeder --force"

echo "→ Cachés de producción"
# Se reconstruyen en cada despliegue: si quedaran las del build anterior,
# una ruta o una variable nueva no aparecería y el fallo sería invisible.
como_www "php /var/www/html/artisan config:cache"
como_www "php /var/www/html/artisan route:cache"
como_www "php /var/www/html/artisan view:cache"
como_www "php /var/www/html/artisan filament:cache-components"

echo "→ Listo"
