# Entorno de pruebas reproducible

Fecha de validacion: 2026-08-02

## Decision

La suite usa MySQL/MariaDB, igual que la aplicacion. SQLite no es la ruta
principal porque las migraciones y varios flujos dependen del comportamiento
real de MySQL.

La base de pruebas debe ser exclusiva. Nunca se debe usar `erp_crm` ni una base
de produccion. El nombre recomendado es `erp_crm_testing`.

## Preparacion

1. Copiar `.env.testing.example` como `.env.testing`.
2. Ajustar usuario y clave de MySQL sin cambiar el nombre de la base.
3. Crear la base vacia:

```sql
CREATE DATABASE erp_crm_testing
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Ejemplo con XAMPP en Windows:

```powershell
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root -e "CREATE DATABASE IF NOT EXISTS erp_crm_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Si el usuario no es `root`, se recomienda otorgarle permisos solo sobre
`erp_crm_testing`.

## Ejecucion

```bash
php artisan test
```

Todos los tests feature usan `RefreshDatabase`. En la primera prueba Laravel
ejecuta `migrate:fresh`; las siguientes pruebas usan transacciones. Por tanto:

- el esquema se prepara automaticamente;
- cada prueba queda aislada;
- no se requieren seeders ni datos previos;
- todos los datos dentro de `erp_crm_testing` pueden ser eliminados.

La inicializacion de la suite contiene una barrera adicional: rechaza cualquier
base cuyo nombre no termine en `_testing` (salvo SQLite `:memory:`). La
validacion ocurre antes de que `RefreshDatabase` pueda limpiar tablas.

## Validacion realizada

Se elimino y recreo `erp_crm_testing` vacia y luego se ejecuto solamente:

```bash
php artisan test
```

La cantidad exacta de pruebas cambia con cada fase. El resultado autoritativo es
el job `php-tests` del commit, que ejecuta la suite completa sobre MySQL 8 desde
una base vacia.

## Pruebas de navegador

El job `browser-smoke` instala Chromium administrado por Playwright, compila los
assets, recrea la base de pruebas y ejecuta el flujo de autenticacion en un
servidor Laravel aislado. En CI se usa `SESSION_DRIVER=file` para conservar la
sesion entre solicitudes del navegador.

Ejecucion local del mismo alcance:

```bash
npx playwright install chromium
npx playwright test tests/e2e/01-auth.spec.ts --project=chrome-desktop
```

`E2E_CHROME_PATH` es opcional. Si no se define, CI usa el Chromium descargado;
en Windows el proyecto puede seguir usando el canal Chrome instalado.

## Diagnostico de fallos comunes

- `Connection refused`: MySQL no esta iniciado o host/puerto son incorrectos.
- `Unknown database erp_crm_testing`: falta crear la base vacia.
- `Access denied`: el usuario no tiene permisos sobre la base de testing.
- Una prueba deja datos: verificar que use `RefreshDatabase`.
- Una prueba depende de registros de desarrollo: crear esos registros con
  factories o helpers dentro de la propia prueba.

## Regla de seguridad

Antes de ejecutar pruebas, comprobar:

```bash
php artisan env
php artisan migrate:status --env=testing
```

El entorno debe ser `testing` y la conexion debe apuntar exclusivamente a
`erp_crm_testing`.
