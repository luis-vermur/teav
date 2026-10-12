# Sistema de arrendamiento — COMERCIALIZADORA TEAV

Panel administrativo para operar una flota de arrendamiento puro de vehículos:
cotizaciones, contratos, calendario de cobranza, expedientes, seguros,
servicios y rentabilidad.

**Laravel 13 · Filament 5 · PostgreSQL · PHP 8.3**

---

## El modelo de negocio, en una página

Entender esto explica casi todas las decisiones del código.

La arrendadora **sólo pone el capital**. El cliente absorbe prácticamente todo
lo demás: seguro, placas y gestoría, mantenimientos, llantas y cualquier gasto
derivado del uso.

Consecuencias que están cableadas en el sistema:

- **El seguro y la gestoría no son egresos.** La arrendadora los desembolsa y
  los recobra: aparecen como cargos en el calendario del cliente, con concepto
  `seguro` y `gastos_iniciales`. Lo cobrado por ellos **tampoco es ingreso**;
  es reembolso, y se excluye del cálculo de rentabilidad.
- **Los mantenimientos son del cliente por defecto** (`responsable_costo =
  'arrendatario'`). Si la arrendadora adelanta el pago, se marca `se_recobra` y
  el sistema genera solo un cargo con concepto `recobro`.
- **El denominador de todos los rendimientos es `precio_adquisicion`** — lo que
  costó el coche. No la inversión con gastos, no el precio de lista.
- Como los costos ya no explican nada, lo que separa el rendimiento real del
  proyectado es otra cosa: **el auto parado, la cartera no cobrada y haber
  vendido por debajo de lo estimado**. Eso es lo que miden las métricas de
  riesgo.

### Las fórmulas

```
renta_total       = renta_con_iva × plazo_meses
rendimiento_renta = (renta_total / precio_adquisicion) − 1
ingreso_bruto     = renta_total + valor_venta_unidad
rendimiento_total = (ingreso_bruto / precio_adquisicion) − 1
rendimiento_anual = rendimiento_total / (plazo_meses / 12)
```

El flujo comercial va **al revés** de lo habitual: el usuario captura el
rendimiento que quiere y el sistema despeja la renta
(`CotizacionService::rentaParaRendimiento`). La renta se redondea **hacia
arriba** a la centena, para que el rendimiento real nunca quede por debajo del
pedido.

---

## Instalación

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate

# Base de datos (PostgreSQL)
createdb teav
php artisan migrate
php artisan db:seed          # roles, catálogos y usuario de prueba

php artisan serve
```

Entra a `http://127.0.0.1:8000` — la raíz redirige al panel.

### Configuración que importa

| Variable | Por qué |
|---|---|
| `APP_TIMEZONE=America/Mexico_City` | Los vencimientos y los días de mora dependen de esto. `config/app.php` ya la respeta. |
| `MAIL_MAILER` | En `log` no se envía nada: la recuperación de contraseña escribe el enlace en `storage/logs/laravel.log`. |
| `upload_max_filesize` ≥ 40M en `php.ini` | Los PDF escaneados de identificaciones pesan 20-30 MB. **Requiere reiniciar PHP.** |

> El idioma del panel es `es` (`app.locale`). El **formato de números** se fija
> aparte en `AppServiceProvider` con `defaultNumberLocale('es_MX')`, porque
> Filament sólo trae traducciones `es` y cambiar el locale dejaría el panel en
> inglés.

---

## Cómo se usa

El flujo completo, en orden:

1. **Cotizar** — se captura el rendimiento objetivo y el sistema calcula la renta.
2. **Aceptar** la cotización y pulsar **Convertir a contrato**. El contrato hereda
   renta, plazo, seguro, gastos y depósito.
3. **Activar el contrato**. Esto es lo que **genera el calendario de pagos**; un
   borrador no genera obligaciones. Requiere que el cliente esté `activo`, sin
   adeudos y con expediente completo — si algo falta, el botón lo dice.
4. **Registrar pagos** y aplicarlos con **Aplicar al contrato**. Marcar
   "aplicado" a mano no reparte el dinero: por eso ese estatus no se puede
   elegir desde el formulario.

---

## Tareas programadas

Requieren un cron que llame a `php artisan schedule:run` cada minuto.

| Hora | Comando | Qué hace |
|---|---|---|
| 00:30 | `teav:respaldar` | Respaldo comprimido de la base (conserva 14) |
| 01:00 | `teav:cobranza` | Marca vencidos y genera recargos por mora |
| 01:15 | `teav:estatus` | Actualiza estatus de pólizas, contratos y cotizaciones |
| 01:30 | `teav:rentabilidad` | Recalcula la rentabilidad real de la flota |

**Los respaldos van a `storage/app/respaldos`, en el mismo disco que la base.**
Eso resuelve "no hay respaldo", no "está a salvo": hay que copiarlos fuera del
servidor.

---

## Roles

Definidos en `RolesPermisosSeeder`. El más usado es **operador**: hace toda la
operación diaria pero no ve Configuración ni cifras del negocio.

La distinción que conviene entender: **esconder del menú no es quitar el
permiso.** El operador tiene `crear_catalogo` pero no `ver_catalogo`, así que no
navega a Marcas pero sí puede crear una al vuelo desde el `+` de un formulario.

> **Cuidado con las policies.** Filament trata su ausencia de forma opuesta según
> el contexto: en un **Resource**, sin policy *permite a cualquiera*; en un
> **relation manager**, sin policy *deniega* y los botones desaparecen sin
> explicación. Todo modelo que aparezca en la interfaz necesita la suya.

---

## Pruebas

```bash
php artisan test
```

Corren contra PostgreSQL (`teav_testing`), **no SQLite**: las migraciones usan
sintaxis propia de Postgres. Crea la base antes con `createdb teav_testing`.

Las pruebas cubren lo que más cuesta si se rompe: el despeje de la renta, el
prorrateo del primer mes, las reglas de unicidad de los catálogos y que
ningún campo de dinero acepte cantidades negativas.

---

## Estructura

```
app/
├── Services/          Lógica de negocio. Empieza a leer aquí.
│   ├── ContratoService        activación, calendario, prorrateo, cierre
│   ├── CotizacionService      despeje de renta, conversión a contrato
│   ├── PagoService            aplicación y reverso de pagos
│   ├── MantenimientoService   recobro de servicios adelantados
│   └── RentabilidadService    rendimiento real y métricas de riesgo
├── Filament/          Panel: recursos, widgets, acciones
├── Models/            27 modelos
└── Policies/          Una por modelo visible en la interfaz
```
