# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Qué es

Tenté ("Sabor y Sazón"): sistema de ventas para un local de comida rápida (Bolivia, moneda Bs). Sólo venta: no hay inventario, compras ni stock — los platos se preparan al momento. Nació de una copia del proyecto Bean (ventas e inventario), del que se quitó todo lo de inventario. Tres aplicaciones en un solo repo:

- `back/` — API REST Laravel 13 (PHP 8.3), Sanctum + spatie/laravel-permission.
- `front/` — SPA/PWA Quasar 2 + Vue 3 (Vite), que consume la API por axios.
- `aplicacion/` — app Android en Flutter (paquete Dart `tente`, id `bo.tente.ventas`) para vendedores. Sigue la estructura heredada de Bean; ver `aplicacion/README.md`.

La UI, los nombres de tablas/columnas y los mensajes de error están en español; el código PHP/JS (variables, métodos) está en inglés. Mantener esa mezcla.

Colores de marca: naranja `#E9682F` (primary), rojo `#9F2321` (secondary), amarillo `#F6A204` (accent). Logos en `front/public/` (`logo-tente.png` para fondo claro, `logo-tente-blanco.png` para fondo rojo, `tente-icon.png`) y `back/public/images/empresa/logo_tente.webp` (logo por defecto de la configuración).

## Comandos

```bash
# Backend
cd back
composer install
php artisan migrate         # crea esquema + datos iniciales (ver "Datos iniciales")
php artisan serve           # http://localhost:8000
composer run test           # config:clear + artisan test
vendor/bin/pint             # formateo (Laravel Pint)

# Frontend
cd front
npm install
npm run dev                 # quasar dev
npm run build               # quasar build → front/dist

# App móvil (Flutter, sólo Android)
cd aplicacion
flutter pub get
flutter run
flutter test
flutter analyze
dart run flutter_launcher_icons   # regenerar el icono desde assets/icono/logo.png
```

No hay tests en el frontend. Los tests del backend (`tests/Feature/TenteTest.php`) corren sobre SQLite en memoria con `RefreshDatabase`, por lo que **las migraciones se ejecutan completas en cada test** — incluidas las que insertan datos. Una migración que rompa en SQLite rompe toda la suite.

## Configuración de entorno

- Backend: `back/.env`, MySQL, base de datos `tente`.
- Frontend: `front/.env.development` y `front/.env.production` definen `VITE_API_BACK` y `VITE_VERSION`. El router usa modo `history` (rutas sin `#`).
- App móvil: `aplicacion/.env.development` y `aplicacion/.env.production` definen `API_URL` y `APP_VERSION`; van declarados como `assets` en `pubspec.yaml`.

## Datos iniciales: viven en migraciones, no en seeders

`DatabaseSeeder` está vacío a propósito.

- `2026_10_27_010000_create_configuraciones_table.php` — empresa "Tenté", eslogan y logo por defecto.
- `2026_10_27_020000_create_clientes_table.php` — cliente genérico id 1 `SIN NOMBRE` (`Cliente::SIN_NOMBRE`), no editable ni borrable.
- `2026_10_27_050000_load_roles_permissions_and_users.php` — permisos (con `grupo`/`orden`), roles `Administrador` (todo), `Vendedor`, `Cocinero` y los usuarios `admin`/`vendedor`/`cocinero` (contraseña = usuario).
- `2026_10_27_060000_load_tente_menu.php` — 10 platos `TEN-001…TEN-010`. Los platos con dos precios (salchipapa, pique) son dos productos: CHICA/GRANDE. Fotos versionadas en `back/public/images/productos/*.webp` (recortadas de la imagen del menú).

## Autorización: roles + permisos

Se usan **roles de spatie** con permisos (`role_has_permissions`); un usuario tiene un rol y, opcionalmente, permisos extra directos (`model_has_permissions`). Los permisos efectivos son la unión. No se usa middleware `can:`: cada controlador tiene un método privado `authorizeAction`/`requirePermission` que hace `hasAnyPermission(...)` **al inicio de cada acción** (con una lista, basta cualquiera). Los permisos son strings en español ("Ver Ventas").

