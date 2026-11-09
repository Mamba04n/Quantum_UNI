# ERP Quantum — Suite E2E Playwright

Suite completa de pruebas End-to-End para el ERP Quantum Soluciones S.A., construida con Playwright + TypeScript.

---

## Estructura de la Suite

```
tests/e2e/
├── fixtures/
│   ├── evidence.pdf              # PDF de evidencia para uploads
│   ├── test-data.ts              # Datos canónicos (clientes, productos, proveedores, rutas)
│   └── image.ts                  # Buffers de imagen PNG/JPEG inline
├── helpers/
│   ├── auth.ts                   # loginAs(), logout()
│   ├── livewire.ts               # waitForLivewire(), expectToast(), waitForFileUpload()
│   ├── data.ts                   # setupE2EData(), createCustomer(), uniqueSuffix()
│   ├── files.ts                  # uploadPdf(), uploadImage(), uploadInvalidFile()
│   ├── assertions.ts             # expectAccessDenied(), expectNoServerError(), attachScreenshot()
│   └── index.ts                  # Barrel export
├── auth.spec.ts                  # Login, logout, rutas protegidas
├── dashboard.spec.ts             # Dashboard por rol, sin estados técnicos
├── rbac.spec.ts                  # Permisos por rol — rutas permitidas y bloqueadas
├── customers.spec.ts             # CRUD clientes, estados activo/inactivo
├── suppliers.spec.ts             # CRUD proveedores, marcas, métodos de pago
├── commercial-flow.spec.ts       # Solicitud cliente, maquinaria, dos productos
├── supplier-bid.spec.ts          # RFQ, respuestas múltiples, selección de partidas
├── client-offer.spec.ts          # Oferta cliente, IVA, revisión admin, PDF final
├── admin-approval.spec.ts        # Aprobación interna, rechazos, cambios, respuesta cliente
├── order-tracker.spec.ts         # Tracking, timeline, documentos por fase
├── order-status.spec.ts          # Fases por producto, entrega parcial, cancelación
├── warehouse.spec.ts             # Recepción de órdenes, fotos
├── kardex.spec.ts                # Entradas/salidas, bloqueo sobre stock disponible
├── inventory.spec.ts             # Productos, SKU, precios, imágenes, categorías
├── finance.spec.ts               # CXP, CXC, facturas, pagos (evidencia obligatoria)
├── documents.spec.ts             # Upload válido/inválido, protección /storage
├── security.spec.ts              # Seguridad, CSRF, URL directa, sin 500
├── activity.spec.ts              # Activity Monitor, filtros, datos sensibles
├── kpis.spec.ts                  # KPIs con filtros, acceso admin
├── support.spec.ts               # Tickets: crear, responder, cerrar
├── content.spec.ts               # Blog técnico, catálogos, publicar/desactivar
├── pdfs.spec.ts                  # Validación de PDFs (RFQ, oferta, OC proveedor)
├── anti-duplicate.spec.ts        # Doble clic en 7 acciones críticas sin duplicar
├── full-e2e-flow.spec.ts         # Flujo completo 39 pasos end-to-end
├── smoke.spec.ts                 # Tests de humo básicos (mantener)
├── global-setup.ts               # Arranque del servidor + seeder
├── global-teardown.ts            # Apagado del servidor
└── laravel-router.php            # Router PHP para el servidor de tests
```

---

## Usuarios de Prueba

El seeder `E2EVisualSeeder` crea automáticamente todos estos usuarios:

| Email                          | Rol             | Contraseña     |
|--------------------------------|-----------------|----------------|
| `admin@quantum.test`           | Admin           | `Password123!` |
| `ventas@quantum.test`          | Ventas          | `Password123!` |
| `compras@quantum.test`         | Compras         | `Password123!` |
| `finanzas@quantum.test`        | Agente Finanzas | `Password123!` |
| `bodega@quantum.test`          | Bodega          | `Password123!` |
| `soporte@quantum.test`         | Soporte         | `Password123!` |
| `cliente.test@quantum.test`    | Cliente Tienda  | `Password123!` |

---

## Entidades Base (Seeder)

- **ACSIS Parts** — Proveedor USA (`sales@acsisparts.com`)
- **KB Repuestos** — Proveedor Corea (`sales@kbparts.kr`)
- **Agroservicios San Miguel** — Cliente (`carlos@agrosanmiguel.com`)
- **Proveedor E2E ACSIS** — Proveedor genérico de testing

---

## Variables de Entorno

```bash
E2E_BASE_URL=http://127.0.0.1:8000
E2E_ADMIN_EMAIL=admin@quantum.test
E2E_PASSWORD=Password123!
E2E_CHROME_PATH="C:\Program Files\Google\Chrome\Application\chrome.exe"
E2E_MANAGE_SERVER=1   # 0 para usar servidor externo ya corriendo
```

---

## Instalación

```bash
# Instalar dependencias de Node
npm install

# Instalar browsers de Playwright (si no tienes Chrome en el PATH)
npx playwright install chromium
```

---

## Preparar la Base de Datos

```bash
# Ejecutar el seeder de E2E (mantiene datos existentes, usa updateOrCreate)
php artisan db:seed --class=E2EVisualSeeder

# O desde cero (destruye todos los datos):
php artisan migrate:fresh --seed
```

---

## Comandos de Ejecución

```bash
# Suite completa (modo headless)
npm run test:e2e

# Suite completa con servidor externo (XAMPP ya corriendo)
npm run test:e2e:external

# Con browser visible
npm run test:e2e:headed

# Modo UI interactivo
npm run test:e2e:ui

# Módulo específico
npx playwright test tests/e2e/auth.spec.ts
npx playwright test tests/e2e/finance.spec.ts --headed

# Con proyecto específico (Chrome Desktop)
npx playwright test --project=chrome-desktop

# Ver reporte HTML
npm run test:e2e:report
# o
npx playwright show-report
```

