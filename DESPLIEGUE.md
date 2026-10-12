# Despliegue en Railway

Pasos para poner el sistema en línea. Están en orden y cada uno depende del
anterior.

---

## 1. Subir el repositorio

```bash
git remote add origin <tu-repo>
git push -u origin main
```

El repositorio **no lleva datos reales**. Lo verifican estas reglas del
`.gitignore`, y conviene no quitarlas:

| Ruta | Por qué |
|---|---|
| `.env` | credenciales y `APP_KEY` |
| `storage/app/documentos` | INE, actas, tarjetas de circulación de clientes reales |
| `storage/app/respaldos` | la base entera, comprimida |
| `public/vendor` | assets de Filament; se regeneran en cada build |

Los CSV de la flota viven en `datos-reales/`, **fuera del proyecto**, por la
misma razón.

---

## 2. Crear el proyecto y la base

En Railway: **New Project → Deploy from GitHub repo**, y después
**New → Database → PostgreSQL** en el mismo proyecto.

El `railway.json` ya le dice que construya con el `Dockerfile` y que vigile
`/admin/login` como healthcheck.

---

## 3. El volumen

**New → Volume**, montado en `/datos`, sobre el servicio de la aplicación.

Esto no es opcional. El sistema de archivos del contenedor se recrea en cada
despliegue: sin volumen, los expedientes y los respaldos **desaparecen en el
siguiente `git push`**. Lo peor es que no truena — la base sigue listando los
documentos y la descarga responde 404.

> Railway no permite compartir un volumen entre servicios. Por eso el
> programador de tareas corre *dentro* del contenedor web (como servicio de
> s6) y no como un servicio aparte: `teav:respaldar` escribe en este mismo
> volumen.

---

## 4. Variables de entorno

```env
APP_NAME="COMERCIALIZADORA TEAV"
APP_ENV=production
APP_DEBUG=false
APP_KEY=                      # ← la MISMA de tu .env local
APP_URL=https://<tu-dominio>
APP_TIMEZONE=America/Mexico_City
APP_LOCALE=es

TEAV_DISCO_PERSISTENTE=/datos

DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Dos que no se pueden equivocar:

- **`APP_KEY` tiene que ser la misma** que la local. Es la llave con la que se
  cifran las sesiones y los datos sensibles; con otra, nada de lo existente se
  descifra.
- **`APP_DEBUG=false`**. En `true`, cada error muestra rutas, consultas y
  variables de entorno a quien lo provoque. Es la fuga de datos más común de
  Laravel.

El correo sigue pendiente: en `MAIL_MAILER=log` no se envía nada y quien
olvide su contraseña se queda fuera. En `.env.example` está el bloque con los
ajustes de Google Workspace, Resend y Mailgun.

---

## 5. Primer arranque

El contenedor corre solo, en cada despliegue:

1. crea las carpetas del volumen,
2. `migrate --force`,
3. `config:cache`, `route:cache`, `view:cache`, `filament:cache-components`.

Falta sembrar los roles y crear el primer usuario. Desde la consola de Railway:

```bash
php artisan db:seed --class=RolesPermisosSeeder
php artisan make:filament-user
```

Y después asignarle el rol:

```bash
php artisan tinker --execute='App\Models\User::first()->assignRole("administrador");'
```

---

## 6. Llevar la flota

Los datos reales no están en el repositorio, así que hay dos caminos:

**Restaurar el respaldo local** (lleva datos y documentos, es lo más rápido):

```bash
# en tu máquina
php artisan teav:respaldar
# sube el .sql.gz y los archivos de storage/app/documentos al volumen
```

**O volver a importar** subiendo `datos-reales/` y las carpetas de los autos
al volumen, y corriendo:

```bash
php artisan teav:importar --ruta=/datos/datos-reales \
                          --documentos --archivos=/datos/carros
```

---

## 7. Comprobar que quedó bien

| Qué | Cómo |
|---|---|
| El panel responde | abrir `/admin/login` |
| Los documentos se descargan | abrir un vehículo → Documentos → Descargar |
| El programador vive | `php artisan schedule:list` y revisar logs al día siguiente |
| Los respaldos caen en el volumen | `ls /datos/respaldos` después de las 00:30 |

**La prueba que de verdad importa:** despliega una segunda vez y vuelve a
descargar un documento. Si sigue ahí, el volumen está bien montado. Si da 404,
`TEAV_DISCO_PERSISTENTE` no está apuntando al volumen.