`/login` y `/me` devuelven el usuario con `rol` y `permissions` = **todos** los permisos efectivos (`getAllPermissions()`); el frontend y la app leen `permissions[].name`. En el frontend: `proxy.$store.hasPermission('Ver Ventas')`; el menú está en `front/src/layouts/MainLayout.vue` (`links[].can`).

- Pantalla `roles/IndexPage.vue` (`Gestionar Roles`): edita los permisos de cada rol. El rol `Administrador` no se renombra ni borra y siempre conserva todos los permisos; un rol con usuarios no se borra.
- `usuarios/IndexPage.vue`: elige el rol y marca permisos extra; los del rol se muestran bloqueados. `PUT /users/{id}/permissions` reemplaza sólo los **extra**.
- **Todo permiso nuevo debe fijar `grupo` y `orden`** en su migración y asignarse al rol `Administrador` (`Role::findByName('Administrador')->givePermissionTo(...)`).

## Ventas

- `VentaController@store`: el precio **siempre** es el del producto (no se envía), cantidades enteras, el producto debe estar `disponible`. `tipo_pago` ∈ `EFECTIVO | QR | COMBINADO` con `monto_efectivo + monto_qr == total`. El descuento de cabecera se prorratea por línea.
- **Snapshots**: la venta guarda `usuario_nombre`, `cliente_nombre`, `cliente_documento`; el detalle guarda `codigo`, `nombre`, `categoria`, `foto`, `precio_venta`. Reportes y dashboard se calculan sobre esos snapshots, nunca con joins a `users`/`productos`/`clientes`.
- En `ventas/NuevaPage.vue` el cliente es un único `q-select` que busca por nombre mientras se escribe. Sin `cliente_id`, el backend busca un cliente con el `cliente_nombre` escrito y, si no existe, lo registra (sólo el nombre). Sin nombre se usa el genérico `SIN NOMBRE`.
- `ticket` = número de pedido del día (se reinicia cada día); `numero` = `V-00000001` a partir del id, generado después del insert.
- **Todo es para llevar (cajitas)**: `store` guarda siempre `tipo_consumo = LLEVAR` (ignora lo enviado; `MESA` queda sólo por compatibilidad) y la caja no muestra selector. Los chips de categoría usan `src/addons/categoriaIcon.js` (icono por palabra clave). Cada línea puede llevar `observacion` ("SIN CEBOLLA").
- `estado` ∈ `COMPLETADA | ANULADA` (lo económico; reportes y dashboard filtran por `COMPLETADA`). Anular no toca nada más (no hay stock).

## Ingresos y gastos de caja

Una sola tabla `movimientos` (modelo `Movimiento`, `MovimientoController`) para ingresos **y** egresos que no son ventas del menú: `tipo` ∈ `INGRESO | EGRESO` (venta de aceite usado / compra de periódicos), `concepto`, `categoria` libre (sugerencias en `Movimiento::CATEGORIAS` por tipo), `monto`, `tipo_pago` ∈ `EFECTIVO | QR | COMBINADO` con `monto_efectivo + monto_qr == monto` (el efectivo en caja suma `monto_efectivo`), `estado` ∈ `REGISTRADO | ANULADO`, snapshot `usuario_nombre`; `numero` = `I-…`/`E-…` a partir del id. No hay tabla de gastos aparte. Permisos (grupo `Caja`): `Ver Movimientos`, `Registrar Movimientos` (también Vendedor), `Anular Movimientos`. Rutas: `GET/POST /movimientos`, `GET /movimientos-catalogos`, `PUT /movimientos/{id}/anular`. Se registran desde `ventas/IndexPage.vue` (botones Ingreso / Gasto + pestaña "Ingresos y gastos").

Cierre: `neto = ventas + ingresos − egresos`, `caja_efectivo = efectivo de ventas + ingresos en efectivo − egresos en efectivo`; sólo cuenta lo `REGISTRADO`. Lo devuelven `/ventas-resumen` y `/reportes` (`MovimientoController::totales`), y se exporta: Excel con hojas "Resumen" (cierre) e "Ingresos y gastos", y columnas ingresos/egresos/neto en "Por día"; el PDF incluye los mismos totales y el detalle.

## Cocina

