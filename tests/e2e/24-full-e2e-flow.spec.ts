import { expect, test } from '@playwright/test';
import {
  cleanupE2E,
  createOrderTrackerWithStatus,
  expectNoServerError,
  loginAs,
  setupBase,
} from './helpers';
import { ROUTES, USERS } from './fixtures/test-data';

test.describe('Full E2E - smoke y flujo completo', () => {
  test.beforeAll(async ({ request }) => {
    await setupBase(request);
  });

  test.afterEach(async ({ request }) => {
    await cleanupE2E(request);
  });

  test('smoke integrado: dashboard, tracker y Order Status con fixture estable', async ({ page, request }) => {
    const fixture = await createOrderTrackerWithStatus(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.dashboard);
    await expectNoServerError(page);

    await page.goto(ROUTES.tracker);
    await expectNoServerError(page);
    await expect(page.locator('body')).toContainText(/tracker|seguimiento|orden/i);

    await page.goto(fixture.orderStatusUrl);
    await expectNoServerError(page);
    await expect(page.getByTestId('order-status-header')).toContainText(fixture.trackingCode);
  });

  test.fixme(
    'ciclo de vida completo de 39 pasos: activar solo cuando modulos individuales pasen',
    async () => {
      // Este flujo no debe preparar datos para los modulos individuales.
      // Reactivar cuando clientes, licitacion, oferta, aprobacion, tracker,
      // order status, bodega, kardex y finanzas esten verdes por separado.
    },
  );
});
