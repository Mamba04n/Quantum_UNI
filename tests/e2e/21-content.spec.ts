import { expect, test } from '@playwright/test';
import {
  cleanupE2E,
  clickAndWaitForEither,
  expectAccessDenied,
  expectNoServerError,
  loginAs,
  setupBase,
  uploadPdf,
} from './helpers';
import { uniqueSuffix } from './helpers/data';
import { ROUTES, USERS } from './fixtures/test-data';

test.describe('Blog tecnico y catalogos', () => {
  test.beforeAll(async ({ request }) => {
    await setupBase(request);
  });

  test.afterEach(async ({ request }) => {
    await cleanupE2E(request);
  });

  test('soporte puede acceder al modulo de blog tecnico', async ({ page }) => {
    await loginAs(page, USERS.soporte.email);
    await page.goto(ROUTES.webBlog);
    await expectNoServerError(page);

    await expect(page.locator('body')).toContainText(/blog|articulo|artículo|maquinaria/i);
  });

  test('soporte puede crear entrada de blog tecnico', async ({ page }) => {
    const title = `Mantenimiento Tractor E2E ${uniqueSuffix()}`;

    await loginAs(page, USERS.soporte.email);
    await page.goto(ROUTES.webBlog);
    await expectNoServerError(page);

    await clickAndWaitForEither(page, page.getByTestId('content-blog-create-button'), {
      livewire: true,
      locator: page.getByTestId('content-blog-title-input'),
      actionName: 'abrir formulario blog tecnico',
    });

    await page.getByTestId('content-blog-title-input').fill(title);
    await page.getByTestId('content-blog-body-input').fill('Guia tecnica E2E para mantenimiento preventivo.');

    await clickAndWaitForEither(page, page.getByTestId('content-blog-save-button'), {
      livewire: true,
      text: /articulo guardado|guardado correctamente|guardado/i,
      actionName: 'guardar blog tecnico',
    });

    await expectNoServerError(page);
    await expect(page.locator('body')).toContainText(title);
  });

  test('soporte puede crear catalogo y adjuntar archivo', async ({ page }) => {
    const name = `Catalogo Filtros E2E ${uniqueSuffix()}`;

    await loginAs(page, USERS.soporte.email);
    await page.goto(ROUTES.webCatalogos);
    await expectNoServerError(page);

    await clickAndWaitForEither(page, page.getByTestId('catalog-create-button'), {
      livewire: true,
      locator: page.getByTestId('catalog-name-input'),
      actionName: 'abrir formulario catalogo',
    });

    await page.getByTestId('catalog-name-input').fill(name);
    await uploadPdf(page, '[data-testid="catalog-file-input"]');

    await clickAndWaitForEither(page, page.getByTestId('catalog-save-button'), {
      livewire: true,
      text: /catalogo guardado|catálogo guardado|guardado correctamente|guardado/i,
      timeout: 45_000,
      actionName: 'guardar catalogo con archivo',
    });

    await expectNoServerError(page);
    await expect(page.locator('body')).toContainText(name);
  });

  test('ventas no puede acceder al blog tecnico', async ({ page }) => {
    await loginAs(page, USERS.ventas.email);
    await page.goto(ROUTES.webBlog);
    await expectAccessDenied(page);
  });

  test('bodega no puede acceder al blog tecnico', async ({ page }) => {
    await loginAs(page, USERS.bodega.email);
    await page.goto(ROUTES.webBlog);
    await expectAccessDenied(page);
  });

  test('cliente externo no puede acceder al blog tecnico', async ({ page }) => {
    await loginAs(page, USERS.cliente.email);
    await page.goto(ROUTES.webBlog);
    await expectAccessDenied(page);
  });
});
