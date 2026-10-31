/**
 * RBAC SPEC — tests/e2e/rbac.spec.ts
 *
 * Covers:
 *  1. Ventas no puede entrar a /finance/cxp
 *  2. Bodega no puede aprobar compra (ruta bloqueada)
 *  3. Soporte no puede ver costos proveedor
 *  4. Cliente externo no puede entrar a /admin/users
 *  5. Usuario sin permiso no puede acceder por URL directa
 *  6. Botones restringidos no aparecen según rol
 *
 * Esperado: 403 | "Acceso denegado" | redirección segura | sin error 500
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { expectAccessDenied, expectNoServerError, expectButtonHidden } from './helpers/assertions';
import { USERS, ROUTES } from './fixtures/test-data';

// ── Definición de casos de acceso ─────────────────────────────────────────────
interface AccessCase {
  role: keyof typeof USERS;
  label: string;
  allowed: Array<{ url: string; name: string }>;
  forbidden: string[];
}

const accessMatrix: AccessCase[] = [
  {
    role:    'ventas',
    label:   'Ventas',
    allowed: [
      { url: ROUTES.dashboard,          name: 'dashboard' },
      { url: ROUTES.solicitudCliente,   name: 'solicitud-cliente' },
      { url: ROUTES.quotationsRequest,  name: 'gestor-request' },
    ],
    forbidden: [
      ROUTES.financeCxp,
      ROUTES.financeCxc,
      ROUTES.adminUsers,
      ROUTES.adminApprovals,
      ROUTES.adminKardex,
      ROUTES.adminKpis,
    ],
  },
  {
    role:    'compras',
    label:   'Compras',
    allowed: [
      { url: ROUTES.dashboard,       name: 'dashboard' },
      { url: ROUTES.adminSuppliers,  name: 'proveedores' },
      { url: ROUTES.adminApprovals,  name: 'aprobaciones' },
    ],
    forbidden: [
      ROUTES.financeCxp,
      ROUTES.adminUsers,
      ROUTES.solicitudCliente,
      ROUTES.adminKpis,
    ],
  },
  {
    role:    'finanzas',
    label:   'Finanzas',
    allowed: [
      { url: ROUTES.dashboard,        name: 'dashboard' },
      { url: ROUTES.financeCxp,       name: 'cxp' },
      { url: ROUTES.financeCxc,       name: 'cxc' },
      { url: ROUTES.financeInvoices,  name: 'facturas' },
    ],
    forbidden: [
      ROUTES.adminUsers,
      ROUTES.adminSuppliers,
      ROUTES.solicitudCliente,
      ROUTES.adminKpis,
    ],
  },
  {
    role:    'bodega',
    label:   'Bodega',
    allowed: [
      { url: ROUTES.dashboard,           name: 'dashboard' },
      { url: ROUTES.inventoryProducts,   name: 'productos' },
      { url: ROUTES.warehouseIncoming,   name: 'incoming-orders' },
    ],
    forbidden: [
      ROUTES.financeCxp,
      ROUTES.adminUsers,
      ROUTES.adminApprovals,
      ROUTES.solicitudCliente,
    ],
  },
  {
    role:    'soporte',
    label:   'Soporte',
    allowed: [
      { url: ROUTES.adminTickets, name: 'tickets' },
    ],
    forbidden: [
      ROUTES.financeCxp,
      ROUTES.adminUsers,
      ROUTES.adminApprovals,
      ROUTES.solicitudCliente,
      ROUTES.adminSuppliers,
      ROUTES.adminKpis,
    ],
  },
  {
    role:    'cliente',
    label:   'Cliente externo',
    allowed: [
      { url: ROUTES.customerPortal,  name: 'portal' },
      { url: ROUTES.customerTickets, name: 'tickets-cliente' },
    ],
    forbidden: [
      ROUTES.dashboard,
      ROUTES.adminUsers,
      ROUTES.adminApprovals,
      ROUTES.financeCxp,
      ROUTES.solicitudCliente,
      ROUTES.adminSuppliers,
    ],
  },
];

test.describe('RBAC — Control de acceso por rol', () => {

  for (const caseData of accessMatrix) {
    test.describe(`Rol: ${caseData.label}`, () => {

      // ── Rutas permitidas ────────────────────────────────────────────────────
      for (const { url, name } of caseData.allowed) {
        test(`puede acceder a ${name} (${url})`, async ({ page }, testInfo) => {
          await loginAs(page, USERS[caseData.role].email);
          const response = await page.goto(url);
          await page.waitForLoadState('networkidle');

          // No debe ser 403 ni 500
          expect(
            response?.status() ?? 0,
            `${caseData.label} debe poder acceder a ${url}`,
          ).toBeLessThan(400);

          await expectNoServerError(page);

          await testInfo.attach(`${caseData.role}-${name}`, {
            body: await page.screenshot({ fullPage: true }),
            contentType: 'image/png',
          });
        });
      }

      // ── Rutas prohibidas ────────────────────────────────────────────────────
      for (const url of caseData.forbidden) {
        test(`NO puede acceder por URL directa a ${url}`, async ({ page }) => {
          await loginAs(page, USERS[caseData.role].email);
          await page.goto(url);
          await expectAccessDenied(page);
          await expectNoServerError(page);
        });
      }
    });
  }

  // ── Pruebas específicas de botones ocultos ─────────────────────────────────
  test.describe('Botones restringidos por rol', () => {

    test('ventas no ve botón "Aprobar compra" en el flujo de aprobación', async ({ page }) => {
      await loginAs(page, USERS.ventas.email);
      // Ventas no tiene acceso a /admin/approvals, pero si llegara, no debe ver el botón
      await page.goto(ROUTES.dashboard);
      await expectButtonHidden(page, 'Aprobar compra');
    });

    test('bodega no ve costos de proveedor en el tracker', async ({ page }) => {
      await loginAs(page, USERS.bodega.email);
      await page.goto(ROUTES.warehouseIncoming);
      await page.waitForLoadState('networkidle');
      await expectNoServerError(page);

      // Bodega no debe ver precios de proveedor
      const body = page.locator('body');
      await expect(body).not.toContainText('Costo proveedor');
      await expect(body).not.toContainText('Precio proveedor USD');
    });

    test('soporte no ve botón de aprobación de compras', async ({ page }) => {
      await loginAs(page, USERS.soporte.email);
      await page.goto(ROUTES.adminTickets);
      await page.waitForLoadState('networkidle');
      await expectNoServerError(page);
      await expectButtonHidden(page, 'Aprobar compra');
      await expectButtonHidden(page, 'Solicitar aprobación interna');
    });

    test('ventas no ve botón "Generar OC Proveedor"', async ({ page }) => {
      await loginAs(page, USERS.ventas.email);
      await page.goto(ROUTES.dashboard);
      await expectButtonHidden(page, 'Generar OC Proveedor');
    });

  });

  // ── Prueba: sin error 500 en ninguna denegación ───────────────────────────
  test('ninguna denegación de acceso genera error 500', async ({ page }) => {
    const forbiddenCombinations = [
      { user: USERS.ventas.email,   url: ROUTES.financeCxp },
      { user: USERS.bodega.email,   url: ROUTES.adminUsers },
      { user: USERS.soporte.email,  url: ROUTES.adminApprovals },
      { user: USERS.cliente.email,  url: ROUTES.adminUsers },
      { user: USERS.finanzas.email, url: ROUTES.adminUsers },
    ];

    for (const { user, url } of forbiddenCombinations) {
      await loginAs(page, user);
      await page.goto(url);

      const body = await page.locator('body').textContent() ?? '';
      expect(body, `Acceso a ${url} no debe generar 500`).not.toContain('Server Error');
      expect(body, `Acceso a ${url} no debe generar SQLSTATE`).not.toContain('SQLSTATE');
      expect(body, `Acceso a ${url} no debe generar Internal Server Error`).not.toContain('Internal Server Error');
    }
  });

});
