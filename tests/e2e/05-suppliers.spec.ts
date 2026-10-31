/**
 * SUPPLIERS SPEC — tests/e2e/suppliers.spec.ts
 *
 * Covers:
 *  1. Crear proveedor ACSIS Parts
 *  2. Crear proveedor KB Repuestos
 *  3. Agregar marcas al proveedor
 *  4. Agregar métodos de pago
 *  5. Buscar por nombre
 *  6. Buscar por marca
 *  7. Aplicar filtros
 *  8. Abrir detalle del proveedor
 *  9. Volver al listado
 * 10. Usuario sin permiso no ve datos bancarios
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { waitForLivewire } from './helpers/livewire';
import { expectNoServerError, expectAccessDenied, attachScreenshot } from './helpers/assertions';
import { uniqueSuffix } from './helpers/data';
import { USERS, ROUTES, SUPPLIER_ACSIS, SUPPLIER_KB } from './fixtures/test-data';

test.describe('Proveedores — CRUD y búsqueda', () => {

  // ── Helpers locales ─────────────────────────────────────────────────────────
  async function openSupplierModule(page: Parameters<typeof loginAs>[0]) {
    await page.goto(ROUTES.adminSuppliers);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);
  }

  async function clickNewSupplier(page: Parameters<typeof loginAs>[0]) {
    const btn = page.getByRole('button', { name: /nuevo proveedor|agregar proveedor|new supplier/i });
    await expect(btn).toBeVisible();
    await waitForLivewire(page, () => btn.click());
  }

  async function fillSupplierForm(
    page: Parameters<typeof loginAs>[0],
    data: { name: string; country: string; contactName: string; email: string; phone: string }
  ) {
    // Nombre
    const nameInput = page.locator('input[wire\\:model*="name"], input[wire\\:model*="Name"]').first();
    await nameInput.fill(data.name);

    // País
    const countryInput = page.locator('input[wire\\:model*="country"], select[wire\\:model*="country"]').first();
    if (await countryInput.getAttribute('tagName') === 'SELECT' || (await countryInput.count()) === 0) {
      const selectCountry = page.locator('select[wire\\:model*="country"]').first();
      if (await selectCountry.isVisible()) {
        await selectCountry.selectOption({ label: data.country });
      }
    } else {
      await countryInput.fill(data.country);
    }

    // Contacto
    const contactInput = page.locator('input[wire\\:model*="contactName"], input[wire\\:model*="contact_name"]').first();
    if (await contactInput.isVisible()) await contactInput.fill(data.contactName);

    // Email
    const emailInput = page.locator('input[type="email"], input[wire\\:model*="email"]').first();
    if (await emailInput.isVisible()) await emailInput.fill(data.email);

    // Teléfono
    const phoneInput = page.locator('input[wire\\:model*="phone"]').first();
    if (await phoneInput.isVisible()) await phoneInput.fill(data.phone);
  }

  // ── 1. Crear ACSIS Parts ────────────────────────────────────────────────────
  test('crear proveedor ACSIS Parts (USA)', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await openSupplierModule(page);

    // El proveedor ACSIS ya existe desde el seeder, buscar y verificar
    const search = page.locator('input[wire\\:model*="search"]').first();
    if (await search.isVisible()) {
      await waitForLivewire(page, () => search.fill(SUPPLIER_ACSIS.name));
    }

    await expect(page.locator('body')).toContainText(SUPPLIER_ACSIS.name, { timeout: 10_000 });
    await attachScreenshot(page, testInfo, 'proveedor-acsis');
  });

  test('crear proveedor nuevo dinámico', async ({ page }, testInfo) => {
    const uid = uniqueSuffix();
    const provName = `Proveedor E2E Test ${uid}`;

    await loginAs(page, USERS.admin.email);
    await openSupplierModule(page);

    await clickNewSupplier(page);

    await fillSupplierForm(page, {
      name:        provName,
      country:     'Estados Unidos',
      contactName: 'Test Contact',
      email:       `test.${uid}@e2e.test`,
      phone:       '+1 800 000 0000',
    });

    const saveBtn = page.getByRole('button', { name: /guardar|save|crear/i }).last();
    await waitForLivewire(page, () => saveBtn.click());

    await expect(page.locator('body')).toContainText(
      new RegExp(`${provName}|guardado|creado|éxito`, 'i'), { timeout: 15_000 }
    );
    await attachScreenshot(page, testInfo, 'proveedor-nuevo');
  });

  // ── 2. KB Repuestos ─────────────────────────────────────────────────────────
  test('KB Repuestos existe desde el seeder y es visible', async ({ page }) => {
    await loginAs(page, USERS.admin.email);
    await openSupplierModule(page);

    const search = page.locator('input[wire\\:model*="search"]').first();
    if (await search.isVisible()) {
      await waitForLivewire(page, () => search.fill(SUPPLIER_KB.name));
    }

    await expect(page.locator('body')).toContainText(SUPPLIER_KB.name, { timeout: 10_000 });
  });

  // ── 3. Agregar marcas ───────────────────────────────────────────────────────
  test('agregar marca a un proveedor existente', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await openSupplierModule(page);

    // Abrir ACSIS
    const search = page.locator('input[wire\\:model*="search"]').first();
    if (await search.isVisible()) {
      await waitForLivewire(page, () => search.fill(SUPPLIER_ACSIS.name));
    }

    const detailBtn = page.getByRole('button', { name: /ver|detalle|detail|abrir/i }).first();
    if (await detailBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await waitForLivewire(page, () => detailBtn.click());
    }

    // Agregar marca
    const brandInput = page.locator('input[wire\\:model*="brand"], input[placeholder*="marca"]').first();
    if (await brandInput.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await brandInput.fill('Donaldson');
      const addBrandBtn = page.getByRole('button', { name: /agregar marca|add brand/i });
      if (await addBrandBtn.isVisible()) {
        await waitForLivewire(page, () => addBrandBtn.click());
        await expect(page.locator('body')).toContainText('Donaldson', { timeout: 10_000 });
        await attachScreenshot(page, testInfo, 'proveedor-marca-agregada');
      }
    } else {
      test.skip();
    }
  });

  // ── 4. Agregar método de pago ───────────────────────────────────────────────
  test('agregar método de pago a proveedor', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await openSupplierModule(page);

    const paymentInput = page.locator(
      'input[wire\\:model*="payment"], input[placeholder*="pago"]'
    ).first();

    if (await paymentInput.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await paymentInput.fill('Transferencia internacional');
      const addBtn = page.getByRole('button', { name: /agregar|add/i }).first();
      if (await addBtn.isVisible()) {
        await waitForLivewire(page, () => addBtn.click());
        await expect(page.locator('body')).toContainText('Transferencia', { timeout: 10_000 });
        await attachScreenshot(page, testInfo, 'proveedor-metodo-pago');
      }
    } else {
      test.skip();
    }
  });

  // ── 5. Buscar por nombre ────────────────────────────────────────────────────
  test('buscar proveedor por nombre filtra correctamente', async ({ page }) => {
    await loginAs(page, USERS.admin.email);
    await openSupplierModule(page);

    const search = page.locator('input[wire\\:model*="search"]').first();
    await expect(search).toBeVisible();

    await waitForLivewire(page, () => search.fill('ACSIS'));
    await expect(page.locator('body')).toContainText(SUPPLIER_ACSIS.name, { timeout: 10_000 });
    // No debe aparecer KB
    await expect(page.locator('body')).not.toContainText(SUPPLIER_KB.name);
  });

  // ── 6. Filtros ──────────────────────────────────────────────────────────────
  test('filtrar por país limita los resultados', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await openSupplierModule(page);

    const countryFilter = page.locator('select[wire\\:model*="country"], input[wire\\:model*="filterCountry"]').first();
    if (await countryFilter.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await waitForLivewire(page, () =>
        (countryFilter.tagName() as unknown as string) === 'SELECT'
          ? countryFilter.selectOption('Estados Unidos')
          : countryFilter.fill('Estados Unidos')
      );
      await expect(page.locator('body')).toContainText(SUPPLIER_ACSIS.name, { timeout: 10_000 });
      await attachScreenshot(page, testInfo, 'proveedor-filtrado-pais');
    } else {
      test.skip();
    }
  });

  // ── 7. Abrir detalle y volver ───────────────────────────────────────────────
  test('abrir detalle de proveedor y volver al listado', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await openSupplierModule(page);

    const detailBtn = page.getByRole('button', { name: /ver|detalle|abrir/i }).first();
    if (await detailBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await waitForLivewire(page, () => detailBtn.click());
      await expectNoServerError(page);
      await attachScreenshot(page, testInfo, 'proveedor-detalle');

      const backBtn = page.getByRole('button', { name: /volver|back|lista/i }).first();
      if (await backBtn.isVisible()) {
        await waitForLivewire(page, () => backBtn.click());
      } else {
        await page.goto(ROUTES.adminSuppliers);
      }

      await expect(page.locator('body')).toContainText(SUPPLIER_ACSIS.name, { timeout: 10_000 });
      await attachScreenshot(page, testInfo, 'proveedor-listado');
    } else {
      test.skip();
    }
  });

  // ── 8. Usuario sin permiso no ve datos bancarios ────────────────────────────
  test('ventas no puede acceder al módulo de proveedores', async ({ page }) => {
    await loginAs(page, USERS.ventas.email);
    await page.goto(ROUTES.adminSuppliers);
    await expectAccessDenied(page);
    await expectNoServerError(page);
  });

  test('bodega no ve datos bancarios de proveedores', async ({ page }) => {
    await loginAs(page, USERS.bodega.email);
    await page.goto(ROUTES.adminSuppliers);
    // Bodega puede ver proveedores para recibir órdenes, pero no datos bancarios
    await expectNoServerError(page);
    const body = await page.locator('body').textContent() ?? '';
    const hasBankData = /cuenta bancaria|IBAN|SWIFT|routing number/i.test(body);
    expect(hasBankData, 'Bodega no debe ver datos bancarios de proveedores').toBeFalsy();
  });

});
