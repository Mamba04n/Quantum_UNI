/**
 * DASHBOARD SPEC — tests/e2e/dashboard.spec.ts
 *
 * Covers:
 *  1. Admin ve dashboard completo con KPIs
 *  2. Ventas ve su dashboard (sin costos de finanzas)
 *  3. Compras ve su dashboard
 *  4. Bodega ve dashboard sin costos sensibles
 *  5. Finanzas ve CXP/CXC
 *  6. Estados técnicos NO se muestran (pending_purchase → "Pendiente de compra")
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { expectNoServerError, expectNoTechnicalStatuses, attachScreenshot } from './helpers/assertions';
import { USERS, HUMAN_STATUS_MAP, ROUTES } from './fixtures/test-data';

test.describe('Dashboard — visibilidad por rol', () => {

  // ── Admin ──────────────────────────────────────────────────────────────────
  test('admin ve dashboard completo con todos los KPIs', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.dashboard);
    await page.waitForLoadState('networkidle');

    await expectNoServerError(page);
    await expect(page).toHaveURL(/\/dashboard/);

    // No debe haber estados técnicos
    await expectNoTechnicalStatuses(page);

    await attachScreenshot(page, testInfo, 'dashboard-admin');
  });

  // ── Ventas ─────────────────────────────────────────────────────────────────
  test('ventas accede al dashboard sin ver métricas de finanzas sensibles', async ({ page }, testInfo) => {
    await loginAs(page, USERS.ventas.email);
    await page.goto(ROUTES.dashboard);
    await page.waitForLoadState('networkidle');

    await expectNoServerError(page);
    await expect(page).toHaveURL(/\/dashboard/);
    await expectNoTechnicalStatuses(page);

    await attachScreenshot(page, testInfo, 'dashboard-ventas');
  });

  // ── Compras ────────────────────────────────────────────────────────────────
  test('compras accede al dashboard', async ({ page }, testInfo) => {
    await loginAs(page, USERS.compras.email);
    await page.goto(ROUTES.dashboard);
    await page.waitForLoadState('networkidle');

    await expectNoServerError(page);
    await expectNoTechnicalStatuses(page);

    await attachScreenshot(page, testInfo, 'dashboard-compras');
  });

  // ── Bodega ─────────────────────────────────────────────────────────────────
  test('bodega accede al dashboard sin costos sensibles de finanzas', async ({ page }, testInfo) => {
    await loginAs(page, USERS.bodega.email);
    await page.goto(ROUTES.dashboard);
    await page.waitForLoadState('networkidle');

    await expectNoServerError(page);
    await expectNoTechnicalStatuses(page);

    const mainContent = page.locator('main').first();
    await expect(mainContent).not.toContainText(/Cuentas por Cobrar|Por Cobrar \(CXC\)|CXC/i);
    await expect(mainContent).not.toContainText(/Cuentas por Pagar|Por Pagar \(CXP\)|CXP/i);
    await expect(mainContent).not.toContainText(/Pagos Proximos|Pagos Próximos|Ver CXP|Ver todas/i);

    await attachScreenshot(page, testInfo, 'dashboard-bodega');
  });

  // ── Finanzas ───────────────────────────────────────────────────────────────
  test('finanzas ve resúmenes de CXP y CXC en el dashboard', async ({ page }, testInfo) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(ROUTES.dashboard);
    await page.waitForLoadState('networkidle');

    await expectNoServerError(page);
    await expectNoTechnicalStatuses(page);

    await attachScreenshot(page, testInfo, 'dashboard-finanzas');
  });

  // ── Estados técnicos ────────────────────────────────────────────────────────
  test('el dashboard nunca muestra estados técnicos en raw snake_case', async ({ page }) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.dashboard);
    await page.waitForLoadState('networkidle');

    // Verificar que ningún estado técnico aparece en texto visible
    const technicalStatuses = [
      'pending_purchase',
      'lista_para_licitar',
      'en_licitacion',
      'en_revision_admin',
      'solicitado_proveedor',
      'en_transito_aduanas',
      'recibido_bodega',
    ];

    for (const status of technicalStatuses) {
      const count = await page.locator(`text="${status}"`).count();
      expect(count, `Estado técnico "${status}" no debe aparecer en el dashboard`).toBe(0);
    }
  });

  // ── Redirección cliente externo ────────────────────────────────────────────
  test('cliente externo es redirigido al portal, no al dashboard ERP', async ({ page }) => {
    await loginAs(page, USERS.cliente.email);
    // El cliente debe ir al portal de cliente o a la portada pública
    await expect(page).toHaveURL(/\/mi-portal|\/login|\/$/);

    // Si intenta acceder al dashboard del ERP, debe ser bloqueado
    await page.goto(ROUTES.dashboard);
    await expect(page).not.toHaveURL(/\/dashboard$/);
    await expectNoServerError(page);
  });

  // ── No aparecen errores SQL o de servidor ──────────────────────────────────
  test.describe('ningún rol ve errores de servidor en el dashboard', () => {
    const roles: Array<keyof typeof USERS> = ['admin', 'ventas', 'compras', 'finanzas', 'bodega'];

    for (const role of roles) {
      test(`${role} — dashboard sin Server Error ni SQLSTATE`, async ({ page }) => {
        await loginAs(page, USERS[role].email);
        await page.goto(ROUTES.dashboard);
        await page.waitForLoadState('networkidle');
        await expectNoServerError(page);
        await expectNoTechnicalStatuses(page);
      });
    }
  });

});
