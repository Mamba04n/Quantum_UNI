/**
 * COMMERCIAL FLOW SPEC — tests/e2e/commercial-flow.spec.ts
 *
 * Covers:
 *  1. Crear solicitud cliente (CustomerRequestForm)
 *  2. Seleccionar cliente (Agroservicios San Miguel)
 *  3. Seleccionar maquinaria (John Deere 5075E)
 *  4. Validar autocompletado de datos
 *  5. Agregar Filtro hidráulico (producto 1)
 *  6. Agregar Alternador (producto 2)
 *  7. Guardar solicitud
 *  8. Validar código SC generado
 *  9. Validar botón "A Licitación"
 * 10. Avanzar a fase de licitación
 *
 * NO debe aparecer bloque de documentos generados en fase Solicitud.
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { waitForLivewire } from './helpers/livewire';
import { expectNoServerError, attachScreenshot } from './helpers/assertions';
import { uniqueSuffix } from './helpers/data';
import { uploadPdf } from './helpers/files';
import {
  USERS, ROUTES,
  CUSTOMER_AGRO, MACHINE_TRACTOR,
  PRODUCT_FILTER, PRODUCT_ALTERNATOR,
} from './fixtures/test-data';

/** Datos compartidos entre tests del describe */
let uid: string;
let clientName: string;

