import { expect, test, type Page } from '@playwright/test';
import {
  cleanupE2E,
  clickAndWaitForEither,
  createClientOfferPendingReview,
  createClientOfferReadyForReview,
  expectNoServerError,
  loginAs,
  setupBase,
  waitForLivewireAction,
  attachScreenshot,
} from './helpers';
import { PRODUCT_ALTERNATOR, PRODUCT_FILTER, ROUTES, TAX_PERCENT, USERS } from './fixtures/test-data';

async function openOffer(page: Page, quotationId: number) {
  await page.goto(ROUTES.quotationsOffer);
  await expectNoServerError(page);

  const row = page.getByTestId(`quotation-row-${quotationId}`);
  await expect(row).toBeVisible();
  await clickAndWaitForEither(page, row, {
    livewire: true,
    locator: page.getByTestId('offer-preview-button'),
    actionName: 'abrir oferta cliente',
  });
}

test.describe('Oferta Cliente', () => {
  test.beforeAll(async ({ request }) => {
    await setupBase(request);
  });

  test.afterEach(async ({ request }) => {
    await cleanupE2E(request);
  });

  test('ventas edita precios cliente, aplica IVA y conserva costos proveedor no editables', async ({ page, request }, testInfo) => {
    const fixture = await createClientOfferReadyForReview(request);

    await loginAs(page, USERS.ventas.email);
    await openOffer(page, fixture.quotationId);

    await attachScreenshot(page, testInfo, '01-oferta-cliente-borrador');

    await expect(page.locator('input[wire\\:model*="supplierCost"]:not([readonly])')).toHaveCount(0);
    await expect(page.locator('input[wire\\:model*="providerCost"]:not([readonly])')).toHaveCount(0);

    const [filterItemId, alternatorItemId] = fixture.itemIds;
    await waitForLivewireAction(page, async () => {
      const input = page.getByTestId(`offer-client-price-input-${filterItemId}`);
      await input.fill(String(PRODUCT_FILTER.clientPrice));
      await input.blur();
    }, { actionName: 'actualizar precio filtro' });

    await waitForLivewireAction(page, async () => {
      const input = page.getByTestId(`offer-client-price-input-${alternatorItemId}`);
      await input.fill(String(PRODUCT_ALTERNATOR.clientPrice));
      await input.blur();
    }, { actionName: 'actualizar precio alternador' });

    await page.getByTestId('offer-tax-input').fill(String(TAX_PERCENT));
    await clickAndWaitForEither(page, page.getByTestId('offer-save-tax-button'), {
      livewire: true,
      text: /iva|impuesto|guardado|subtotal|total/i,
      actionName: 'guardar IVA oferta cliente',
    });

    await expect(page.locator('body')).toContainText(/subtotal/i);
    await expect(page.locator('body')).toContainText(/iva|impuesto/i);
    await expect(page.locator('body')).toContainText(/total/i);

    await attachScreenshot(page, testInfo, '02-oferta-cliente-precios-calculados');
  });

  test('preview PDF es vista previa y no documento oficial final', async ({ page, request }) => {
    const fixture = await createClientOfferReadyForReview(request);

    await loginAs(page, USERS.ventas.email);
    await openOffer(page, fixture.quotationId);

    const [previewPage] = await Promise.all([
      page.context().waitForEvent('page').catch(() => null),
      page.getByTestId('offer-preview-button').click(),
    ]);
    const target = previewPage ?? page;
    await target.waitForLoadState('networkidle').catch(() => undefined);
    await expectNoServerError(target);

    expect(target.url()).toMatch(/preview|offers/i);
    if (previewPage) {
      await previewPage.close();
    }
  });

  test('ventas envia oferta a revision admin usando la oferta preparada', async ({ page, request }, testInfo) => {
    const fixture = await createClientOfferReadyForReview(request);

    await loginAs(page, USERS.ventas.email);
    await openOffer(page, fixture.quotationId);

    await page.getByTestId('offer-review-notes-textarea').fill('Oferta lista para revision admin. E2E.');
    await clickAndWaitForEither(page, page.getByTestId('offer-send-review-button'), {
      livewire: true,
      text: /revision admin|pendiente|autorizacion|revisi[oó]n/i,
      actionName: 'enviar oferta a revision admin',
    });

    await expectNoServerError(page);
    await attachScreenshot(page, testInfo, '03-oferta-cliente-enviada-a-revision');
  });

  test('admin ve revision pendiente y aprueba oferta', async ({ page, request }, testInfo) => {
    const fixture = await createClientOfferPendingReview(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminOfferReviews);
    await expectNoServerError(page);

    await clickAndWaitForEither(page, page.getByTestId(`offer-review-row-${fixture.reviewId}`), {
      livewire: true,
      locator: page.getByTestId('offer-review-approve-button'),
      actionName: 'abrir revision de oferta',
    });

    await clickAndWaitForEither(page, page.getByTestId('offer-review-approve-button'), {
      livewire: true,
      text: /autorizado|aprobado|approved|enviar/i,
      actionName: 'aprobar oferta cliente',
    });

    await expectNoServerError(page);
    await attachScreenshot(page, testInfo, '04-oferta-cliente-aprobada-por-admin');
  });

  test('admin solicita cambios con motivo obligatorio', async ({ page, request }) => {
    const fixture = await createClientOfferPendingReview(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminOfferReviews);

    await clickAndWaitForEither(page, page.getByTestId(`offer-review-row-${fixture.reviewId}`), {
      livewire: true,
      locator: page.getByTestId('offer-review-change-request-button'),
      actionName: 'abrir revision para cambios',
    });

    await clickAndWaitForEither(page, page.getByTestId('offer-review-change-request-button'), {
      livewire: true,
      text: /motivo|obligatorio|required|requerido/i,
      actionName: 'validar cambios sin motivo',
    });

    await page.getByTestId('offer-review-reason-textarea').fill('Cambios E2E: revisar precio final.');
    await clickAndWaitForEither(page, page.getByTestId('offer-review-change-request-button'), {
      livewire: true,
      text: /cambios|solicitados|requiere/i,
      actionName: 'solicitar cambios con motivo',
    });
  });

  test('admin rechaza oferta con motivo obligatorio', async ({ page, request }) => {
    const fixture = await createClientOfferPendingReview(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminOfferReviews);

    await clickAndWaitForEither(page, page.getByTestId(`offer-review-row-${fixture.reviewId}`), {
      livewire: true,
      locator: page.getByTestId('offer-review-reject-button'),
      actionName: 'abrir revision para rechazo',
    });

    await clickAndWaitForEither(page, page.getByTestId('offer-review-reject-button'), {
      livewire: true,
      text: /motivo|obligatorio|required|requerido/i,
      actionName: 'validar rechazo sin motivo',
    });

    await page.getByTestId('offer-review-reason-textarea').fill('Rechazo E2E: margen insuficiente.');
    await clickAndWaitForEither(page, page.getByTestId('offer-review-reject-button'), {
      livewire: true,
      text: /rechazada|rechazo|rejected/i,
      actionName: 'rechazar oferta con motivo',
    });
  });
});
