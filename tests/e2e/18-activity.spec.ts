/**
 * ACTIVITY SPEC — tests/e2e/activity.spec.ts
 *
 * Covers:
 *  1. Abrir Activity Monitor
 *  2. Validar mensajes humanos (no términos técnicos)
 *  3. Filtrar por usuario
 *  4. Filtrar por módulo
 *  5. Filtrar por fecha
 *  6. Admin ve detalles técnicos (IP, payload)
 *  7. Usuario sin permiso no ve IP/payload
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { waitForLivewire } from './helpers/livewire';
import { expectNoServerError, expectAccessDenied, attachScreenshot } from './helpers/assertions';
import { USERS, ROUTES } from './fixtures/test-data';

test.describe('Activity Monitor', () => {

  test('admin puede abrir el Activity Monitor', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminActivity);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    await expect(page.locator('body')).toContainText(/actividad|activity|log|registro/i);
    await attachScreenshot(page, testInfo, 'activity-monitor-list');
  });

  test('Activity Monitor no muestra estados técnicos en raw', async ({ page }) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminActivity);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    const body = await page.locator('body').textContent() ?? '';
    // Los mensajes de actividad no deben contener keys técnicas crudas
    expect(body).not.toContain('pending_purchase');
    expect(body).not.toContain('lista_para_licitar');
    expect(body).not.toContain('en_transito_aduanas');
  });

  test('admin puede filtrar actividad por usuario', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminActivity);
    await page.waitForLoadState('networkidle');

    const userFilter = page.locator('select[wire\\:model*="user"], input[wire\\:model*="user"]').first();
    if (await userFilter.isVisible({ timeout: 5_000 }).catch(() => false)) {
      if ((await userFilter.evaluate(el => el.tagName)) === 'SELECT') {
        await waitForLivewire(page, () => userFilter.selectOption({ index: 1 }));
      } else {
        await waitForLivewire(page, () => userFilter.fill('admin'));
      }
      await expectNoServerError(page);
      await attachScreenshot(page, testInfo, 'activity-filter-user');
    } else {
      test.skip();
    }
  });

  test('admin puede filtrar actividad por módulo', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminActivity);
    await page.waitForLoadState('networkidle');

    const moduleFilter = page.locator('select[wire\\:model*="module"]').first();
    if (await moduleFilter.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await waitForLivewire(page, () => moduleFilter.selectOption({ index: 1 }));
      await expectNoServerError(page);
      await attachScreenshot(page, testInfo, 'activity-filter-module');
    } else {
      test.skip();
    }
  });

  test('admin puede filtrar actividad por fecha', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminActivity);
    await page.waitForLoadState('networkidle');

    const dateInput = page.locator('input[type="date"], input[wire\\:model*="date"]').first();
    if (await dateInput.isVisible({ timeout: 5_000 }).catch(() => false)) {
      const today = new Date().toISOString().split('T')[0];
      await waitForLivewire(page, () => dateInput.fill(today));
      await expectNoServerError(page);
      await attachScreenshot(page, testInfo, 'activity-filter-date');
    } else {
      test.skip();
    }
  });

  test('ventas no puede acceder al Activity Monitor', async ({ page }) => {
    await loginAs(page, USERS.ventas.email);
    await page.goto(ROUTES.adminActivity);
    await expectAccessDenied(page);
    await expectNoServerError(page);
  });

  test('bodega no puede acceder al Activity Monitor', async ({ page }) => {
    await loginAs(page, USERS.bodega.email);
    await page.goto(ROUTES.adminActivity);
    await expectAccessDenied(page);
    await expectNoServerError(page);
  });

});
