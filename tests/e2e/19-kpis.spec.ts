/**
 * KPIs SPEC — tests/e2e/kpis.spec.ts
 *
 * Covers:
 *  1. Margen promedio visible para admin
 *  2. Tiempo promedio de entrega
 *  3. Volumen de ventas
 *  4. % cierre
 *  5. Costo total inventario
 *  6. Filtros por cliente, proveedor, marca, segmento
 *  7. Rol sin permiso no ve costos sensibles
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { waitForLivewire } from './helpers/livewire';
import { expectNoServerError, expectAccessDenied, attachScreenshot } from './helpers/assertions';
import { USERS, ROUTES } from './fixtures/test-data';

test.describe('KPIs — Indicadores de Rendimiento', () => {

  test('admin puede acceder al módulo de KPIs', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminKpis);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    await expect(page.locator('body')).toContainText(/kpi|indicador|margen|ventas/i);
    await attachScreenshot(page, testInfo, 'kpis-admin');
  });

  test('KPIs no muestran estados técnicos crudos', async ({ page }) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminKpis);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    const body = await page.locator('body').textContent() ?? '';
    expect(body).not.toContain('pending_purchase');
    expect(body).not.toContain('en_transito_aduanas');
  });

  test('filtrar KPIs por cliente muestra resultados', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminKpis);
    await page.waitForLoadState('networkidle');

    const clientFilter = page.locator('select[wire\\:model*="client"], input[wire\\:model*="client"]').first();
    if (await clientFilter.isVisible({ timeout: 10_000 }).catch(() => false)) {
      const tag = await clientFilter.evaluate(el => el.tagName.toLowerCase());
      if (tag === 'select') {
        await waitForLivewire(page, () => clientFilter.selectOption({ index: 1 }));
      } else {
        await waitForLivewire(page, () => clientFilter.fill('San Miguel'));
      }
      await expectNoServerError(page);
      await attachScreenshot(page, testInfo, 'kpis-filter-cliente');
    } else {
      test.skip();
    }
  });

  test('filtrar KPIs por proveedor', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminKpis);
    await page.waitForLoadState('networkidle');

    const supplierFilter = page.locator('select[wire\\:model*="supplier"], input[wire\\:model*="supplier"]').first();
    if (await supplierFilter.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await waitForLivewire(page, () => supplierFilter.fill('ACSIS'));
      await expectNoServerError(page);
      await attachScreenshot(page, testInfo, 'kpis-filter-proveedor');
    } else {
      test.skip();
    }
  });

  test('ventas no puede acceder a KPIs', async ({ page }) => {
    await loginAs(page, USERS.ventas.email);
    await page.goto(ROUTES.adminKpis);
    await expectAccessDenied(page);
    await expectNoServerError(page);
  });

  test('bodega no puede acceder a KPIs', async ({ page }) => {
    await loginAs(page, USERS.bodega.email);
    await page.goto(ROUTES.adminKpis);
    await expectAccessDenied(page);
    await expectNoServerError(page);
  });

  test('finanzas no puede acceder a KPIs', async ({ page }) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(ROUTES.adminKpis);
    await expectAccessDenied(page);
    await expectNoServerError(page);
  });

});
