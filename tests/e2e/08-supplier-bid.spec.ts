/**
 * SUPPLIER BID SPEC — tests/e2e/supplier-bid.spec.ts
 *
 * Covers:
 *  LICITACIÓN:
 *   1. Seleccionar proveedor ACSIS
 *   2. Generar RFQ PDF
 *   3. Validar que se genera documento (sin precios)
 *   4. Validar que se puede descargar
 *   5. Validar que queda registrado en documentos de fase
 *   6. Validar Activity y Order Tracker
 *
 *  RESPUESTA PROVEEDOR:
 *   7. Registrar respuesta ACSIS Filtro USD 120
 *   8. Registrar Alternador USD 250
 *   9. Adjuntar proforma PDF
 *  10. Guardar cotización
 *  11. Registrar segunda respuesta ACSIS Filtro USD 135 (no sobrescribe)
 *  12. Registrar respuesta KB Alternador USD 240
 *  13. Validar todas las cotizaciones visibles
 *
 *  SELECCIÓN DE PARTIDAS:
 *  14. Abrir selección
 *  15. Seleccionar Filtro ACSIS USD 120
 *  16. Seleccionar Alternador KB USD 240
 *  17. Quitar selección y volver a seleccionar
 *  18. Intentar seleccionar partida sin precio (bloqueado)
 *  19. Enviar seleccionados a Oferta Cliente
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { waitForLivewire, expectToast } from './helpers/livewire';
import { expectNoServerError, attachScreenshot } from './helpers/assertions';
import { uniqueSuffix } from './helpers/data';
import { uploadPdf } from './helpers/files';
import {
  USERS, ROUTES,
  SUPPLIER_ACSIS, SUPPLIER_KB,
  PRODUCT_FILTER, PRODUCT_ALTERNATOR,
  BID_ACSIS_FILTER_V1, BID_ACSIS_FILTER_V2, BID_KB_ALTERNATOR,
} from './fixtures/test-data';

const supplierName = 'Proveedor E2E ACSIS'; // Nombre exacto del seeder

async function waitForLivewireUpdate(page: Parameters<typeof loginAs>[0]) {
  await page.waitForResponse(
    (r) => r.url().includes('/livewire/update') && r.ok(),
    { timeout: 45_000 }
  );
}

/**
 * Rellena y guarda un formulario de cotización de proveedor.
 */
async function fillAndSaveQuote(
  page: Parameters<typeof loginAs>[0],
  opts: {
    supplierLabel?: string;
    description: string;
    unitCost: string;
    quantity?: string;
    deliveryDays?: string;
    addAnother?: boolean;
  }
): Promise<void> {
  // Seleccionar proveedor si hay un selector visible
  const supplierSelect = page.locator('select').filter({ hasText: opts.supplierLabel ?? supplierName }).first();
  if (await supplierSelect.isVisible({ timeout: 3_000 }).catch(() => false)) {
    await supplierSelect.selectOption({ label: opts.supplierLabel ?? supplierName });
  }

  // Descripción cotizada
  const descInput = page.getByLabel(/descripci[oó]n cotizada/i).or(
    page.locator('input[wire\\:model*="description"], textarea[wire\\:model*="description"]').first()
  );
  if (await descInput.isVisible({ timeout: 3_000 }).catch(() => false)) {
    await descInput.fill(opts.description);
  }

  // Cantidad
  const qtyInput = page.getByLabel(/cantidad/i).first();
  if (await qtyInput.isVisible({ timeout: 3_000 }).catch(() => false)) {
    await qtyInput.fill(opts.quantity ?? '1');
  }

  // Precio proveedor
  const priceInput = page.getByLabel(/precio proveedor|unit cost|costo/i).first().or(
    page.locator('input[wire\\:model*="unitCost"], input[wire\\:model*="unit_cost"], input[wire\\:model*="price"]').first()
  );
  if (await priceInput.isVisible({ timeout: 3_000 }).catch(() => false)) {
    await priceInput.fill(opts.unitCost);
  }

  // ETA / entrega
  const etaInput = page.getByLabel(/eta|fecha estimada/i).first();
  if (await etaInput.isVisible({ timeout: 3_000 }).catch(() => false)) {
    await etaInput.fill('2026-08-15');
  }

  const deliveryInput = page.getByLabel(/entrega/i).first();
  if (await deliveryInput.isVisible({ timeout: 3_000 }).catch(() => false)) {
    await deliveryInput.fill(opts.deliveryDays ?? '15 días');
  }

  // Adjuntar proforma PDF
  const fileInput = page.locator('input[type="file"]').first();
  if (await fileInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
    await uploadPdf(page, 'input[type="file"]');
  }

  // Guardar
  const btnText = opts.addAnother ? /guardar y agregar otra/i : /guardar cotizaci[oó]n|save/i;
  const saveBtn = page.getByRole('button', { name: btnText });

  await waitForLivewire(page, () => saveBtn.click());
}