---

## Tests por Módulo

| Comando | Módulo |
|---------|--------|
| `npx playwright test auth.spec.ts` | Autenticación |
| `npx playwright test dashboard.spec.ts` | Dashboard |
| `npx playwright test rbac.spec.ts` | Roles y permisos |
| `npx playwright test customers.spec.ts` | Clientes |
| `npx playwright test suppliers.spec.ts` | Proveedores |
| `npx playwright test commercial-flow.spec.ts` | Solicitud cliente |
| `npx playwright test supplier-bid.spec.ts` | Licitación RFQ |
| `npx playwright test client-offer.spec.ts` | Oferta cliente |
| `npx playwright test admin-approval.spec.ts` | Aprobaciones |
| `npx playwright test order-tracker.spec.ts` | Order Tracker |
| `npx playwright test order-status.spec.ts` | Order Status |
| `npx playwright test warehouse.spec.ts` | Bodega |
| `npx playwright test kardex.spec.ts` | Kardex |
| `npx playwright test inventory.spec.ts` | Inventario |
| `npx playwright test finance.spec.ts` | CXP/CXC/Facturas |
| `npx playwright test documents.spec.ts` | Documentos |
| `npx playwright test security.spec.ts` | Seguridad |
| `npx playwright test activity.spec.ts` | Activity Monitor |
| `npx playwright test kpis.spec.ts` | KPIs |
| `npx playwright test support.spec.ts` | Soporte/Tickets |
| `npx playwright test content.spec.ts` | Blog/Catálogos |
| `npx playwright test pdfs.spec.ts` | PDFs |
| `npx playwright test anti-duplicate.spec.ts` | Anti-duplicados |
| `npx playwright test full-e2e-flow.spec.ts` | Flujo completo 39 pasos |

---

## Debugging

```bash
# Ver trazas de un test fallido
npx playwright show-trace test-results/<test-name>/trace.zip

# Debug interactivo (abre el inspector de Playwright)
npx playwright test auth.spec.ts --debug

# Guardar traza de TODOS los tests (no solo en fallo)
npx playwright test --trace on

# Codegen: grabar acciones del browser y generar código
npx playwright codegen http://127.0.0.1:8000/login
```

---

## CI / GitHub Actions

```yaml
name: E2E Tests

on: [push, pull_request]

jobs:
  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Install dependencies
        run: npm ci

      - name: Install Playwright browsers
        run: npx playwright install --with-deps chromium

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install Composer dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Configure environment
        run: |
          cp .env.testing .env
          php artisan key:generate

      - name: Run migrations and seed
        run: |
          php artisan migrate:fresh --seed --env=testing

      - name: Start Laravel server
        run: php artisan serve --port=8000 &

      - name: Run E2E tests
        run: npm run test:e2e
        env:
          E2E_BASE_URL: http://127.0.0.1:8000
          E2E_MANAGE_SERVER: 0
          CI: true

      - name: Upload test results
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: playwright-report
          path: playwright-report/
          retention-days: 30
```

---

## Principios de la Suite

### ✅ NUNCA usar `waitForTimeout`

```typescript
// ❌ MAL
await page.waitForTimeout(2000);

// ✅ BIEN — esperar respuesta Livewire
await waitForLivewire(page, () => button.click());

// ✅ BIEN — esperar elemento visible
await expect(locator).toBeVisible();

// ✅ BIEN — esperar navegación
await page.waitForURL(/dashboard/);
```

### ✅ Usar helpers para Livewire

```typescript
import { waitForLivewire, expectToast } from './helpers/livewire';

// Esperar ciclo completo de Livewire
await waitForLivewire(page, () => page.getByRole('button', { name: 'Guardar' }).click());

// Validar toast de éxito
await expectToast(page, 'Guardado exitosamente');
```

### ✅ Datos dinámicos para aislamiento

```typescript
import { uniqueSuffix, uniqueEmail } from './helpers/data';

const uid = uniqueSuffix();
const clientName = `Cliente E2E ${uid}`;  // Único por test
const email = uniqueEmail('cliente');      // cliente.17832841@e2e.test
```

### ✅ Screenshots en puntos clave

```typescript
import { attachScreenshot } from './helpers/assertions';

await attachScreenshot(page, testInfo, 'nombre-descriptivo');
```

---

## Arquitectura de Helpers

```
helpers/
├── auth.ts       loginAs(), logout()
├── livewire.ts   waitForLivewire(), expectToast(), waitForFileUpload()
├── data.ts       uniqueSuffix(), uniqueEmail(), createCustomer()
├── files.ts      uploadPdf(), uploadImage(), uploadInvalidFile()
├── assertions.ts expectAccessDenied(), expectNoServerError(), attachScreenshot()
└── index.ts      Re-exporta todo (import from './helpers')
```

---

## Endpoints de Testing Laravel

Disponibles **solo en entorno `local` y `testing`**:

| Endpoint | Descripción |
|----------|-------------|
| `POST /testing/e2e/setup` | Ejecuta el seeder E2E |
| `POST /testing/e2e/cleanup` | Limpia datos de test |
| `POST /testing/e2e/create-customer` | Crea un cliente |
| `POST /testing/e2e/create-supplier` | Crea un proveedor |
| `POST /testing/e2e/create-product` | Crea un producto |
| `POST /testing/e2e/login-user-data` | Retorna credenciales por rol |

> ⚠️ **Estos endpoints NO están disponibles en producción.** El controlador tiene un guard de entorno que llama `abort(404)` fuera de `local`/`testing`.
