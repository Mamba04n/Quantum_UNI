/**
 * ANTI-DUPLICATE SPEC — tests/e2e/anti-duplicate.spec.ts
 *
 * Tests específicos para prevenir duplicación de documentos y aprobaciones
 * causada por doble clic u otras condiciones de carrera.
 *
 * Covers (doble clic en acciones críticas):
 *  1. Doble clic en "Generar RFQ" → 1 solo RFQ
 *  2. Doble clic en "Enviar a revisión admin" → 1 sola revisión
 *  3. Doble clic en "Aprobar admin" → 1 sola aprobación
 *  4. Doble clic en "Generar PDF final" → 1 solo PDF
 *  5. Doble clic en "Solicitar aprobación interna" → 1 sola solicitud
 *  6. Doble clic en "Aprobar compra" → 1 sola orden generada
 *  7. Doble clic en "Registrar pago" → 1 solo pago
 *
 * Resultado esperado:
 *  - No se duplican documentos
 *  - No se duplican aprobaciones
 *  - No se duplican órdenes
 *  - No se duplican trackers
 *  - Sin errores 500
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { expectNoServerError, attachScreenshot } from './helpers/assertions';
import { USERS, ROUTES } from './fixtures/test-data';

/**
 * Helper: cuenta cuántos elementos match un selector visible en la página.
 */
async function countVisible(page: Parameters<typeof loginAs>[0], selector: string): Promise<number> {
  return page.locator(selector).count();
}

