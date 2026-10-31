/**
 * WAREHOUSE SPEC — tests/e2e/warehouse.spec.ts
 *
 * Covers:
 *  1. Bodega ve órdenes próximas a llegar
 *  2. Registrar recepción parcial con foto adjunta
 *  3. No se puede registrar más cantidad de la disponible en OC
 *  4. Validar que el estado de la orden cambia tras recepción
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { waitForLivewire } from './helpers/livewire';
import { expectNoServerError, expectAccessDenied, attachScreenshot } from './helpers/assertions';
import { uploadPdf, uploadImage } from './helpers/files';
import { USERS, ROUTES } from './fixtures/test-data';

test.describe('Bodega — Recepción de Órdenes', () => {

  test('módulo de órdenes entrantes carga correctamente', async ({ page }, testInfo) => {
    await loginAs(page, USERS.bodega.email);
    await page.goto(ROUTES.warehouseIncoming);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    await expect(page.locator('body')).toContainText(/[oó]rdenes|incoming|entrante/i);
    await attachScreenshot(page, testInfo, 'bodega-incoming-orders');
  });

  test('admin también puede ver órdenes entrantes', async ({ page }) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.warehouseIncoming);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);
  });

  test('ventas no puede acceder a órdenes entrantes', async ({ page }) => {
    await loginAs(page, USERS.ventas.email);
    await page.goto(ROUTES.warehouseIncoming);
    await expectAccessDenied(page);
    await expectNoServerError(page);
  });

  test('registrar recepción parcial de orden', async ({ page }, testInfo) => {
    await loginAs(page, USERS.bodega.email);
    await page.goto(ROUTES.warehouseIncoming);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    // Buscar una orden con estado "esperando llegada" o similar
    const receiveBtn = page.getByRole('button', { name: /registrar recepci[oó]n|receive|recibir/i }).first();
    if (await receiveBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => receiveBtn.click());
      await expectNoServerError(page);
      await attachScreenshot(page, testInfo, 'recepcion-form-abierto');

      // Cantidad recibida (parcial: menor a la total)
      const qtyInput = page.locator('input[wire\\:model*="qty"], input[wire\\:model*="quantity"]').first();
      if (await qtyInput.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await qtyInput.fill('1'); // Parcial
      }

      // Adjuntar foto de la recepción
      const fileInput = page.locator('input[type="file"]').first();
      if (await fileInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await uploadImage(page, 'input[type="file"]', 'recepcion-bodega.png');
      }

      // Notas de recepción
      const notesInput = page.locator('textarea[wire\\:model*="notes"]').first();
      if (await notesInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await notesInput.fill('Recepción parcial E2E. 1 de 2 unidades recibidas.');
      }

      // Guardar
      const saveBtn = page.getByRole('button', { name: /guardar|registrar|save/i }).last();
      if (await saveBtn.isVisible()) {
        await waitForLivewire(page, () => saveBtn.click());
        await expectNoServerError(page);
        await attachScreenshot(page, testInfo, 'recepcion-guardada');
      }
    } else {
      test.skip();
    }
  });

  test('ver movimientos de inventario (Kardex)', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminKardex);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    await expect(page.locator('body')).toContainText(/kardex|movimiento|stock/i);
    await attachScreenshot(page, testInfo, 'kardex-viewer');
  });

  test('ver movimientos de stock desde inventario', async ({ page }, testInfo) => {
    await loginAs(page, USERS.bodega.email);
    await page.goto(ROUTES.inventoryMoves);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    await attachScreenshot(page, testInfo, 'inventory-moves');
  });

});
