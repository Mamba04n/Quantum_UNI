import { expect, test } from '@playwright/test';
import {
  cleanupE2E,
  clickAndWaitForEither,
  createAdminApprovalPending,
  expectNoServerError,
  loginAs,
  setupBase,
  attachScreenshot,
} from './helpers';
import { ROUTES, USERS } from './fixtures/test-data';

test.describe('Aprobacion interna admin', () => {
  test.beforeAll(async ({ request }) => {
    await setupBase(request);
  });

  test.afterEach(async ({ request }) => {
    await cleanupE2E(request);
  });

  test('admin ve una aprobacion pendiente preparada por fixture', async ({ page, request }, testInfo) => {
    const fixture = await createAdminApprovalPending(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminApprovals);
    await expectNoServerError(page);

    await expect(page.getByTestId(`approval-open-button-${fixture.approvalId}`)).toBeVisible();
    await expect(page.locator('body')).toContainText(fixture.reference);

    await attachScreenshot(page, testInfo, '01-aprobacion-pendiente-listado');
  });

  test('admin abre aprobacion y ve acciones de decision', async ({ page, request }, testInfo) => {
    const fixture = await createAdminApprovalPending(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminApprovals);
    await expectNoServerError(page);

    await clickAndWaitForEither(page, page.getByTestId(`approval-open-button-${fixture.approvalId}`), {
      livewire: true,
      locator: page.getByTestId('approval-approve-button'),
      actionName: 'abrir aprobacion pendiente',
    });

    await expect(page.locator('body')).toContainText(fixture.reference);
    await expect(page.getByTestId('approval-approve-button')).toBeVisible();
    await expect(page.getByTestId('approval-change-request-button')).toBeVisible();
    await expect(page.getByTestId('approval-reject-button')).toBeVisible();

    await attachScreenshot(page, testInfo, '02-aprobacion-acciones-disponibles');
  });

  test('admin aprueba compra y no recibe error de servidor', async ({ page, request }, testInfo) => {
    const fixture = await createAdminApprovalPending(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminApprovals);

    await clickAndWaitForEither(page, page.getByTestId(`approval-open-button-${fixture.approvalId}`), {
      livewire: true,
      locator: page.getByTestId('approval-approve-button'),
      actionName: 'abrir aprobacion para aprobar',
    });

    await clickAndWaitForEither(page, page.getByTestId('approval-approve-button'), {
      livewire: true,
      text: /aprobada|procesada|orden|pedido|solicitud procesada/i,
      timeout: 60_000,
      actionName: 'aprobar compra interna',
    });

    await expectNoServerError(page);
    await expect(page.locator('body')).toContainText(/aprobada|procesada|orden|pedido/i);

    await attachScreenshot(page, testInfo, '03-aprobacion-procesada');
  });

  test('doble clic en aprobar no duplica ni genera 500', async ({ page, request }) => {
    const fixture = await createAdminApprovalPending(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminApprovals);

    await clickAndWaitForEither(page, page.getByTestId(`approval-open-button-${fixture.approvalId}`), {
      livewire: true,
      locator: page.getByTestId('approval-approve-button'),
      actionName: 'abrir aprobacion para doble clic',
    });

    await clickAndWaitForEither(page, page.getByTestId('approval-approve-button'), {
      livewire: true,
      text: /aprobada|procesada|orden|pedido/i,
      timeout: 60_000,
      actionName: 'primer clic aprobar',
    });

    await expectNoServerError(page);
    await expect(page.locator('body')).toContainText(/solicitud procesada|aprobada|orden|pedido/i);
  });

  test('rechazar aprobacion exige motivo y luego procesa rechazo', async ({ page, request }) => {
    const fixture = await createAdminApprovalPending(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminApprovals);

    await clickAndWaitForEither(page, page.getByTestId(`approval-open-button-${fixture.approvalId}`), {
      livewire: true,
      locator: page.getByTestId('approval-reject-button'),
      actionName: 'abrir aprobacion para rechazar',
    });

    await clickAndWaitForEither(page, page.getByTestId('approval-reject-button'), {
      livewire: true,
      locator: page.getByTestId('approval-reason-textarea'),
      actionName: 'abrir modal de rechazo',
    });

    await clickAndWaitForEither(page, page.getByTestId('approval-decision-submit-button'), {
      livewire: true,
      text: /motivo|obligatorio|required|requerido/i,
      actionName: 'validar rechazo sin motivo',
    });

    await page.getByTestId('approval-reason-textarea').fill('Rechazo E2E: costo fuera de politica de compra.');
    await clickAndWaitForEither(page, page.getByTestId('approval-decision-submit-button'), {
      livewire: true,
      text: /rechazada|rechazo/i,
      actionName: 'confirmar rechazo con motivo',
    });

    await expectNoServerError(page);
  });

  test('solicitar cambios exige motivo y registra la decision', async ({ page, request }) => {
    const fixture = await createAdminApprovalPending(request);

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.adminApprovals);

    await clickAndWaitForEither(page, page.getByTestId(`approval-open-button-${fixture.approvalId}`), {
      livewire: true,
      locator: page.getByTestId('approval-change-request-button'),
      actionName: 'abrir aprobacion para cambios',
    });

    await clickAndWaitForEither(page, page.getByTestId('approval-change-request-button'), {
      livewire: true,
      locator: page.getByTestId('approval-reason-textarea'),
      actionName: 'abrir modal solicitar cambios',
    });

    await clickAndWaitForEither(page, page.getByTestId('approval-decision-submit-button'), {
      livewire: true,
      text: /motivo|obligatorio|required|requerido/i,
      actionName: 'validar cambios sin motivo',
    });

    await page.getByTestId('approval-reason-textarea').fill('Cambios E2E: revisar proveedor seleccionado.');
    await clickAndWaitForEither(page, page.getByTestId('approval-decision-submit-button'), {
      livewire: true,
      text: /cambios|ajuste|requiere/i,
      actionName: 'confirmar cambios con motivo',
    });

    await expectNoServerError(page);
  });
});