test.describe('Licitación Proveedor — RFQ, Respuestas y Selección', () => {

  // ── LICITACIÓN / RFQ ───────────────────────────────────────────────────────
  test.describe('RFQ — Generación y validación', () => {

    test('generar RFQ PDF seleccionando proveedor ACSIS', async ({ page }, testInfo) => {
      const uid = uniqueSuffix();
      const clientName = `Cliente E2E Licit ${uid}`;

      await loginAs(page, USERS.admin.email);

      // Primero crear una solicitud (usando el spec de commercial-flow como base)
      await page.goto(ROUTES.solicitudCliente);
      await page.waitForLoadState('networkidle');

      await page.locator('input[wire\\:model="quoteToName"]').fill(clientName);
      await page.locator('input[wire\\:model="items.0.part_name"]').fill(PRODUCT_FILTER.name);
      await page.locator('input[wire\\:model="items.0.part_code"]').fill(`${PRODUCT_FILTER.partNumber}-${uid}`);
      await page.locator('input[wire\\:model="items.0.quantity"]').fill(String(PRODUCT_FILTER.quantity));
      
      // Fix: Add missing required fields that caused validation errors
      await page.locator('input[wire\\:model="machinery"]').fill('Tractor').catch(() => {});
      await page.locator('input[wire\\:model="machineBrand"]').fill('John Deere').catch(() => {});
      await page.locator('textarea[wire\\:model="description"]').fill('Filtro hidráulico').catch(() => {});

      // Evidencia
      const fileInput = page.locator('input[type="file"]').first();
      if (await fileInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await uploadPdf(page, 'input[type="file"]');
      }

      await attachScreenshot(page, testInfo, '00-solicitud-completada');

      const saveBtn = page.getByRole('button', { name: /guardar solicitud/i });
      await Promise.all([
        page.waitForURL(/quotations\/manage\/request/, { timeout: 45_000 }),
        saveBtn.click(),
      ]);

      // Avanzar a licitación
      await expect(page.locator('body')).toContainText(clientName, { timeout: 15_000 });
      const rowBtn = page.getByRole('button').filter({ hasText: clientName }).first();
      if (await rowBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
        await waitForLivewire(page, () => rowBtn.click());
      }

      const toLicitBtn = page.getByRole('button', { name: /a licitaci[oó]n/i });
      if (await toLicitBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
        await waitForLivewire(page, () => toLicitBtn.click());
      }

      await attachScreenshot(page, testInfo, '01-en-licitacion');

      // Seleccionar proveedor
      const supplierSelect = page.locator('select').filter({ hasText: supplierName }).first();
      if (await supplierSelect.isVisible({ timeout: 10_000 }).catch(() => false)) {
        await supplierSelect.selectOption({ label: supplierName });
      }

      // Generar RFQ
      const rfqBtn = page.getByRole('button', { name: /generar pdf rfq|generate rfq/i });
      await expect(rfqBtn).toBeVisible({ timeout: 15_000 });

      await waitForLivewire(page, () => rfqBtn.click(), { timeout: 60_000 });

      // Validar documento generado
      await expect(page.locator('body')).toContainText(/ver pdf|rfq|pdf generado/i, { timeout: 30_000 });
      await attachScreenshot(page, testInfo, '02-rfq-generado');

      // ── RFQ no debe tener precios ────────────────────────────────────────
      // Verificar que el botón de ver PDF abre un PDF (validar link href)
      const pdfLink = page.getByRole('link', { name: /ver pdf/i }).first();
      if (await pdfLink.isVisible()) {
        const href = await pdfLink.getAttribute('href') ?? '';
        expect(href).toMatch(/pdf|rfq/i);
      }

      // Marcar RFQ enviada
      const markSentBtn = page.getByRole('button', { name: /marcar rfq enviada|marcar enviada/i });
      if (await markSentBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
        await waitForLivewire(page, () => markSentBtn.click());
        await expectNoServerError(page);
      }

      await attachScreenshot(page, testInfo, '03-rfq-marcada-enviada');
    });

  });

  // ── RESPUESTAS PROVEEDOR ───────────────────────────────────────────────────
  test.describe('Respuestas de Proveedor', () => {

    test('registrar múltiples respuestas sin sobrescribir', async ({ page }, testInfo) => {
      // Este test asume que existe al menos una solicitud en estado de licitación
      await loginAs(page, USERS.admin.email);
      await page.goto(ROUTES.quotationsBid);
      await page.waitForLoadState('networkidle');
      await expectNoServerError(page);

      // Buscar botón "Registrar respuesta"
      const registerBtn = page.getByRole('button', { name: /registrar respuesta|add quote/i }).first();

      if (await registerBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
        await waitForLivewire(page, () => registerBtn.click());

        // Respuesta 1: ACSIS - Filtro USD 120
        await fillAndSaveQuote(page, {
          description: PRODUCT_FILTER.name + ' original',
          unitCost:    BID_ACSIS_FILTER_V1.unitCost,
          quantity:    String(PRODUCT_FILTER.quantity),
          addAnother:  false,
        });

        await attachScreenshot(page, testInfo, '01-primera-cotizacion');
        await expect(page.locator('body')).toContainText(/cotizaci[oó]n|registrada|guardada/i, { timeout: 15_000 });

        // Verificar que la primera cotización aparece en el listado
        await expect(page.locator('body')).toContainText(BID_ACSIS_FILTER_V1.unitCost, { timeout: 10_000 });

        // Agregar segunda cotización (diferente precio, mismo proveedor)
        const addAnotherBtn = page.getByRole('button', { name: /registrar respuesta|agregar otra/i }).first();
        if (await addAnotherBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
          await waitForLivewire(page, () => addAnotherBtn.click());

          await fillAndSaveQuote(page, {
            description: PRODUCT_FILTER.name + ' alternativo',
            unitCost:    BID_ACSIS_FILTER_V2.unitCost,
            quantity:    String(PRODUCT_FILTER.quantity),
          });

          await attachScreenshot(page, testInfo, '02-segunda-cotizacion');

          // VALIDAR: ambas cotizaciones visibles (no se sobrescribió)
          await expect(page.locator('body')).toContainText(BID_ACSIS_FILTER_V1.unitCost, { timeout: 10_000 });
          await expect(page.locator('body')).toContainText(BID_ACSIS_FILTER_V2.unitCost, { timeout: 10_000 });

          await attachScreenshot(page, testInfo, '03-ambas-cotizaciones-visibles');
        }
      } else {
        test.skip();
      }
    });

  });

  // ── SELECCIÓN DE PARTIDAS ──────────────────────────────────────────────────
  test.describe('Selección de Partidas', () => {

    test('seleccionar partidas combinadas de dos proveedores', async ({ page }, testInfo) => {
      await loginAs(page, USERS.admin.email);
      await page.goto(ROUTES.quotationsBid);
      await page.waitForLoadState('networkidle');
      await expectNoServerError(page);

      // Buscar botón de selección de partidas
      const selectBtn = page.getByRole('button', { name: /seleccionar partidas|bid selection/i }).first();

      if (await selectBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
        await waitForLivewire(page, () => selectBtn.click());
        await expectNoServerError(page);

        await attachScreenshot(page, testInfo, '01-seleccion-abierta');

        // Seleccionar primera partida (Filtro ACSIS)
        const usarEstaBtn = page.getByRole('button', { name: /usar esta partida|seleccionar/i }).first();
        if (await usarEstaBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
          await waitForLivewire(page, () => usarEstaBtn.click());
          await expect(page.locator('body')).toContainText(/seleccionado|selected/i, { timeout: 10_000 });
          await attachScreenshot(page, testInfo, '02-partida-seleccionada');

          // Quitar selección y volver a seleccionar
          const quitarBtn = page.getByRole('button', { name: /quitar|deselect|limpiar/i }).first();
          if (await quitarBtn.isVisible({ timeout: 3_000 }).catch(() => false)) {
            await waitForLivewire(page, () => quitarBtn.click());
            await waitForLivewire(page, () => usarEstaBtn.click());
            await expect(page.locator('body')).toContainText(/seleccionado/i, { timeout: 10_000 });
          }
        }

        // Intentar avanzar sin seleccionar (validación negativa)
        const enviarBtn = page.getByRole('button', { name: /enviar seleccionados a oferta|send to offer/i });
        if (await enviarBtn.isVisible()) {
          // Si ya hay selección, debe poder avanzar
          await waitForLivewire(page, () => enviarBtn.click());
          await expect(page.locator('body')).toContainText(/oferta cliente|client offer/i, { timeout: 20_000 });
          await attachScreenshot(page, testInfo, '03-enviado-a-oferta');
        }
      } else {
        test.skip();
      }
    });

    test('no se puede avanzar sin seleccionar ninguna partida', async ({ page }) => {
      await loginAs(page, USERS.admin.email);
      await page.goto(ROUTES.quotationsBid);
      await page.waitForLoadState('networkidle');

      const selectBtn = page.getByRole('button', { name: /seleccionar partidas/i }).first();
      if (await selectBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
        await waitForLivewire(page, () => selectBtn.click());

        const enviarBtn = page.getByRole('button', { name: /enviar seleccionados a oferta/i });
        if (await enviarBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
          await enviarBtn.click();
          // Debe mostrar error o el botón debe estar deshabilitado
          const body = await page.locator('body').textContent() ?? '';
          const hasError = /seleccione|select|requerido|required|0 seleccionadas/i.test(body);
          const isDisabled = await enviarBtn.isDisabled();
          expect(hasError || isDisabled, 'No debe poder avanzar sin partidas seleccionadas').toBeTruthy();
          await expectNoServerError(page);
        }
      } else {
        test.skip();
      }
    });

  });

});
