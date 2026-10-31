import { expect, test } from '@playwright/test';
import {
  cleanupE2E,
  createOrderStatusPartialDelivery,
  expectNoServerError,
  loginAs,
  setupBase,
  attachScreenshot,
} from './helpers';
import { USERS } from './fixtures/test-data';

test.describe('Order Status - estado de orden', () => {
  test.beforeAll(async ({ request }) => {
    await setupBase(request);
  });

  test.afterEach(async ({ request }) => {
    await cleanupE2E(request);
  });

  test('Order Status carga con header completo desde fixture controlado', async ({ page, request }, testInfo) => {
    const fixture = await createOrderStatusPartialDelivery(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(fixture.orderStatusUrl);
    await expectNoServerError(page);

    await expect(page.getByTestId('order-status-header')).toContainText(fixture.trackingCode);
    await expect(page.getByTestId('order-status-header')).toContainText(/cliente|tracking|progreso|total/i);
    await attachScreenshot(page, testInfo, '01-order-status-header-cargado');
  });

  test('las fases principales del Order Status son visibles', async ({ page, request }, testInfo) => {
    const fixture = await createOrderStatusPartialDelivery(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(fixture.orderStatusUrl);
    await expectNoServerError(page);

    for (const phase of ['pedido_extranjero', 'transito', 'bodega', 'kardex_entrada', 'kardex_salida', 'logistica', 'entregado']) {
      await expect(page.getByTestId(`order-status-phase-${phase}`)).toBeVisible();
    }
    await attachScreenshot(page, testInfo, '02-order-status-fases-visibles');
  });

  test('cada producto tiene su propio estado y cantidades', async ({ page, request }, testInfo) => {
    const fixture = await createOrderStatusPartialDelivery(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(fixture.orderStatusUrl);
    await expectNoServerError(page);

    for (const itemId of fixture.itemIds) {
      await expect(page.getByTestId(`order-status-item-row-${itemId}`)).toBeVisible();
    }

    const body = await page.locator('body').textContent();
    expect(body ?? '').not.toMatch(/pending_purchase|en_transito_aduanas/);
    await attachScreenshot(page, testInfo, '03-order-status-estado-individual-productos');
  });

  test('productos en fechas diferentes mantienen estado individual', async ({ page, request }, testInfo) => {
    const fixture = await createOrderStatusPartialDelivery(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(fixture.orderStatusUrl);
    await expectNoServerError(page);

    await expect(page.locator('body')).toContainText(/Recibido Bodega Parcial|Recibido Parcial/i);
    await expect(page.locator('body')).toContainText(/En Transito|Tr[aá]nsito/i);
    await expect(page.locator('body')).toContainText(/Cancelado Proveedor|Cancelado/i);
    await attachScreenshot(page, testInfo, '04-order-status-fechas-diferentes');
  });

  test.fixme(
    'cancelar un item por proveedor requiere evidencia antes de guardar',
    async () => {
      // Funcionalidad incompleta: el servicio actual exige motivo, pero no fuerza documento/evidencia.
      // Este test debe activarse cuando cancelBySupplier valide evidencia obligatoria.
    },
  );

  test('bodega puede ver Order Status sin costos sensibles', async ({ page, request }) => {
    const fixture = await createOrderStatusPartialDelivery(request);

    await loginAs(page, USERS.bodega.email);
    await page.goto(fixture.orderStatusUrl);
    await expectNoServerError(page);

    const body = await page.locator('body').textContent();
    expect(body ?? '').not.toMatch(/Costo proveedor USD|Precio de compra|Margen bruto|Costo total/i);
  });
});
