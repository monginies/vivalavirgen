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
- Identidad visual a partir del logo: verde salvia `#9a9b88`, crema `#fcfbf6`, botones en salvia oscuro `#5d604e`; títulos en Noto Serif (servida desde `public/fonts`, sin Google Fonts). Logo y favicon en `public/img`.
- QR: `chillerlan/php-qrcode` (SVG)
- Sesiones, caché y colas en base de datos. El correo está en modo `log` (no envía emails).

## Modelo de datos

| Modelo | Tabla | Notas |
|---|---|---|
| `Category` | `categories` | `code` = prefijo de referencia (p. ej. `SIL`), único |
| `Article` | `articles` | `reference` automática `SIL-0001` (`Article::nextReference`); `rental_price` sin IVA; `replacement_price` = coste de reposición por unidad; fotos en `article_photos`; soft deletes |
| `ArticlePhoto` | `article_photos` | varias fotos por artículo; `position` 0 = principal (`Article::mainPhoto`, la de los listados). Se guardan en `storage/app/public/articles/{id}/` en dos tamaños JPEG (1600 px y miniatura 480 px, `App\\Support\\ImageResizer`); en la ficha se reducen antes en el navegador y se suben de una en una |
| `Unit` | `units` | `code` legible `SIL-0001-003`; `qr_token` UUID; `status` = enum `UnitStatus`; soft deletes |
| `Client` | `clients` | `type` = `company` / `individual`; `tax_id` = NIF/CIF; soft deletes |
| `User` | `users` | `is_superadmin`, `active` (un usuario inactivo no puede entrar) |
| `Order` | `orders` | `number` `PED-2026-0001`; `status` = enum `OrderStatus`; fechas `start_date` (entrega) y `end_date` (recogida); destino, km, camiones, transporte, descuento, fianza, IVA; `expires_at` = fin de la pre-reserva |
| `OrderLine` | `order_lines` | artículo, cantidad y precio por unidad sin IVA copiado al crear la línea |
| `Venue` | `venues` | fincas y lugares de celebración habituales (dirección, CP, población, km desde la base, contacto, notas); al elegir una en el pedido se copian sus datos al pedido (`orders.venue_id` + campos de destino) |
| `Setting` | `settings` | ajustes clave/valor editables por el superadmin; valores por defecto en `Setting::DEFINITIONS` |

`UnitStatus` (`app/Enums/UnitStatus.php`): `available` Disponible · `rented` Alquilada · `maintenance` En reparación · `retired` De baja. Cada caso tiene `label()` y `color()` para la interfaz.

`OrderStatus` (`app/Enums/OrderStatus.php`): Solicitado → Aceptado → Proforma enviada → Pagado → Entregado → Devuelto → Cerrado, más Cancelado y Caducado. Los tres primeros son pre-reserva y caducan en `expires_at` si no se pagan.

**Disponibilidad** (`app/Services/Availability.php`): capacidad = unidades que no están de baja ni en reparación; reservado = pico diario de las cantidades de los pedidos que bloquean esas fechas. Un pedido entregado o devuelto bloquea hasta que se cierra. Las pre-reservas vencidas no cuentan aunque aún no se hayan marcado como caducadas. Al guardar un pedido se comprueba que haya unidades suficientes (`OrderController::assertAvailable`).

**Km del transporte:** botón «Calcular km» en el pedido (`app/Services/Distance.php`): geocodifica el destino y calcula la ruta en coche desde la dirección de la base (Ajustes) con OpenRouteService. La clave va en `.env` como `ORS_API_KEY`; sin ella el botón no aparece y los km se escriben a mano. Si el pedido tiene **código postal**, el destino se sitúa con la tabla `postal_codes` (GeoNames, toda España; se carga con `php artisan postal-codes:import`) y la dirección se busca a menos de 10 km de ese CP; si no aparece, se usa el centro del CP. Sin CP, la búsqueda se limita a 300 km de la base. Resultados en caché 30 días.

**Fin de semana:** si la fecha del evento es viernes, sábado o domingo, el pedido reserva como mínimo de viernes a domingo (`Order::weekendBlock`, aplicado al guardar y en el formulario). Las fechas más amplias se respetan.

