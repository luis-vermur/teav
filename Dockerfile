# Imagen de producción para Railway.
#
# Tres etapas: se compilan los assets con Node, se resuelven las dependencias
# de PHP con Composer, y sólo el resultado de ambas entra a la imagen final.
# Ni node_modules ni Composer viajan a producción.

# ─────────────────── 1. Assets (Vite + tema de Filament) ───────────────────
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

# vite.config.js compila tres entradas, y una es el tema propio del panel
# (resources/css/filament/admin/theme.css). Sin él, Filament carga pero sin
# los estilos de la marca.
COPY vite.config.js ./
COPY resources ./resources

RUN npm run build


# ─────────────────── 2. Dependencias de PHP ───────────────────
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# --no-scripts aquí: los scripts de Laravel necesitan el proyecto completo,
# que todavía no se ha copiado. Se corren hasta abajo, ya con todo en su sitio.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative


# ─────────────────── 3. Imagen final ───────────────────
# serversideup/php trae nginx + php-fpm ya afinados para Laravel, corre como
# usuario sin privilegios y expone un healthcheck. Evita armar a mano una
# configuración de nginx que nadie va a querer mantener.
FROM serversideup/php:8.3-fpm-nginx

# PostgreSQL y el resto de lo que usa el proyecto.
USER root
RUN install-php-extensions pdo_pgsql pgsql bcmath intl zip gd exif
USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Assets de Filament, generados contra la versión del paquete que quedó
# instalada. Es la razón por la que public/vendor no se versiona: así nunca
# quedan desfasados respecto a la versión real.
RUN php artisan filament:assets

# Arranque: migraciones y cachés. Se ejecuta en cada despliegue, antes de
# que nginx empiece a recibir tráfico.
USER root
COPY docker/arranque.sh /etc/entrypoint.d/90-teav.sh
RUN chmod +x /etc/entrypoint.d/90-teav.sh

# El programador de Laravel como servicio de s6, junto a nginx y php-fpm.
#
# Railway no permite compartir un volumen entre servicios y `teav:respaldar`
# escribe en el mismo volumen que los expedientes: un servicio de cron
# separado se quedaría sin dónde dejar los respaldos. Por eso va aquí dentro.
COPY docker/s6/programador /etc/s6-overlay/s6-rc.d/programador
RUN chmod +x /etc/s6-overlay/s6-rc.d/programador/run \
    && touch /etc/s6-overlay/s6-rc.d/user/contents.d/programador

USER www-data

# Railway inyecta PORT; la imagen la lee de esta variable.
ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    AUTORUN_ENABLED=false
