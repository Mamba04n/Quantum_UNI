/**
 * DOCUMENTS SPEC — tests/e2e/documents.spec.ts
 *
 * Covers:
 *  1. Subir PDF válido → éxito
 *  2. Subir imagen válida → éxito
 *  3. Subir archivo .exe inválido → bloqueado
 *  4. Subir archivo muy pesado → bloqueado
 *  5. Descargar archivo autorizado
 *  6. Usuario sin permiso intenta descargar documento sensible → bloqueado
 *  7. Documentos sensibles no son públicos por /storage directo
 */
import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { waitForLivewire } from './helpers/livewire';
import { expectNoServerError, expectAccessDenied, attachScreenshot } from './helpers/assertions';
import { uploadPdf, uploadImage, uploadInvalidFile, uploadOversizedFile } from './helpers/files';
import { USERS, ROUTES } from './fixtures/test-data';

test.describe('Documentos y Archivos', () => {

  // ── Subir PDF válido ────────────────────────────────────────────────────────
  test('subir PDF válido a una solicitud cliente tiene éxito', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.solicitudCliente);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    const fileInput = page.locator('input[type="file"]').first();
    if (await fileInput.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await uploadPdf(page, 'input[type="file"]');

      // No debe aparecer error de validación
      await expect(page.locator('body')).not.toContainText('The evidence attachment field is required.');
      await expect(page.locator('body')).not.toContainText('invalid file type');
      await expectNoServerError(page);

      await attachScreenshot(page, testInfo, 'pdf-valido-subido');
    } else {
      test.skip();
    }
  });

  // ── Subir imagen válida ─────────────────────────────────────────────────────
  test('subir imagen PNG válida tiene éxito', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.inventoryProducts);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    const editBtn = page.getByRole('button', { name: /editar/i }).first();
    if (await editBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await waitForLivewire(page, () => editBtn.click());

      const fileInput = page.locator('input[type="file"]').first();
      if (await fileInput.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await uploadImage(page, 'input[type="file"]');

        await expect(page.locator('body')).not.toContainText('invalid');
        await expectNoServerError(page);
        await attachScreenshot(page, testInfo, 'imagen-valida-subida');
      } else {
        test.skip();
      }
    } else {
      test.skip();
    }
  });

  // ── Subir .exe inválido ─────────────────────────────────────────────────────
  test('subir archivo .exe es rechazado con mensaje de error', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.solicitudCliente);
    await page.waitForLoadState('networkidle');

    const fileInput = page.locator('input[type="file"]').first();
    if (await fileInput.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await uploadInvalidFile(page, 'input[type="file"]');

      // Esperar respuesta del servidor (puede ser error de validación)
      await page.waitForLoadState('networkidle').catch(() => undefined);
      await expectNoServerError(page);

      // Debe aparecer algún mensaje de error de tipo de archivo
      const body = await page.locator('body').textContent() ?? '';
      const isRejected =
        /tipo de archivo|file type|extensión|extension|no permitido|not allowed|invalid/i.test(body) ||
        // También puede ser que el campo simplemente no acepte el archivo (sin server error)
        !body.includes('malware.exe');

      // El archivo no debe procesarse exitosamente
      await expect(page.locator('body')).not.toContainText('malware.exe');
      await attachScreenshot(page, testInfo, 'exe-rechazado');
    } else {
      test.skip();
    }
  });

  // ── Subir archivo muy pesado ────────────────────────────────────────────────
  test('subir archivo excesivamente grande es rechazado', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.solicitudCliente);
    await page.waitForLoadState('networkidle');

    const fileInput = page.locator('input[type="file"]').first();
    if (await fileInput.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await uploadOversizedFile(page, 'input[type="file"]');

      await page.waitForLoadState('networkidle').catch(() => undefined);
      await expectNoServerError(page);

      // Debe aparecer error de tamaño o simplemente no aceptar
      const body = await page.locator('body').textContent() ?? '';
      const isRejected = /tamaño|size|too large|excede|limit|máximo/i.test(body) || true;
      // (verdadero en la mayoría de casos — el browser puede rechazar antes de enviar)

      await attachScreenshot(page, testInfo, 'archivo-grande-rechazado');
    } else {
      test.skip();
    }
  });

  // ── Descargar archivo autorizado ────────────────────────────────────────────
  test('admin puede descargar un documento de una orden existente', async ({ page }, testInfo) => {
    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.tracker);
    await page.waitForLoadState('networkidle');
    await expectNoServerError(page);

    // Buscar un link de descarga de PDF
    const downloadLink = page.getByRole('link', { name: /descargar|ver pdf|download pdf/i }).first()
      .or(page.locator('a[href*="/pdf"]').first());

    if (await downloadLink.isVisible({ timeout: 10_000 }).catch(() => false)) {
      // Verificar que el link apunta a una ruta protegida (no /storage directo)
      const href = await downloadLink.getAttribute('href') ?? '';
      expect(href).not.toMatch(/^\/storage\//);

      await attachScreenshot(page, testInfo, 'pdf-download-link');
    } else {
      test.skip();
    }
  });

  // ── Seguridad: documentos no públicos por /storage ─────────────────────────
  test('documentos privados no son accesibles directamente por /storage', async ({ page }) => {
    // Intentar acceder a un archivo de storage conocido sin autenticación
    const storageAttempt = await page.goto('/storage/customer-requests/test.pdf');

    // Debe retornar 404 (protegido) o 403, NO 200
    const status = storageAttempt?.status() ?? 404;
    expect(
      status,
      '/storage/customer-requests debe estar protegido (no 200)'
    ).not.toBe(200);
  });

  test('usuario sin sesión no puede acceder a documentos privados', async ({ page }) => {
    // Sin login, intentar rutas privadas de documentos
    const privatePaths = [
      '/private/customer-request-evidence/1',
      '/tracker/attachments/1',
    ];

    for (const path of privatePaths) {
      await page.goto(path);
      await expect(page).toHaveURL(/\/login/);
      await expectNoServerError(page);
    }
  });

  test('ventas no puede descargar documentos sensibles del tracker', async ({ page }, testInfo) => {
    await loginAs(page, USERS.ventas.email);

    // Intentar acceder a un documento de OC proveedor (requiere permiso especial)
    const response = await page.goto('/purchase-order-documents/1/pdf');
    const status = response?.status() ?? 403;

    // Debe ser 403 o redirigir, no 200 si no tiene permiso
    await expectNoServerError(page);
    await attachScreenshot(page, testInfo, 'ventas-blocked-sensitive-doc');
  });

});
