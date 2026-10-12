# Imagen de producción para Railway.
#
# Tres etapas: primero las dependencias de PHP, luego los assets (que las
# necesitan), y al final la imagen que se despliega. Ni node_modules ni
# Composer viajan a producción.

# ─────────────────── 1. Dependencias de PHP ───────────────────
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


# ─────────────────── 2. Assets (Vite + tema de Filament) ───────────────────
# Esta etapa va DESPUÉS de vendor y no al revés, porque el tema del panel
# arranca con:
#
#   @import '../../../../vendor/filament/filament/resources/css/theme.css';
#   @source  '../../../../app/Filament/**/*';
#
# O sea: necesita el paquete de Filament instalado y el código del panel a la
# vista. Sin vendor/ el build truena en el import; sin app/Filament/ compila
# pero Tailwind descarta las clases que sólo aparecen ahí y el panel sale
# desmaquillado, que es el fallo más difícil de diagnosticar de los dos.
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY app ./app
COPY --from=vendor /app/vendor ./vendor

RUN npm run build


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
COPY --chmod=755 docker/s6/programador /etc/s6-overlay/s6-rc.d/programador

# Registrar el servicio en el bundle "user": s6 sólo arranca lo que esté
# listado ahí. s6-overlay movió ese bundle de sitio en la versión 3.2.3.2
# (de s6-rc.d/user a user-bundles.d/user), y cuál de las dos trae la imagen
# depende de su versión. Se detecta en vez de suponerla, y si no aparece
# ninguna el build falla aquí en vez de desplegar un contenedor sano en el
# que, calladamente, nunca corre la cobranza.
RUN set -eu; \
    if [ -d /etc/s6-overlay/user-bundles.d/user ]; then \
        bundle=/etc/s6-overlay/user-bundles.d/user/contents.d; \
    elif [ -d /etc/s6-overlay/s6-rc.d/user ]; then \
        bundle=/etc/s6-overlay/s6-rc.d/user/contents.d; \
    else \
        echo "No encuentro el bundle 'user' de s6-overlay en esta imagen." >&2; \
        exit 1; \
    fi; \
    mkdir -p "$bundle"; \
    touch "$bundle/programador"; \
    echo "Programador registrado en $bundle" 

USER www-data

# Railway inyecta PORT; la imagen la lee de esta variable.
ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    AUTORUN_ENABLED=false
