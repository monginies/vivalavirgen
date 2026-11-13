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
| `ArticlePhoto` | `article_photos` | varias fotos por artículo; `position` 0 = principal (`Article::mainPhoto`, la de los listados). Se guardan en `storage/app/public/articles/{id}/` en dos tamaños JPEG (1600 px y miniatura 480 px, `App\Support\ImageResizer`); en la ficha se reducen antes en el navegador y se suben de una en una |
| `article_components` | — | piezas de un **producto combinado**: `bundle_id`, `component_id`, `quantity` (piezas por combinado). Relaciones `Article::components()` y `Article::bundles()` |
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

**Productos combinados** (`articles.is_bundle`): artículo formado por varios artículos sueltos, con precio propio (no la suma de las piezas). No tiene unidades ni QR. En `Availability` un combinado en un pedido se desglosa en sus piezas (`Availability::expand`), así que esas piezas dejan de estar disponibles sueltas en esas fechas; la disponibilidad de un combinado es cuántos se pueden montar con las piezas libres. `Availability::shortages()` valida un pedido sumando lo que piden las líneas sueltas y los combinados (lo usan el backoffice y la web). Un combinado sin piezas no se puede reservar. No se admiten combinados dentro de combinados, un artículo con unidades no puede pasar a combinado y no se puede borrar un artículo que es pieza de un combinado. El cliente no puede cambiar piezas: si quiere otra cosa, pide sueltos.

**Alquiler exclusivo** (`articles.exclusive` + tabla `article_client`): solo los clientes autorizados pueden alquilar el artículo. Para el resto (y para visitantes sin cuenta) aparece en la web sin disponibilidad y sin botón de añadir, como si ya estuviera alquilado. Un combinado queda bloqueado si es exclusivo o si lo es alguna de sus piezas. `Availability::blockedFor($clientId, $ids)` y `Availability::exclusiveErrors()`; el backoffice no deja guardar un pedido con material exclusivo para un cliente no autorizado. La página de Disponibilidad del backoffice no tiene en cuenta la exclusividad.

**Hoja de expedición** (`App\Support\Expedition`, vista `pdf/expedition`, ruta `orders.expedition`): PDF para logística, sin precios, disponible cuando el pedido está pagado (o entregado, devuelto, cerrado). Lleva horas de entrega/recogida, lugar, contacto en el lugar, notas para logística (`orders.delivery_time`, `pickup_time`, `site_contact_name`, `site_contact_phone`, `logistics_notes`, editables en la tarjeta «Expedición» del pedido hasta cerrarlo), el material con miniatura, los combinados desglosados en piezas, casillas de carga/entrega/vuelta y recuadros de firma.

**Salida y entrada con QR** (`ScanController`, vistas `scan/*`, tabla `order_units`): página para móvil que escanea los QR de las unidades con la cámara (`BarcodeDetector` si el navegador lo tiene; si no, `public/js/jsQR.js`, Apache-2.0) o con el código escrito a mano. Salida (pedido pagado): cada unidad pasa a «Alquilada»; avisa de lo que falta, sobra o no está en el pedido, sin bloquear; al terminar, el pedido pasa a «Entregado». Entrada (pedido entregado): devuelta → «Disponible», rota → «En reparación», perdida → «De baja»; al terminar, el pedido pasa a «Devuelto». Una unidad fuera con otro pedido no puede salir. Al cancelar un pedido, las unidades escaneadas vuelven a «Disponible». En el servidor, `public_html/js` es un enlace a `vlv-app/public/js`.

