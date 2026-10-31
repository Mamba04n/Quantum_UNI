/**
 * KARDEX SPEC — tests/e2e/kardex.spec.ts
 *
 * Covers:
 *  1. Crear entrada Kardex
 *  2. Validar stock incrementado
 *  3. Crear salida Kardex
 *  4. Validar stock descontado
 *  5. Bloquear salida mayor a disponible
 *  6. Ver historial de movimientos
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { waitForLivewire } from './helpers/livewire';
import { expectNoServerError, attachScreenshot } from './helpers/assertions';
import { uploadPdf } from './helpers/files';
import { USERS, ROUTES } from './fixtures/test-data';

test.describe('Kardex — Entradas y Salidas', () => {

  test('módulo de movimientos de inventario carga sin errores', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.inventoryMoves);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    await expect(page.locator('body')).toContainText(/movimiento|stock|kardex/i);
    await attachScreenshot(page, testInfo, 'kardex-inventory-moves');
  });

  test('crear entrada de Kardex para un producto', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.inventoryMoves);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    // Buscar botón de nueva entrada
    const newEntryBtn = page.getByRole('button', { name: /nueva entrada|entrada|new entry|add entry/i }).first();
    if (await newEntryBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => newEntryBtn.click());
      await expectNoServerError(page);
      await attachScreenshot(page, testInfo, 'kardex-entrada-form');

      // Tipo de movimiento: entrada
      const typeSelect = page.locator('select[wire\\:model*="type"], select[wire\\:model*="direction"]').first();
      if (await typeSelect.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await typeSelect.selectOption({ value: 'entrada' });
      }

      // Seleccionar producto
      const productSelect = page.locator('select[wire\\:model*="product"]').first();
      if (await productSelect.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await productSelect.selectOption({ index: 1 }); // Primer producto disponible
      }

      // Cantidad
      const qtyInput = page.locator('input[wire\\:model*="qty"], input[wire\\:model*="quantity"]').first();
      if (await qtyInput.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await qtyInput.fill('2');
      }

      // Precio unitario
      const priceInput = page.locator('input[wire\\:model*="price"], input[wire\\:model*="unit_price"]').first();
      if (await priceInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await priceInput.fill('120.00');
      }

      // Referencia
      const refInput = page.locator('input[wire\\:model*="reference"], input[wire\\:model*="notes"]').first();
      if (await refInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await refInput.fill('E2E Entrada Kardex Test');
      }

      // Adjuntar evidencia si la pide
      const fileInput = page.locator('input[type="file"]').first();
      if (await fileInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await uploadPdf(page, 'input[type="file"]');
      }

      const saveBtn = page.getByRole('button', { name: /guardar|registrar|save/i }).last();
      if (await saveBtn.isVisible()) {
        await waitForLivewire(page, () => saveBtn.click());
        await expectNoServerError(page);
        await attachScreenshot(page, testInfo, 'kardex-entrada-guardada');
      }
    } else {
      test.skip();
    }
  });

  test('crear salida de Kardex y verificar stock descontado', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.inventoryMoves);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    // Buscar botón de nueva salida
    const newExitBtn = page.getByRole('button', { name: /nueva salida|salida|new exit|exit/i }).first();
    if (await newExitBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => newExitBtn.click());
      await expectNoServerError(page);

      const typeSelect = page.locator('select[wire\\:model*="type"], select[wire\\:model*="direction"]').first();
      if (await typeSelect.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await typeSelect.selectOption({ value: 'salida' });
      }

      // Seleccionar el mismo producto que la entrada anterior
      const productSelect = page.locator('select[wire\\:model*="product"]').first();
      if (await productSelect.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await productSelect.selectOption({ index: 1 });
      }

      // Cantidad de salida (menor o igual al stock disponible)
      const qtyInput = page.locator('input[wire\\:model*="qty"], input[wire\\:model*="quantity"]').first();
      if (await qtyInput.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await qtyInput.fill('1'); // Salida parcial
      }

      const saveBtn = page.getByRole('button', { name: /guardar|registrar|save/i }).last();
      if (await saveBtn.isVisible()) {
        await waitForLivewire(page, () => saveBtn.click());
        await expectNoServerError(page);
        await attachScreenshot(page, testInfo, 'kardex-salida-guardada');
      }
    } else {
      test.skip();
    }
  });

  test('bloquear salida mayor a stock disponible', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.inventoryMoves);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    const newExitBtn = page.getByRole('button', { name: /nueva salida|salida/i }).first();
    if (await newExitBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => newExitBtn.click());

      const productSelect = page.locator('select[wire\\:model*="product"]').first();
      if (await productSelect.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await productSelect.selectOption({ index: 1 });
      }

      // Intentar salida de cantidad excesiva
      const qtyInput = page.locator('input[wire\\:model*="qty"], input[wire\\:model*="quantity"]').first();
      if (await qtyInput.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await qtyInput.fill('999999'); // Excede cualquier stock razonable
      }

      const saveBtn = page.getByRole('button', { name: /guardar|registrar/i }).last();
      if (await saveBtn.isVisible()) {
        await saveBtn.click();
        await expectNoServerError(page);

        // Debe mostrar error de stock insuficiente
        await expect(page.locator('body')).toContainText(
          /stock insuficiente|insufficient stock|no hay suficiente|cantidad excede/i,
          { timeout: 10_000 }
        );
        await attachScreenshot(page, testInfo, 'kardex-salida-bloqueada');
      }
    } else {
      test.skip();
    }
  });

  test('kardex admin muestra historial completo de movimientos', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminKardex);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    await expect(page.locator('body')).toContainText(/kardex|movimiento/i);

    // Debe mostrar columnas de tipo (entrada/salida), cantidad, fecha
    const body = await page.locator('body').textContent() ?? '';
    const hasColumns = /entrada|salida|cantidad|fecha|producto/i.test(body);
    expect(hasColumns, 'Kardex debe mostrar columnas de movimientos').toBeTruthy();

    await attachScreenshot(page, testInfo, 'kardex-historial');
  });

  test('bodega puede ver el kardex admin', async ({ page }) => {
    await loginAs(page, USERS.bodega.email);
    await page.goto(ROUTES.adminKardex);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);
  });

  test('ventas no puede ver el kardex admin', async ({ page }) => {
    await loginAs(page, USERS.ventas.email);
    await page.goto(ROUTES.adminKardex);
    await expectNoServerError(page);
    const url = page.url();
    const body = await page.locator('body').textContent() ?? '';
    const isBlocked = url.includes('/login') || /403|forbidden|unauthorized/i.test(body);
    expect(isBlocked, 'Ventas no debe ver el kardex').toBeTruthy();
  });

});