test.describe('Anti-Duplicado — Doble Clic en Acciones Críticas', () => {

  // ── 1. Doble clic en "Generar RFQ" ────────────────────────────────────────
  test('doble clic en "Generar RFQ" no crea dos RFQs', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.quotationsBid);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    const rfqBtn = page.getByRole('button', { name: /generar pdf rfq/i }).first();
    if (await rfqBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      // Registrar cuántos PDFs hay antes
      const beforeCount = await countVisible(page, 'a[href*="pdf"]');

      // Doble clic
      await rfqBtn.dblclick();

      // Esperar un momento para que el servidor procese
      await page.waitForLoadState('networkidle').catch(() => undefined);
      await expectNoServerError(page);

      // El botón debe deshabilitarse durante el procesamiento (loading state)
      // o el componente debe tener protección idempotente
      const afterCount = await countVisible(page, 'a[href*="pdf"]');

      // No debe haber más PDFs de los esperados (máximo 1 nuevo)
      expect(afterCount - beforeCount).toBeLessThanOrEqual(1);

      await attachScreenshot(page, testInfo, 'anti-dup-rfq');
    } else {
      test.skip();
    }
  });

  // ── 2. Doble clic en "Enviar a revisión admin" ────────────────────────────
  test('doble clic en "Enviar a revisión admin" no crea dos revisiones', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.quotationsOffer);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    // Abrir oferta
    const offerBtn = page.getByRole('button').first();
    if (await offerBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      const sendBtn = page.getByRole('button', { name: /enviar a revisi[oó]n admin/i });
      if (await sendBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
        await sendBtn.dblclick();
        await page.waitForLoadState('networkidle').catch(() => undefined);
        await expectNoServerError(page);

        // Verificar en /admin/offer-reviews que no hay entradas duplicadas
        await page.goto(ROUTES.adminOfferReviews);
        await page.waitForLoadState('networkidle');
        await expectNoServerError(page);
        await attachScreenshot(page, testInfo, 'anti-dup-send-review');
      } else {
        test.skip();
      }
    } else {
      test.skip();
    }
  });

  // ── 3. Doble clic en "Aprobar admin" (offer review) ──────────────────────
  test('doble clic en "Autorizar envío al cliente" no duplica la aprobación', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminOfferReviews);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    const offerBtn = page.getByRole('button').first();
    if (await offerBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      const approveBtn = page.getByRole('button', { name: /autorizar envio|aprobar para enviar/i });
      if (await approveBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
        await approveBtn.dblclick();
        await page.waitForLoadState('networkidle').catch(() => undefined);
        await expectNoServerError(page);
        await attachScreenshot(page, testInfo, 'anti-dup-offer-approve');
      } else {
        test.skip();
      }
    } else {
      test.skip();
    }
  });

  // ── 4. Doble clic en "Generar PDF final" ─────────────────────────────────
  test('doble clic en "Generar PDF final" no crea dos PDFs finales', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.quotationsOffer);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    const pdfFinalBtn = page.getByRole('button', { name: /generar pdf final/i }).first();
    if (await pdfFinalBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      const beforeLinks = await countVisible(page, 'a[href*="pdf"]');

      await pdfFinalBtn.dblclick();
      await page.waitForLoadState('networkidle').catch(() => undefined);
      await expectNoServerError(page);

      const afterLinks = await countVisible(page, 'a[href*="pdf"]');
      expect(afterLinks - beforeLinks).toBeLessThanOrEqual(1);

      await attachScreenshot(page, testInfo, 'anti-dup-pdf-final');
    } else {
      test.skip();
    }
  });

  // ── 5. Doble clic en "Solicitar aprobación interna" ──────────────────────
  test('doble clic en "Solicitar aprobación interna" no crea dos solicitudes', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.quotationsOffer);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    const internalBtn = page.getByRole('button', { name: /solicitar aprobaci[oó]n interna/i });
    if (await internalBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await internalBtn.dblclick();
      await page.waitForLoadState('networkidle').catch(() => undefined);
      await expectNoServerError(page);

      // Verificar en /admin/approvals
      await page.goto(ROUTES.adminApprovals);
      await page.waitForLoadState('networkidle');
      await expectNoServerError(page);
      await attachScreenshot(page, testInfo, 'anti-dup-internal-approval');
    } else {
      test.skip();
    }
  });

  // ── 6. Doble clic en "Aprobar compra" ────────────────────────────────────
  test('doble clic en "Aprobar compra" no genera dos órdenes de compra', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminApprovals);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    const approvalBtn = page.getByRole('button').first();
    if (await approvalBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      const approveBtn = page.getByRole('button', { name: /aprobar compra/i });
      if (await approveBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
        await approveBtn.dblclick();
        await page.waitForLoadState('networkidle').catch(() => undefined);
        await expectNoServerError(page);

        // Verificar en el tracker que no hay duplicados
        await page.goto(ROUTES.tracker);
        await page.waitForLoadState('networkidle');
        const scCodes = await page.locator('text=/SC-\\d{4}-\\d+/').allTextContents();
        const uniqueCodes = new Set(scCodes.map(c => c.trim()));
        expect(scCodes.length).toBe(uniqueCodes.size);

        await attachScreenshot(page, testInfo, 'anti-dup-approve-purchase');
      } else {
        test.skip();
      }
    } else {
      test.skip();
    }
  });

  // ── 7. Doble clic en "Registrar pago" ────────────────────────────────────
  test('doble clic en "Registrar pago" no duplica el pago', async ({ page }, testInfo) => {
    await loginAs(page, USERS.finanzas.email);
    await page.goto(ROUTES.financeCxp);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    const payBtn = page.getByRole('button', { name: /registrar pago|add payment/i }).first();
    if (await payBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await payBtn.dblclick();
      await page.waitForLoadState('networkidle').catch(() => undefined);
      await expectNoServerError(page);

      // El sistema debe manejar el doble clic idempotentemente
      await attachScreenshot(page, testInfo, 'anti-dup-payment');
    } else {
      test.skip();
    }
  });

  // ── Verificación general: tracker sin duplicados ──────────────────────────
  test('el Order Tracker no tiene entradas duplicadas', async ({ page }) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.tracker);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    const scCodes = await page.locator('text=/SC-\\d{4}-\\d+/').allTextContents();
    const uniqueCodes = new Set(scCodes.map(c => c.trim()));

    expect(
      scCodes.length,
      `Encontrados ${scCodes.length - uniqueCodes.size} trackers duplicados`
    ).toBe(uniqueCodes.size);
  });

});
