import { expect, test } from '@playwright/test';
import {
  cleanupE2E,
  clickAndWaitForEither,
  createOrderTrackerWithStatus,
  expectNoServerError,
  loginAs,
  setupBase,
  waitForLivewireAction,
  attachScreenshot,
} from './helpers';
import { ROUTES, USERS } from './fixtures/test-data';

test.describe('Order Tracker', () => {
  test.beforeAll(async ({ request }) => {
    await setupBase(request);
  });

  test.afterEach(async ({ request }) => {
    await cleanupE2E(request);
  });

  async function openFixtureTracker(page: import('@playwright/test').Page, trackingCode: string) {
    await page.goto(ROUTES.tracker);
    await expectNoServerError(page);

    await clickAndWaitForEither(page, page.getByTestId('tracker-list-view-button'), {
      livewire: true,
      locator: page.getByTestId('tracker-search-input'),
      actionName: 'cambiar tracker a vista lista',
    });

    await waitForLivewireAction(
      page,
      () => page.getByTestId('tracker-search-input').fill(trackingCode),
      { actionName: 'buscar tracker por codigo' },
    );

    const row = page.getByTestId(`tracker-row-${trackingCode}`);
    await expect(row).toBeVisible();

    await clickAndWaitForEither(page, row, {
      livewire: true,
      locator: page.getByTestId('tracker-order-status-link'),
      actionName: 'abrir detalle de tracker',
    });
  }

  test('modulo de tracking carga correctamente sin Server Error', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.tracker);
    await expectNoServerError(page);

    await expect(page.locator('body')).toContainText(/tracker|seguimiento|orden/i);
    await attachScreenshot(page, testInfo, '01-tracker-modulo-cargado');
  });

  test('buscar por codigo unico filtra el tracker creado por fixture', async ({ page, request }, testInfo) => {
    const fixture = await createOrderTrackerWithStatus(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.tracker);
    await expectNoServerError(page);

    await clickAndWaitForEither(page, page.getByTestId('tracker-list-view-button'), {
      livewire: true,
      locator: page.getByTestId('tracker-search-input'),
      actionName: 'vista lista para busqueda tracker',
    });
    await waitForLivewireAction(
      page,
      () => page.getByTestId('tracker-search-input').fill(fixture.trackingCode),
      { actionName: 'buscar tracking code fixture' },
    );

    await expect(page.getByTestId(`tracker-row-${fixture.trackingCode}`)).toBeVisible();
    await expect(page.locator('tbody')).toContainText(fixture.trackingCode);
    await attachScreenshot(page, testInfo, '02-tracker-busqueda-filtrada');
  });

  test('abrir detalle muestra timeline y documentos del tracker preparado', async ({ page, request }, testInfo) => {
    const fixture = await createOrderTrackerWithStatus(request);

    await loginAs(page, USERS.admin.email);
    await openFixtureTracker(page, fixture.trackingCode);

    const body = page.locator('body');
    await expect(body).toContainText(fixture.trackingCode);
    await expect(body).toContainText(/documento|pdf|orden-compra|purchase-order/i);
    await expect(body).toContainText(/fixture|tracker|timeline|historial|pedido/i);
    await attachScreenshot(page, testInfo, '03-tracker-detalle-timeline');
  });

  test('Ver Order Status navega al estado de la orden vinculada', async ({ page, request }, testInfo) => {
    const fixture = await createOrderTrackerWithStatus(request);

    await loginAs(page, USERS.admin.email);
    await openFixtureTracker(page, fixture.trackingCode);

    await clickAndWaitForEither(page, page.getByTestId('tracker-order-status-link'), {
      livewire: false,
      url: /\/orders\/status\/\d+/,
      actionName: 'navegar a Order Status desde tracker',
    });

    await expectNoServerError(page);
    await expect(page).toHaveURL(/\/orders\/status\/\d+/);
    await expect(page.getByTestId('order-status-header')).toContainText(fixture.trackingCode);
    await attachScreenshot(page, testInfo, '04-tracker-a-order-status');
  });

  test('bodega puede ver tracker sin costos sensibles de proveedor', async ({ page, request }) => {
    const fixture = await createOrderTrackerWithStatus(request);

    await loginAs(page, USERS.bodega.email);
    await openFixtureTracker(page, fixture.trackingCode);

    const body = await page.locator('body').textContent();
    expect(body ?? '').not.toMatch(/Costo proveedor USD|Precio proveedor|Margen bruto|Costo total estimado/i);
  });
});
