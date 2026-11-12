# Viva la Virgen — App de inventario

Guía para quien trabaje en este código, sea una persona o un asistente de IA (Claude, Codex…).

## Qué es

App interna de **Viva la Virgen** para gestionar el material que alquila para eventos y catering. Cada artículo del catálogo tiene **unidades físicas** individuales, cada una con su etiqueta QR.

- Producción: https://vivalavirgen.net/app
- Repositorio: `github.com/monginies/vivalavirgen` (privado, rama `main`)
- Idioma de la interfaz: **español** (`APP_LOCALE=es`, mensajes de validación en `lang/es/`)

## Stack

- Laravel 13, PHP 8.4, MariaDB 10.11
- Vistas Blade sin framework JS. El CSS es **CSS propio escrito a mano** en `public/css/app.css` (variables en `:root`). No hay que compilar nada: Vite y Tailwind están en `package.json` porque vienen con el esqueleto de Laravel, pero no se usan.
- QR: `chillerlan/php-qrcode` (SVG)
- Sesiones, caché y colas en base de datos. El correo está en modo `log` (no envía emails).

## Modelo de datos

| Modelo | Tabla | Notas |
|---|---|---|
| `Category` | `categories` | `code` = prefijo de referencia (p. ej. `SIL`), único |
| `Article` | `articles` | `reference` automática `SIL-0001` (`Article::nextReference`); `rental_price` sin IVA; `replacement_price` = coste de reposición por unidad; foto en el disco `public`; soft deletes |
| `Unit` | `units` | `code` legible `SIL-0001-003`; `qr_token` UUID; `status` = enum `UnitStatus`; soft deletes |
| `Client` | `clients` | `type` = `company` / `individual`; `tax_id` = NIF/CIF; soft deletes |
| `User` | `users` | `is_superadmin`, `active` (un usuario inactivo no puede entrar) |

`UnitStatus` (`app/Enums/UnitStatus.php`): `available` Disponible · `rented` Alquilada · `maintenance` En reparación · `retired` De baja. Cada caso tiene `label()` y `color()` para la interfaz.

Las referencias y códigos se calculan contando también los registros borrados (`withTrashed`), así que nunca se reutilizan. `Article::addUnits()` crea las unidades dentro de una transacción con bloqueo.

## Rutas principales (`routes/web.php`)

- `/login`, `/logout`
- `/` panel
- `categories`, `articles`, `clients`: recursos CRUD
- `articles/{article}/units` (POST) añade unidades; `articles/{article}/labels` muestra las etiquetas QR para imprimir
- `q/{token}`: **URL corta impresa en cada etiqueta**; redirige a la ficha de la unidad. No cambiar su formato, porque invalidaría las etiquetas ya impresas.
- `units/{unit}` ver, cambiar estado o borrar; `units/{unit}/qr.svg`
- `users`: solo superadmin (middleware `EnsureSuperadmin`)

Todo, menos el login, exige sesión iniciada.

## Usuarios

- Solo el superadmin gestiona usuarios desde la app.
- Para crear o ascender un superadmin por consola: `php artisan app:superuser --name=... --email=...` (pide la contraseña de forma interactiva).

## Servidor

- `server.mongini.net` (AlmaLinux 8, WHM/cPanel), SSH por el puerto **46422**, cuenta `vivalavirgen`
- Código en `/home/vivalavirgen/vlv-app`; `public_html/app` es un enlace a `vlv-app/public`
- Base de datos `vivalavirgen_app` (usuario `vivalavirgen_app`); credenciales solo en `.env`, que no se versiona
- **PHP:** con la cuenta `vivalavirgen`, `php` es la 8.4 (`~/bin/php`). Si se entra como root con `su`, el PHP por defecto es la 8.2 y Composer falla: usar `/opt/cpanel/ea-php84/root/usr/bin/php`.
- El servidor sube a GitHub con una deploy key propia (`~/.ssh/github_vlv`, configurada en `core.sshCommand`)

## Cómo trabajar

- Se trabaja directamente en el servidor, sobre `main`. Tras cada cambio: commit con un mensaje en español que explique el porqué, y `git push`.
- Migraciones nuevas: `php artisan migrate --force`. No editar migraciones ya ejecutadas en producción; crear una nueva.
- Después de tocar configuración o rutas: `php artisan optimize:clear`.
- Si se cambia `public/css/app.css`, subir el `?v=` del enlace en `resources/views/layouts/app.blade.php` para que los navegadores no usen la versión antigua en caché.
- Nunca subir `.env`, `vendor/`, logs ni `error_log`.
- Estilo de código: Laravel Pint (`vendor/bin/pint`).
- Los textos visibles para el usuario, en español.

## Pendiente de decidir

Las decisiones de producto (pedidos, pre-reservas, transporte, descuentos por tipo de cliente, facturación, fianzas, doble validación) se registran en el proyecto de Claude «VIVA LA VIRGEN» (`decisiones.md`). Conviene consultarlas antes de construir esas partes.