**App instalable (PWA) y avisos push** (`PwaController`, `pwa/*`, `PushController`, `App\Support\Push`): `/manifest.webmanifest` (clientes) y `/app/manifest.webmanifest` (backoffice, «VLV Gestión»), service worker en `/sw.js` (página `/offline` y notificaciones). Iconos `public/img/icon-*.png`. Avisos push con `minishlink/web-push` y claves VAPID en `.env` (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`; se generan con `php artisan push:keys`). Cada usuario de oficina los activa por dispositivo desde Inicio (tabla `push_subscriptions`). Se avisa de los pedidos nuevos de la web (al enviarlos) y de las pre-reservas que caducan en menos de 24 h (`orders:expiry-alerts`, cada 15 min, una vez por pedido: `orders.expiry_alerted_at`; se reinicia al ampliar o reactivar). En iPhone solo funcionan si la app se ha añadido a la pantalla de inicio.

**Km del transporte:** botón «Calcular km» en el pedido (`app/Services/Distance.php`): geocodifica el destino y calcula la ruta en coche desde la dirección de la base (Ajustes) con OpenRouteService. La clave va en `.env` como `ORS_API_KEY`; sin ella el botón no aparece y los km se escriben a mano. Si el pedido tiene **código postal**, el destino se sitúa con la tabla `postal_codes` (GeoNames, toda España; se carga con `php artisan postal-codes:import`) y la dirección se busca a menos de 10 km de ese CP; si no aparece, se usa el centro del CP. Sin CP, la búsqueda se limita a 300 km de la base. Resultados en caché 30 días.

**Fin de semana:** si la fecha del evento es viernes, sábado o domingo, el pedido reserva como mínimo de viernes a domingo (`Order::weekendBlock`, aplicado al guardar y en el formulario). Las fechas más amplias se respetan.

**Importes:** el precio de alquiler es por evento. Transporte = 2 trayectos × km × €/km × camiones, con mínimo (`Order::calculateTransport`); se puede fijar a mano. La fianza va aparte, sin IVA.

Las referencias y códigos se calculan contando también los registros borrados (`withTrashed`), así que nunca se reutilizan. `Article::addUnits()` crea las unidades dentro de una transacción con bloqueo.

## Dos zonas en la misma app

- **Web de clientes** en la raíz (`vivalavirgen.net`): catálogo, carrito («Mi pedido»), registro, acceso y «Mi cuenta». Rutas con nombre `portal.*`, controladores en `app/Http/Controllers/Portal`, vistas en `resources/views/portal` con el layout `layouts/portal`.
  - Los clientes entran con el guard **`client`** (modelo `Client`, contraseña en `clients.password`), separado del de los usuarios del backoffice (`web`).
  - Alta libre con confirmación de email; **no pueden ver precios ni enviar pedidos hasta que el equipo los aprueba** (`clients.approved_at`) desde la ficha del cliente.
  - Descuento según el **tipo de cliente** (`client_categories`), editable en Administración → Tipos de cliente.
  - El carrito vive en la sesión (`App\Support\Cart`). Al solicitarlo se crea un `Order` con `source = web`, estado Solicitado y pre-reserva; se avisa por email al cliente y a `notify_email` (Ajustes).
- **Backoffice** en `/app` (`Route::prefix(app)`). Los QR impresos apuntan a `/app/q/{token}`: no cambiar.
- En el servidor, `public_html/index.php` carga `vlv-app/public/index.php`, y `css`, `img`, `fonts` y `storage` de `public_html` son enlaces a `vlv-app/public`.
- Correo saliente: SMTP con `gestion@vivalavirgen.net` (credenciales en `.env`). Textos de los emails en `app/Notifications`.

## Rutas principales (`routes/web.php`)

- Backoffice: `/app/login`, `/app/logout`, `/app` panel
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

- `users.role`: `office` (todo el backoffice) o `logistics` (solo expediciones). El middleware `RestrictLogistics` deja a logística únicamente las rutas de `RestrictLogistics::ALLOWED`: lista y ficha de expediciones (`ExpeditionController`, sin precios), hojas PDF, escaneo de salida y entrada, y `q/{token}` (que les redirige a sus expediciones). Un usuario de logística nunca es superusuario.

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
