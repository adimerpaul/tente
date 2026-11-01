# Tenté · Sabor y Sazón

Sistema de ventas para Tenté, comida rápida: punto de venta con el menú en fotos,
pantalla de cocina, clientes, reportes y usuarios con roles (administrador, vendedor, cocinero).

## Backend Laravel

```bash
cd back
composer install
php artisan migrate      # base de datos MySQL "tente"
php artisan serve
```

Las migraciones crean el menú inicial (10 platos con foto) y tres usuarios:

| Usuario    | Contraseña | Rol           |
|------------|------------|---------------|
| `admin`    | `admin`    | Administrador |
| `vendedor` | `vendedor` | Vendedor      |
| `cocinero` | `cocinero` | Cocinero      |

## Frontend Quasar

```bash
cd front
npm install
npm run dev
```

La URL de la API se configura en `front/.env.development` y `front/.env.production` (`VITE_API_BACK`).