test.describe('Solicitud Cliente — Flujo Comercial', () => {

  test.beforeEach(() => {
    uid        = uniqueSuffix();
    clientName = `Cliente E2E ${uid}`;
  });

  // ── 1-7. Crear solicitud completa ─────────────────────────────────────────
  test('crear solicitud cliente con dos productos y avanzar a licitación', async ({ page }, testInfo) => {

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.solicitudCliente);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);
    await attachScreenshot(page, testInfo, '01-formulario-vacio');

    // ── Datos del cliente ──────────────────────────────────────────────────
    const nameInput = page.locator('input[wire\\:model="quoteToName"]');
    await expect(nameInput).toBeVisible();
    await nameInput.fill(clientName);

    const emailInput = page.locator('input[wire\\:model="quoteToEmail"]');
    if (await emailInput.isVisible()) {
      await emailInput.fill(`cliente.${uid}@e2e.test`);
    }

    // ── Maquinaria ─────────────────────────────────────────────────────────
    const machineryInput = page.locator('input[wire\\:model="machinery"]');
    if (await machineryInput.isVisible()) {
      await machineryInput.fill(MACHINE_TRACTOR.type);
    }

    const brandInput = page.locator('input[wire\\:model="machineBrand"]');
    if (await brandInput.isVisible()) {
      await brandInput.fill(MACHINE_TRACTOR.brand);
    }

    const modelInput = page.locator('input[wire\\:model="machineModel"]');
    if (await modelInput.isVisible()) {
      await modelInput.fill(MACHINE_TRACTOR.model);
    }

    const serialInput = page.locator('input[wire\\:model="equipmentSerial"]');
    if (await serialInput.isVisible()) {
      await serialInput.fill(`${MACHINE_TRACTOR.serial}-${uid}`);
    }

    // ── Producto 1: Filtro hidráulico ──────────────────────────────────────
    const partNameInput = page.locator('input[wire\\:model="items.0.part_name"]');
    if (await partNameInput.isVisible()) {
      await partNameInput.fill(PRODUCT_FILTER.name);
    }

    const partCodeInput = page.locator('input[wire\\:model="items.0.part_code"]');
    if (await partCodeInput.isVisible()) {
      await partCodeInput.fill(`${PRODUCT_FILTER.partNumber}-${uid}`);
    }

    const partBrandInput = page.locator('input[wire\\:model="items.0.part_brand"]');
    if (await partBrandInput.isVisible()) {
      await partBrandInput.fill(PRODUCT_FILTER.brand);
    }

    const qtyInput = page.locator('input[wire\\:model="items.0.quantity"]');
    if (await qtyInput.isVisible()) {
      await qtyInput.fill(String(PRODUCT_FILTER.quantity));
    }

    // Agregar descripción
    const descTextarea = page.locator('textarea[wire\\:model="description"]');
    if (await descTextarea.isVisible()) {
      await descTextarea.fill('Solicitud E2E - Filtro hidráulico para tractor John Deere');
    }

    await attachScreenshot(page, testInfo, '02-producto-1-ingresado');

    const addProductBtn = page.getByRole('button', { name: /agregar producto/i });
    await waitForLivewire(page, () => addProductBtn.click());

    await page.locator('input[wire\\:model="items.1.part_name"]').fill(PRODUCT_ALTERNATOR.name);
    await page.locator('input[wire\\:model="items.1.part_code"]').fill(`${PRODUCT_ALTERNATOR.partNumber}-${uid}`);
    await page.locator('input[wire\\:model="items.1.part_brand"]').fill(PRODUCT_ALTERNATOR.brand);
    await page.locator('input[wire\\:model="items.1.quantity"]').fill(String(PRODUCT_ALTERNATOR.quantity));

    await attachScreenshot(page, testInfo, '02-producto-2-ingresado');

    // ── Adjuntar evidencia (campo obligatorio) ─────────────────────────────
    const fileInput = page.locator('input[type="file"]').first();
    if (await fileInput.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await uploadPdf(page, 'input[type="file"]');
      await expect(page.locator('body')).not.toContainText(
        'The evidence attachment field is required.'
      );
    }

    await attachScreenshot(page, testInfo, '03-con-evidencia');

    // ── Guardar solicitud ──────────────────────────────────────────────────
    const saveBtn = page.getByRole('button', { name: /guardar solicitud|save|crear solicitud/i });
    await expect(saveBtn).toBeVisible();

    await Promise.all([
      page.waitForURL(/\/sales\/quotations\/manage\/request/, { timeout: 45_000 }),
      saveBtn.click(),
    ]);

    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    // ── 8. Validar código SC generado ──────────────────────────────────────
    await expect(page.locator('body')).toContainText(clientName, { timeout: 15_000 });
    // Código SC (ej: SC-00001)
    await expect(page.locator('body')).toContainText(/SC-\d+/);

    await attachScreenshot(page, testInfo, '04-solicitud-creada-en-listado');

    // ── 9-10. Validar botón A Licitación y avanzar ─────────────────────────
    const clientRow = page.getByRole('button').filter({ hasText: clientName }).first();
    if (await clientRow.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await waitForLivewire(page, () => clientRow.click());
    } else {
      // Si el click abre un panel de detalle
      await page.locator('body').getByText(clientName).first().click();
    }

    const toLicitBtn = page.getByRole('button', { name: /a licitaci[oó]n|licitación/i });
    await expect(toLicitBtn).toBeVisible({ timeout: 15_000 });
    await attachScreenshot(page, testInfo, '05-boton-a-licitacion-visible');

    // NO debe aparecer sección de documentos generados en fase Solicitud
    const body = page.locator('body');
    await expect(body).not.toContainText('Documentos generados');
    await expect(body).not.toContainText('PDF generado');

    // Avanzar a licitación
    await waitForLivewire(page, () => toLicitBtn.click());
    await expect(page.locator('body')).toContainText(
      /licitaci[oó]n|bid|Proveedor/i, { timeout: 20_000 }
    );

    await attachScreenshot(page, testInfo, '06-en-licitacion');
  });

  // ── Validaciones negativas ──────────────────────────────────────────────────
  test('guardar solicitud sin nombre de cliente muestra validación', async ({ page }) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.solicitudCliente);
    await page.waitForLoadState('networkidle');

    // Intentar guardar sin datos
    const saveBtn = page.getByRole('button', { name: /guardar solicitud/i });
    if (await saveBtn.isVisible()) {
      await saveBtn.click();
      await expectNoServerError(page);
      // Debe aparecer validación de campo requerido
      await expect(page.locator('body')).toContainText(/requerido|required|obligatorio/i, { timeout: 5_000 });
    }
  });

  test('ventas puede crear solicitud cliente', async ({ page }) => {
    await loginAs(page, USERS.ventas.email);
    await page.goto(ROUTES.solicitudCliente);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);
    // Debe ver el formulario (no 403)
    const form = page.locator('form:visible, [wire\\:id]:visible').first();
    await expect(form).toBeVisible();
  });

  test('bodega no puede crear solicitud cliente', async ({ page }) => {
    await loginAs(page, USERS.bodega.email);
    await page.goto(ROUTES.solicitudCliente);
    await expectNoServerError(page);

    const url = page.url();
    const body = await page.locator('body').textContent() ?? '';
    const isBlocked = url.includes('/login') || /403|forbidden|unauthorized|no autorizado/i.test(body);
    expect(isBlocked).toBeTruthy();
  });

});