**Importes:** el precio de alquiler es por evento. Transporte = 2 trayectos × km × €/km × camiones, con mínimo (`Order::calculateTransport`); se puede fijar a mano. La fianza va aparte, sin IVA.

Las referencias y códigos se calculan contando también los registros borrados (`withTrashed`), así que nunca se reutilizan. `Article::addUnits()` crea las unidades dentro de una transacción con bloqueo.

## Rutas principales (`routes/web.php`)

- `/login`, `/logout`
- `/` panel
- `categories`, `articles`, `clients`: recursos CRUD
- `articles/{article}/units` (POST) añade unidades; `articles/{article}/labels` muestra las etiquetas QR para imprimir
- `q/{token}`: **URL corta impresa en cada etiqueta**; redirige a la ficha de la unidad. No cambiar su formato, porque invalidaría las etiquetas ya impresas.
- `units/{unit}` ver, cambiar estado o borrar; `units/{unit}/qr.svg`
- `orders`: CRUD sin borrado; `orders/{order}/status` cambia de estado (avanzar, cancelar, reactivar); `orders/{order}/extend` amplía la pre-reserva
- `venues`: fincas habituales (CRUD sin ficha); `orders/create?venue=ID` abre un pedido con esa finca
- `calendar` (vista mensual) y `availability` (libres entre dos fechas)
- `users` y `settings`: solo superadmin (middleware `EnsureSuperadmin`)

Todo, menos el login, exige sesión iniciada.

## Usuarios

- Solo el superadmin gestiona usuarios desde la app.
- Para crear o ascender un superadmin por consola: `php artisan app:superuser --name=... --email=...` (pide la contraseña de forma interactiva).

## Servidor

- `server.mongini.net` (AlmaLinux 8, WHM/cPanel), SSH por el puerto **46422**, cuenta `vivalavirgen`
- Código en `/home/vivalavirgen/vlv-app`; `public_html/app` es un enlace a `vlv-app/public`
- Base de datos `vivalavirgen_app` (usuario `vivalavirgen_app`); credenciales solo en `.env`, que no se versiona
- **PHP:** con la cuenta `vivalavirgen`, `php` es la 8.4 (`~/bin/php`). Si se entra como root con `su`, el PHP por defecto es la 8.2 y Composer falla: usar `/opt/cpanel/ea-php84/root/usr/bin/php`.
- Cron de la cuenta: `schedule:run` cada minuto; `orders:expire` se ejecuta cada 15 minutos (`routes/console.php`)
- Copias de la base de datos antes de cada despliegue en `~/backups`
- El servidor sube a GitHub con una deploy key propia (`~/.ssh/github_vlv`, configurada en `core.sshCommand`)

## Cómo trabajar

- Cambios pequeños: directamente en `~/vlv-app`, sobre `main`. Tras cada cambio: commit con un mensaje en español que explique el porqué, y `git push`.
- Cambios grandes: en la copia de desarrollo `~/vlv-dev` (git worktree en otra rama, con su propio `vendor/` y base de datos SQLite en `database/dev.sqlite`, nunca la de producción). Probar con `php artisan test` y luego, en `~/vlv-app`: copia de la base de datos, `git merge --ff-only <rama>`, `php artisan migrate --force`, `php artisan optimize:clear` y `git push`.
- Migraciones nuevas: `php artisan migrate --force`. No editar migraciones ya ejecutadas en producción; crear una nueva.
- Después de tocar configuración o rutas: `php artisan optimize:clear`.
- Si se cambia `public/css/app.css`, subir el `?v=` del enlace en `resources/views/layouts/app.blade.php` para que los navegadores no usen la versión antigua en caché.
- Nunca subir `.env`, `vendor/`, logs ni `error_log`.
- Estilo de código: Laravel Pint (`vendor/bin/pint`).
- Los textos visibles para el usuario, en español.

## Pendiente de decidir

Las decisiones de producto (pedidos, pre-reservas, transporte, descuentos por tipo de cliente, facturación, fianzas, doble validación) se registran en el proyecto de Claude «VIVA LA VIRGEN» (`decisiones.md`). Conviene consultarlas antes de construir esas partes.
