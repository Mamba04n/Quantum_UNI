import { expect, test } from '@playwright/test';
import {
  attachScreenshot,
  createOrderStatusPackingListFlow,
  expectNoServerError,
  loginAs,
  setupBase,
  appUrl,
} from './helpers';
import { USERS } from './fixtures/test-data';

test.describe('Packing List visual - flujo bodega a envio', () => {
  test.beforeAll(async ({ request }) => {
    await setupBase(request);
  });

  test('genera packing list PDF despues de Kardex salida y lo muestra en Order Status', async ({ page, request }, testInfo) => {
    const fixture = await createOrderStatusPackingListFlow(request);
    expect(fixture.packingListUrl, 'El fixture debe devolver URL del packing list').toBeTruthy();

    await loginAs(page, USERS.admin.email);

    await page.goto(appUrl(fixture.orderStatusUrl));
    await expectNoServerError(page);
    await expect(page.locator('body')).toContainText(/Packing list|Packing List|Salida Kardex|Enviado al cliente|Logistica/i);
    await expect(page.locator('body')).toContainText(/Validado para salida|Kardex|packing/i);
    await attachScreenshot(page, testInfo, '01-order-status-packing-list-generado');

    await page.goto(appUrl(fixture.packingListUrl!));
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toContainText(/404|500|Server Error|Exception|SQLSTATE/i);
    await attachScreenshot(page, testInfo, '02-packing-list-pdf-visual');
  });
});
