/**
 * SECURITY SPEC — tests/e2e/security.spec.ts
 *
 * Covers:
 *  1. URL directa sin permiso → 403 o redirect (nunca 500)
 *  2. Botones restringidos no aparecen en el DOM para roles no autorizados
 *  3. Acción Livewire forzada por rol incorrecto → bloqueada
 *  4. Cliente externo no entra a admin
 *  5. Bodega no ve costos de proveedor
 *  6. Soporte no aprueba compras
 *  7. Ventas no genera OC proveedor
 *  8. CSRF: petición sin token es rechazada
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { expectNoServerError, expectAccessDenied, expectButtonHidden } from './helpers/assertions';
import { USERS, ROUTES } from './fixtures/test-data';

test.describe('Seguridad — Protección de Rutas y Acciones', () => {

  // ── 1. URL directa sin permiso ─────────────────────────────────────────────
  test.describe('Acceso por URL directa sin permiso', () => {
    const securityMatrix = [
      { user: 'ventas'  as const, url: ROUTES.financeCxp,      label: 'ventas→CXP' },
      { user: 'ventas'  as const, url: ROUTES.adminUsers,       label: 'ventas→admin.users' },
      { user: 'bodega'  as const, url: ROUTES.adminApprovals,   label: 'bodega→approvals' },
      { user: 'bodega'  as const, url: ROUTES.adminUsers,       label: 'bodega→admin.users' },
      { user: 'soporte' as const, url: ROUTES.adminApprovals,   label: 'soporte→approvals' },
      { user: 'soporte' as const, url: ROUTES.financeCxp,       label: 'soporte→CXP' },
      { user: 'cliente' as const, url: ROUTES.adminUsers,       label: 'cliente→admin.users' },
      { user: 'cliente' as const, url: ROUTES.dashboard,        label: 'cliente→dashboard' },
      { user: 'finanzas'as const, url: ROUTES.adminUsers,       label: 'finanzas→admin.users' },
    ];

    for (const { user, url, label } of securityMatrix) {
      test(`${label}: bloqueado con 403 o redirect, sin 500`, async ({ page }) => {
        await loginAs(page, USERS[user].email);
        await page.goto(url);
        await expectAccessDenied(page);
        await expectNoServerError(page);
      });
    }
  });

  // ── 2. Botones restringidos no en DOM ──────────────────────────────────────
  test.describe('Botones restringidos ausentes del DOM', () => {

    test('ventas no ve botón "Aprobar compra" en ninguna página', async ({ page }) => {
      await loginAs(page, USERS.ventas.email);
      await page.goto(ROUTES.dashboard);
      await page.waitForLoadState('networkidle');
      await expectButtonHidden(page, /aprobar compra|approve purchase/i);
    });

    test('ventas no ve botón "Generar OC Proveedor"', async ({ page }) => {
      await loginAs(page, USERS.ventas.email);
      await page.goto(ROUTES.dashboard);
      await page.waitForLoadState('networkidle');
      await expectButtonHidden(page, /generar oc proveedor|purchase order/i);
    });

    test('soporte no ve botón "Solicitar aprobación interna"', async ({ page }) => {
      await loginAs(page, USERS.soporte.email);
      await page.goto(ROUTES.adminTickets);
      await page.waitForLoadState('networkidle');
      await expectButtonHidden(page, /solicitar aprobaci[oó]n interna/i);
    });

    test('bodega no ve campo de costo de proveedor', async ({ page }) => {
      await loginAs(page, USERS.bodega.email);
      await page.goto(ROUTES.warehouseIncoming);
      await page.waitForLoadState('networkidle');
      await expectNoServerError(page);

      const body = await page.locator('body').textContent() ?? '';
      expect(body).not.toContain('Precio proveedor USD');
      expect(body).not.toContain('Costo unitario proveedor');
    });

  });

  // ── 3. Acción Livewire forzada ─────────────────────────────────────────────
  test('POST directo a /livewire/update sin CSRF es rechazado', async ({ request }) => {
    // Sin el token CSRF, Livewire debe rechazar la petición
    const response = await request.post('/livewire/update', {
      data: {
        components: [],
      },
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        // Deliberadamente sin X-CSRF-TOKEN
      },
    });

    // Debe ser 419 (CSRF token mismatch) o 403
    expect([419, 403, 422]).toContain(response.status());
  });

  // ── 4. Cliente externo no entra a admin ────────────────────────────────────
  test('cliente externo es bloqueado en todas las rutas admin', async ({ page }) => {
    await loginAs(page, USERS.cliente.email);

    const adminRoutes = [
      ROUTES.adminUsers,
      ROUTES.adminApprovals,
      ROUTES.adminActivity,
      ROUTES.adminSuppliers,
      ROUTES.financeCxp,
    ];

    for (const url of adminRoutes) {
      await page.goto(url);
      await expectAccessDenied(page);
      await expectNoServerError(page);
    }
  });

  // ── 5. Bodega no ve costos de proveedor ────────────────────────────────────
  test('bodega no ve costos de proveedor en ninguna pantalla visible', async ({ page }) => {
    await loginAs(page, USERS.bodega.email);

    const bodegaRoutes = [ROUTES.warehouseIncoming, ROUTES.inventoryProducts, ROUTES.inventoryMoves];
    for (const url of bodegaRoutes) {
      await page.goto(url);
      await page.waitForLoadState('networkidle');
      await expectNoServerError(page);

      const body = await page.locator('body').textContent() ?? '';
      expect(body, `Bodega no debe ver costos en ${url}`).not.toContain('Costo proveedor USD');
    }
  });

  // ── 6. Soporte no aprueba compras ──────────────────────────────────────────
  test('soporte no puede aprobar compras — ruta bloqueada', async ({ page }) => {
    await loginAs(page, USERS.soporte.email);
    await page.goto(ROUTES.adminApprovals);
    await expectAccessDenied(page);
    await expectNoServerError(page);
  });

  // ── 7. Ventas no genera OC proveedor ──────────────────────────────────────
  test('ventas no puede acceder a la ruta de creación de OC proveedor', async ({ page }) => {
    await loginAs(page, USERS.ventas.email);
    await page.goto('/purchases/create');
    await expectAccessDenied(page);
    await expectNoServerError(page);
  });

  // ── 8. Sin errores 500 en ningún 403 ──────────────────────────────────────
  test('ninguna acción de seguridad genera un error 500', async ({ page }) => {
    const combinations = [
      { user: USERS.ventas.email,   url: ROUTES.financeCxp },
      { user: USERS.bodega.email,   url: ROUTES.adminApprovals },
      { user: USERS.soporte.email,  url: ROUTES.adminSuppliers },
      { user: USERS.cliente.email,  url: ROUTES.adminUsers },
    ];

    for (const { user, url } of combinations) {
      await loginAs(page, user);
      await page.goto(url);
      await expectNoServerError(page);
    }
  });

  // ── Rutas de autenticación básica ─────────────────────────────────────────
  test('ruta /auth/login es accesible sin sesión', async ({ page }) => {
    const response = await page.goto('/login');
    expect(response?.status() ?? 0).toBeLessThan(500);
    await expectNoServerError(page);
  });

  test('no hay información sensible expuesta en errores de validación', async ({ page }) => {
    await loginAs(page, USERS.ventas.email);
    await page.goto(ROUTES.financeCxp);

    const body = await page.locator('body').textContent() ?? '';
    // Los errores no deben exponer stacktraces ni queries SQL
    expect(body).not.toContain('at App\\');
    expect(body).not.toContain('SELECT');
    expect(body).not.toContain('stack trace');
  });

});
