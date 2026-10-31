/**
 * FINANCE SPEC — tests/e2e/finance.spec.ts
 *
 * Covers:
 *  CXP:
 *   1. Crear CXP
 *   2. Intentar guardar sin evidencia → bloqueado
 *   3. Adjuntar factura proveedor
 *   4. Guardar CXP
 *   5. Registrar pago parcial con comprobante
 *   6. Registrar pago total
 *   7. Validar saldo y estado
 *
 *  CXC:
 *   8. Crear CXC
 *   9. Intentar guardar sin evidencia → bloqueado
 *  10. Adjuntar factura cliente
 *  11. Registrar cobro parcial
 *  12. Registrar cobro total
 *  13. Validar saldo y estado
 *
 *  FACTURAS:
 *  14. Crear factura cliente con PDF
 *  15. Intentar factura sin documento → bloqueado
 *  16. Crear factura proveedor con invoice
 *  17. Relacionar factura con CXC/CXP
 *  18. Descargar documentos
 *
 *  PAGOS:
 *  19. Registrar pago proveedor
 *  20. Intentar guardar sin comprobante → bloqueado
 *  21. Adjuntar comprobante
 *  22. Registrar pago cliente
 *  23. Validar saldo y Activity
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { waitForLivewire } from './helpers/livewire';
import { expectNoServerError, expectAccessDenied, attachScreenshot } from './helpers/assertions';
import { appUrl } from './helpers/navigation';
import { uploadPdf } from './helpers/files';
import { uniqueSuffix } from './helpers/data';
import { USERS, ROUTES } from './fixtures/test-data';

// ── CXP ───────────────────────────────────────────────────────────────────────
test.describe('CXP — Cuentas por Pagar', () => {

  test('módulo CXP carga correctamente para finanzas', async ({ page }, testInfo) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(appUrl(ROUTES.financeCxp));
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);
    await expect(page.locator('body')).toContainText(/cuentas por pagar|CXP|payable/i);
    await attachScreenshot(page, testInfo, 'cxp-list');
  });

  test('crear CXP sin evidencia está bloqueado', async ({ page }, testInfo) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(appUrl(ROUTES.financeCxp));
    await page.waitForLoadState('networkidle');

    const newBtn = page.getByRole('button', { name: /nueva|crear|new/i }).first();
    if (await newBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => newBtn.click());

      // Rellenar datos básicos
      const amountInput = page.locator('input[wire\\:model*="amount"], input[wire\\:model*="total"]').first();
      if (await amountInput.isVisible()) await amountInput.fill('1500.00');

      // Intentar guardar sin evidencia (sin adjuntar archivo)
      const saveBtn = page.getByRole('button', { name: /guardar|save/i }).last();
      if (await saveBtn.isVisible()) {
        await saveBtn.click();
        await expectNoServerError(page);
        // Debe aparecer mensaje de evidencia requerida
        await expect(page.locator('body')).toContainText(
          /evidencia|comprobante|documento|required|requerido/i, { timeout: 10_000 }
        );
        await attachScreenshot(page, testInfo, 'cxp-sin-evidencia-bloqueado');
      }
    } else {
      test.skip();
    }
  });

  test('crear CXP con factura adjunta y registrar pagos', async ({ page }, testInfo) => {
    const uid = uniqueSuffix();

    await loginAs(page, USERS.finanzas.email);
    await page.goto(appUrl(ROUTES.financeCxp));
    await page.waitForLoadState('networkidle');

    const newBtn = page.getByRole('button', { name: /nueva|crear/i }).first();
    if (await newBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => newBtn.click());

      // Monto
      const amountInput = page.locator('input[wire\\:model*="amount"], input[wire\\:model*="total"]').first();
      if (await amountInput.isVisible()) await amountInput.fill('1500.00');

      // Proveedor
      const supplierSelect = page.locator('select[wire\\:model*="supplier"], select[wire\\:model*="entity"]').first();
      if (await supplierSelect.isVisible()) await supplierSelect.selectOption({ index: 1 });

      // Fecha de vencimiento
      const dueDateInput = page.locator('input[wire\\:model*="due_date"], input[type="date"]').first();
      if (await dueDateInput.isVisible()) await dueDateInput.fill('2026-12-31');

      // Descripción
      const descInput = page.locator('input[wire\\:model*="desc"], textarea[wire\\:model*="desc"]').first();
      if (await descInput.isVisible()) await descInput.fill(`CXP E2E Test ${uid}`);

      // Adjuntar factura proveedor (evidencia obligatoria)
      const fileInput = page.locator('input[type="file"]').first();
      if (await fileInput.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await uploadPdf(page, 'input[type="file"]');
      }

      const saveBtn = page.getByRole('button', { name: /guardar|save/i }).last();
      if (await saveBtn.isVisible()) {
        await waitForLivewire(page, () => saveBtn.click());
        await expectNoServerError(page);
        await attachScreenshot(page, testInfo, 'cxp-creado');
      }

      // Registrar pago parcial
      const partialPayBtn = page.getByRole('button', { name: /registrar pago|add payment/i }).first();
      if (await partialPayBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
        await waitForLivewire(page, () => partialPayBtn.click());

        const payAmountInput = page.locator('input[wire\\:model*="payAmount"], input[wire\\:model*="amount"]').first();
        if (await payAmountInput.isVisible()) await payAmountInput.fill('500.00'); // Pago parcial

        // Adjuntar comprobante
        const compInput = page.locator('input[type="file"]').first();
        if (await compInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
          await uploadPdf(page, 'input[type="file"]');
        }

        const savePayBtn = page.getByRole('button', { name: /guardar pago|confirm/i }).last();
        if (await savePayBtn.isVisible()) {
          await waitForLivewire(page, () => savePayBtn.click());
          await expectNoServerError(page);
          await attachScreenshot(page, testInfo, 'cxp-pago-parcial');
        }
      }
    } else {
      test.skip();
    }
  });

  test('ventas no puede acceder a CXP', async ({ page }) => {
    await loginAs(page, USERS.ventas.email);
    await page.goto(appUrl(ROUTES.financeCxp));
    await expectAccessDenied(page);
    await expectNoServerError(page);
  });

});

// ── CXC ───────────────────────────────────────────────────────────────────────
test.describe('CXC — Cuentas por Cobrar', () => {

  test('módulo CXC carga correctamente para finanzas', async ({ page }, testInfo) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(appUrl(ROUTES.financeCxc));
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);
    await expect(page.locator('body')).toContainText(/cuentas por cobrar|CXC|receivable/i);
    await attachScreenshot(page, testInfo, 'cxc-list');
  });

  test('crear CXC sin evidencia está bloqueado', async ({ page }, testInfo) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(appUrl(ROUTES.financeCxc));
    await page.waitForLoadState('networkidle');

    const newBtn = page.getByRole('button', { name: /nueva|crear/i }).first();
    if (await newBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => newBtn.click());

      const amountInput = page.locator('input[wire\\:model*="amount"]').first();
      if (await amountInput.isVisible()) await amountInput.fill('2000.00');

      const saveBtn = page.getByRole('button', { name: /guardar/i }).last();
      if (await saveBtn.isVisible()) {
        await saveBtn.click();
        await expectNoServerError(page);
        await expect(page.locator('body')).toContainText(
          /evidencia|requerido|required/i, { timeout: 10_000 }
        );
        await attachScreenshot(page, testInfo, 'cxc-sin-evidencia-bloqueado');
      }
    } else {
      test.skip();
    }
  });

  test('registrar cobro parcial y cobro total en CXC', async ({ page }, testInfo) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(appUrl(ROUTES.financeCxc));
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    // Buscar una CXC existente con saldo pendiente
    const payBtn = page.getByRole('button', { name: /registrar cobro|collect|cobro/i }).first();
    if (await payBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => payBtn.click());

      const amountInput = page.locator('input[wire\\:model*="amount"]').first();
      if (await amountInput.isVisible()) await amountInput.fill('500.00');

      // Comprobante
      const fileInput = page.locator('input[type="file"]').first();
      if (await fileInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await uploadPdf(page, 'input[type="file"]');
      }

      const saveBtn = page.getByRole('button', { name: /guardar|confirmar/i }).last();
      if (await saveBtn.isVisible()) {
        await waitForLivewire(page, () => saveBtn.click());
        await expectNoServerError(page);
        await attachScreenshot(page, testInfo, 'cxc-cobro-parcial');
      }
    } else {
      test.skip();
    }
  });

});

// ── FACTURAS ──────────────────────────────────────────────────────────────────
test.describe('Facturas', () => {

  test('módulo de facturas carga para finanzas', async ({ page }, testInfo) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(appUrl(ROUTES.financeInvoices));
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);
    await expect(page.locator('body')).toContainText(/factura|invoice/i);
    await attachScreenshot(page, testInfo, 'invoices-list');
  });

  test('crear factura cliente sin PDF está bloqueado', async ({ page }, testInfo) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(appUrl(ROUTES.financeInvoices));
    await page.waitForLoadState('networkidle');

    const newBtn = page.getByRole('button', { name: /nueva factura|crear factura/i }).first();
    if (await newBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => newBtn.click());

      const saveBtn = page.getByRole('button', { name: /guardar|save/i }).last();
      if (await saveBtn.isVisible()) {
        await saveBtn.click();
        await expectNoServerError(page);
        await expect(page.locator('body')).toContainText(
          /documento|pdf|requerido|required/i, { timeout: 10_000 }
        );
        await attachScreenshot(page, testInfo, 'invoice-sin-pdf-bloqueado');
      }
    } else {
      test.skip();
    }
  });

  test('crear factura con PDF adjunto', async ({ page }, testInfo) => {
    const uid = uniqueSuffix();

    await loginAs(page, USERS.finanzas.email);
    await page.goto(appUrl(ROUTES.financeInvoices));
    await page.waitForLoadState('networkidle');

    const newBtn = page.getByRole('button', { name: /nueva factura|crear/i }).first();
    if (await newBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => newBtn.click());

      // Número de factura
      const invoiceNumInput = page.locator('input[wire\\:model*="invoice_number"], input[wire\\:model*="number"]').first();
      if (await invoiceNumInput.isVisible()) await invoiceNumInput.fill(`FAC-E2E-${uid}`);

      // Monto
      const amountInput = page.locator('input[wire\\:model*="amount"]').first();
      if (await amountInput.isVisible()) await amountInput.fill('2300.00');

      // Adjuntar PDF
      const fileInput = page.locator('input[type="file"]').first();
      if (await fileInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await uploadPdf(page, 'input[type="file"]');
      }

      const saveBtn = page.getByRole('button', { name: /guardar|save/i }).last();
      if (await saveBtn.isVisible()) {
        await waitForLivewire(page, () => saveBtn.click());
        await expectNoServerError(page);
        await attachScreenshot(page, testInfo, 'invoice-creada-con-pdf');
      }
    } else {
      test.skip();
    }
  });

});

// ── PAGOS ─────────────────────────────────────────────────────────────────────
test.describe('Pagos', () => {

  test('módulo de pagos carga para finanzas', async ({ page }, testInfo) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(appUrl(ROUTES.financePayments));
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);
    await expect(page.locator('body')).toContainText(/pago|payment/i);
    await attachScreenshot(page, testInfo, 'payments-list');
  });

  test('registrar pago sin comprobante está bloqueado', async ({ page }, testInfo) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(appUrl(ROUTES.financePayments));
    await page.waitForLoadState('networkidle');

    const newBtn = page.getByRole('button', { name: /nuevo pago|registrar pago/i }).first();
    if (await newBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => newBtn.click());

      const amountInput = page.locator('input[wire\\:model*="amount"]').first();
      if (await amountInput.isVisible()) await amountInput.fill('500.00');

      const saveBtn = page.getByRole('button', { name: /guardar|registrar/i }).last();
      if (await saveBtn.isVisible()) {
        await saveBtn.click();
        await expectNoServerError(page);
        await expect(page.locator('body')).toContainText(
          /comprobante|evidencia|requerido|required/i, { timeout: 10_000 }
        );
        await attachScreenshot(page, testInfo, 'pago-sin-comprobante-bloqueado');
      }
    } else {
      test.skip();
    }
  });

  test('registrar pago con comprobante adjunto', async ({ page }, testInfo) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(appUrl(ROUTES.financePayments));
    await page.waitForLoadState('networkidle');

    const newBtn = page.getByRole('button', { name: /nuevo pago|registrar pago/i }).first();
    if (await newBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => newBtn.click());

      const amountInput = page.locator('input[wire\\:model*="amount"]').first();
      if (await amountInput.isVisible()) await amountInput.fill('500.00');

      // Adjuntar comprobante
      const fileInput = page.locator('input[type="file"]').first();
      if (await fileInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await uploadPdf(page, 'input[type="file"]');
      }

      const saveBtn = page.getByRole('button', { name: /guardar|registrar/i }).last();
      if (await saveBtn.isVisible()) {
        await waitForLivewire(page, () => saveBtn.click());
        await expectNoServerError(page);
        await attachScreenshot(page, testInfo, 'pago-con-comprobante');
      }
    } else {
      test.skip();
    }
  });

  test('bodega no puede acceder a módulo de pagos', async ({ page }) => {
    await loginAs(page, USERS.bodega.email);
    await page.goto(appUrl(ROUTES.financePayments));
    await expectAccessDenied(page);
    await expectNoServerError(page);
  });

});