`estado_cocina`: `PENDIENTE → PREPARANDO → LISTO → ENTREGADO`. `GET /cocina` (`Ver Cocina`) devuelve los pedidos de hoy no entregados + los últimos 10 entregados; `PUT /ventas/{id}/cocina` (`Actualizar Pedidos`) cambia el estado. La página `cocina/IndexPage.vue` refresca cada 8 s y pita (WebAudio) cuando entra un pedido nuevo. **Tiempo real**: se usa el servidor socket compartido por todos los proyectos (`socket/` es sólo copia de referencia: **no se modifica**). El backend publica por su canal genérico `POST {SOCKET_URL}/notify {event:'tente-cocina', data}` (`App\Support\CocinaSocket`, con `defer`, sin romper si el socket falla) al crear una venta (`NUEVO`), anularla (`ANULADO`) y en cada cambio de `estado_cocina`; `data.mensaje` trae el texto a leer. El front (`src/addons/cocinaSocket.js`, `VITE_API_SOCKET`) escucha en `MainLayout.vue`: muestra `proxy.$alert.kitchen` en cualquier página y **sólo en `/cocina`** lo lee con Web Speech API (voz elegida en el diálogo del header, guardada en localStorage `vozTente`). Quien tiene `Ver Cocina` oye NUEVO/ANULADO/PREPARANDO/LISTO; la caja (`Crear Ventas`) sólo PREPARANDO/LISTO. La cocina recarga con cada aviso y sólo sondea si no hay socket. En tests `SOCKET_URL` va vacío (`phpunit.xml`). Al cobrar, `ventas/NuevaPage.vue` ofrece imprimir el ticket del cliente y la comanda de cocina (`src/addons/ventaPrint.js`: `printSale`, `printKitchen`).

## Reportes

`ReporteController`: `GET /reportes?desde&hasta&user_id` (por defecto hoy) con resumen, por producto, categoría, vendedor, día, hora, pago, consumo y mejores clientes; `GET /reportes-exportar/excel` (8 hojas, `App\Exports\VentasReporteExport` + `Comun\HojaTabla`) y `/pdf` (`resources/views/ventas/reporte.blade.php`). Permiso `Ver Reportes`. El panel de inicio (`/dashboard`) usa `Ver Estadísticas`; quien no lo tiene ve accesos rápidos a Nueva venta / Cocina.

## Auditoría y soft deletes

`Producto`, `Venta`, `VentaDetalle`, `Cliente`, `User` usan `owen-it/laravel-auditing` + `SoftDeletes`. Las consultas con `DB::table(...)` deben añadir `whereNull('deleted_at')` explícitamente.

## Imágenes

Archivos en `back/public/images/**` (no en `storage/`), convertidos a WebP con GD. La columna guarda la ruta relativa (`productos/x.webp`). Al cambiar la foto de un producto sólo se borran archivos `producto_*.webp` subidos desde el sistema; las fotos del menú inicial pueden estar compartidas (chico/grande). El frontend arma la URL con `proxy.$imgBase + '/images/' + ruta`.

## Frontend: convenciones

- `<script setup>` + `const { proxy } = getCurrentInstance()` y luego `proxy.$axios`, `proxy.$alert`, `proxy.$store`, `proxy.$imgBase` (registrados en `front/src/boot/axios.js`). No importar axios directamente.
- Un único store Pinia en `src/stores/example-store.js` con sesión y los 5 pedidos abiertos (`carts`) de la caja.
- Sesión en localStorage: `tokenTente`, `userTente`, `permissionsTente`; empresa en `empresaTente`. Sólo un 401 cierra la sesión.
- Notificaciones y diálogos siempre con `proxy.$alert` (`src/addons/Alert.js`; `choose` para casillas).
- Directiva `v-uppercase` para inputs en mayúsculas.
- El JS de las páginas está escrito denso (varias declaraciones por línea); al editar una página respetar esa densidad.
- Errores de API: primer mensaje de `e.response?.data?.errors`, si no `e.response?.data?.message`.

## Rutas API

Todas en `back/routes/api.php`, planas. Públicas: `POST /api/login` y `GET /api/configuracion`. El resto bajo `auth:sanctum`.
