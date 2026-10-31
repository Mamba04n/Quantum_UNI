# Quantum UNI

**ERP comercial para la gestión de refacciones, compras, inventario y ventas.**

[![Pruebas](https://github.com/Mamba04n/Quantum_UNI/actions/workflows/entrega-2.yml/badge.svg)](https://github.com/Mamba04n/Quantum_UNI/actions/workflows/entrega-2.yml)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-3-FB70A9?logo=livewire&logoColor=white)

Quantum UNI integra el ciclo comercial de una empresa revendedora de repuestos: desde el requerimiento del cliente y la evaluación de proveedores hasta la recepción, el control de existencias, la venta y su seguimiento financiero.

Es una versión académica de QuantumComplete orientada al estudio de procesos empresariales, integridad transaccional y pruebas de software. Incluye un caso sintético reproducible para recorrer el flujo de compra, recepción y venta utilizando los servicios del ERP.

## Funcionalidades

| Área | Capacidades principales |
| --- | --- |
| Gestión comercial | Clientes, solicitudes, cotizaciones, ofertas y aprobaciones. |
| Compras | Proveedores, órdenes de compra y recepción de partidas. |
| Inventario | Catálogo, generación de SKU, reservas, entradas, salidas y Kardex. |
| Ventas | Órdenes, venta directa desde inventario y documentos asociados. |
| Logística | Estado de pedidos, embarques y seguimiento de entregas. |
| Finanzas | Facturas, cuentas por cobrar y pagar, pagos y tipos de cambio. |
| Administración | Usuarios, roles, permisos, auditoría e indicadores. |
| Canal digital | Catálogo público y portal de cliente para solicitudes y seguimiento. |

## Arquitectura y tecnologías

La aplicación utiliza un **monolito modular**: los módulos comparten una base relacional y coordinan sus operaciones mediante servicios y acciones de dominio. Las pantallas administrativas se construyen con Blade y Livewire.

| Componente | Tecnología |
| --- | --- |
| Backend | PHP 8.2+ · Laravel 12 |
| Interfaz | Blade · Livewire 3 · Tailwind CSS |
| Recursos frontend | Vite · npm |
| Persistencia | MySQL / MariaDB · Eloquent |
| Documentos | Dompdf · mPDF · Laravel Excel |
| Verificación | PHPUnit · Playwright · Larastan · Pint |

```text
app/
├── Domain/       Servicios, acciones y consultas por módulo
├── Http/         Controladores y middleware
├── Livewire/     Componentes de interfaz
├── Models/       Modelos y relaciones
└── Policies/     Autorización por recurso
database/         Migraciones, seeders y factories
resources/        Vistas y fuentes del frontend
routes/           Rutas web y API
tests/            Pruebas unitarias, funcionales y de navegador
.github/workflows/ Integración continua
```

## Requisitos

- PHP **8.2 o superior**, con las extensiones requeridas por Composer, Laravel y mPDF; entre ellas PDO MySQL, mbstring, DOM, cURL, GD y ZIP.
- Composer.
- MySQL o MariaDB en ejecución.
- Node.js compatible con Vite: **20.19.x o 22.12+**, y npm **10 o superior**.
- Git para clonar el repositorio y ejecutar las verificaciones que inspeccionan archivos versionados.

Las versiones resueltas de dependencias se conservan en `composer.lock` y `package-lock.json`.

## Instalación local

Los siguientes pasos corresponden a una **instalación nueva**. Los ejemplos utilizan PowerShell; en Linux o macOS, sustituye `Copy-Item` por `cp`.

### 1. Clonar e instalar dependencias

```powershell
git clone https://github.com/Mamba04n/Quantum_UNI.git
cd Quantum_UNI
Copy-Item .env.example .env
composer install
npm ci
```

### 2. Configurar el entorno

Edita `.env` y conserva los parámetros del entorno académico:

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=quantum_uni

QUANTUM_UNI_ADMIN_EMAIL=admin@quantum-uni.test
QUANTUM_UNI_ADMIN_PASSWORD=
```

Configura `DB_USERNAME` y `DB_PASSWORD` según tu instalación de MySQL. Define una contraseña propia de **al menos 12 caracteres** en `QUANTUM_UNI_ADMIN_PASSWORD` antes de cargar el caso académico.

El archivo `.env.example` es una plantilla; `.env` contiene la configuración privada de cada instalación y está excluido de Git.

### 3. Preparar la base y los recursos

```powershell
php artisan key:generate
php artisan quantum-uni:install
npm run build
```

El comando `quantum-uni:install` crea `quantum_uni` si no existe, aplica migraciones y ejecuta el seeder académico. Solo acepta el entorno `local`, una conexión MySQL en `127.0.0.1` o `localhost` y ese nombre de base. El usuario de MySQL debe contar con los permisos necesarios.

Si la base ya fue creada y se administran las migraciones por separado, se puede utilizar `php artisan migrate --seed` con la configuración académica correcta.

Para habilitar archivos públicos almacenados por la aplicación:

```powershell
php artisan storage:link
```

### 4. Iniciar la aplicación

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Abre **http://127.0.0.1:8000/login** e ingresa con el correo y la contraseña definidos en `.env`.

En XAMPP, el `DocumentRoot` de Apache debe apuntar a la carpeta **`public/`** del proyecto. Para trabajar sobre los recursos del frontend durante el desarrollo, utiliza `npm run dev` en otra terminal.

## Piloto académico incluido

El [seeder del caso](database/seeders/AcademicCaseSeeder.php) prepara un cliente, un proveedor y un filtro identificados como datos académicos. Ejecuta recepción y venta mediante los servicios de la aplicación.

| Etapa | Operación | Resultado esperado |
| --- | --- | --- |
| Compra | 10 piezas a C$100.00 | Importe de compra: C$1,000.00. |
| Recepción | Ingreso de las 10 piezas | Entrada Kardex y saldo de 10. |
| Venta | 4 piezas a C$150.00 | Venta y factura por C$600.00. |
| Inventario final | 10 recibidas − 4 vendidas | 6 piezas con valor de C$600.00. |

El ensayo utiliza **NIO y no aplica impuesto**, para aislar la conciliación. El SKU se genera con el patrón del ERP; `E2-FILTRO-ACADEMICO` es la referencia de parte del producto. El seeder evita repetir el ciclo cuando ya existe la venta del caso.

Estos datos son sintéticos y no acreditan operaciones reales. El piloto demuestra un flujo comercial; no representa fabricación, trazabilidad por lote físico ni una inspección de calidad industrial.

Rutas útiles después del acceso:

| Pantalla | Ruta |
| --- | --- |
| Productos y existencias | `/inventory/products` |
| Kardex | `/admin/kardex` |
| Indicadores | `/admin/kpis` |

## Pruebas

### Preparar una base exclusiva

```powershell
Copy-Item .env.testing.example .env.testing
```

Configura las credenciales de MySQL en `.env.testing` y crea la base de pruebas:

```sql
CREATE DATABASE quantum_uni_testing
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Las pruebas que utilizan `RefreshDatabase` pueden recrear su esquema. Mantén esta conexión separada de `quantum_uni` y de cualquier base empresarial. La inicialización de pruebas rechaza nombres de base que no terminen en `_testing`, salvo SQLite en memoria.

### Ejecutar verificaciones

```powershell
php artisan test --filter=AcademicInstallationTest
php artisan test --filter=AcademicDeliveryEvidenceTest
php artisan test --filter=DirectInventorySaleTest
```

Para ejecutar toda la suite de PHP:

```powershell
php artisan test
```

La [integración continua](.github/workflows/entrega-2.yml) ejecuta una selección de pruebas sobre MySQL: instalación académica, conciliación de documentos e inventario, ventas, autorización, indicadores y controles de acceso. Esa selección se verificó localmente con **28 pruebas aprobadas y 183 aserciones**; el badge superior muestra el estado de GitHub Actions.

Las pruebas de navegador están en `tests/e2e` y utilizan Playwright. Su preparación es independiente del piloto mínimo: requieren navegador, servidor y los datos de prueba correspondientes. La selección de CI anterior no ejecuta esa suite de navegador.

## Seguridad y operación

- Roles, permisos y policies para controlar acciones y acceso a registros.
- Transacciones y bloqueos de registros en operaciones críticas de compra, inventario y venta.
- Clave de idempotencia para evitar repetir la misma venta directa.
- Documentos sensibles almacenados en disco privado y servidos mediante rutas autorizadas.
- `MAIL_MAILER=log` en la plantilla local: los correos se registran sin enviarse a destinatarios reales.

Las integraciones externas, como PayPal, requieren configuración y credenciales propias. No son necesarias para ejecutar el piloto académico.

Mantén fuera del repositorio `.env`, credenciales, respaldos, sesiones y documentos generados con datos privados. Un despliegue productivo requiere revisar permisos, configurar HTTPS, desactivar `APP_DEBUG` y validar respaldo y restauración.
