import { expect, test } from '@playwright/test';
import {
  cleanupE2E,
  clickAndWaitForEither,
  createCustomerFixture,
  expectAccessDenied,
  expectNoServerError,
  loginAs,
  setupBase,
  waitForLivewireAction,
} from './helpers';
import { uniqueEmail, uniqueSuffix } from './helpers/data';
import { ROUTES, USERS } from './fixtures/test-data';

test.describe('Clientes - CRUD y estados', () => {
  test.beforeAll(async ({ request }) => {
    await setupBase(request);
  });

  test.afterEach(async ({ request }) => {
    await cleanupE2E(request);
  });

  test('admin crea cliente nuevo con datos controlados', async ({ page }) => {
    const uid = uniqueSuffix();
    const name = `Cliente E2E ${uid}`;
    const email = uniqueEmail(`cliente.${uid}`);

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.clients);
    await expectNoServerError(page);

    await clickAndWaitForEither(page, page.getByTestId('customer-create-button'), {
      livewire: true,
      locator: page.getByTestId('customer-save-button'),
      actionName: 'abrir modal de cliente',
    });

    await page.getByTestId('customer-name-input').fill(name);
    await page.getByTestId('customer-email-input').fill(email);
    await page.getByTestId('customer-phone-input').fill('+505 9999 0001');
    await page.getByTestId('customer-address-input').fill('Managua, Nicaragua');

    await clickAndWaitForEither(page, page.getByTestId('customer-save-button'), {
      livewire: true,
      text: /guardado|registrado|creado|cliente/i,
      actionName: 'guardar cliente',
    });

    await expect(page.getByRole('main')).toContainText(name);
  });

  test('admin edita un cliente creado por fixture', async ({ page, request }) => {
    const customer = await createCustomerFixture(request, {
      name: `Cliente E2E Edit ${uniqueSuffix()}`,
      email: uniqueEmail('cliente.edit'),
    });
    const newName = `${customer.name} Actualizado`;

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.clients);
    await expectNoServerError(page);

    await waitForLivewireAction(
      page,
      () => page.getByTestId('customer-search-input').fill(customer.name),
      { actionName: 'buscar cliente fixture' },
    );

    await expect(page.getByTestId(`customer-row-${customer.id}`)).toBeVisible();
    await page.getByTestId(`customer-row-${customer.id}`).click();
    await expect(page).toHaveURL(new RegExp(`/clients/${customer.id}/profile`));
    await clickAndWaitForEither(page, page.getByTestId('customer-profile-edit-button'), {
      livewire: true,
      locator: page.getByTestId('customer-profile-save-button'),
      actionName: 'abrir edicion desde perfil de cliente',
    });

    await page.getByTestId('customer-profile-name-input').fill(newName);
    await clickAndWaitForEither(page, page.getByTestId('customer-profile-save-button'), {
      livewire: true,
      text: /guardado|actualizado/i,
      actionName: 'guardar edicion de cliente',
    });

    await expect(page.getByRole('main')).toContainText(newName);
  });

  test('admin desactiva y reactiva un cliente sin depender del primer boton visible', async ({ page, request }) => {
    const customer = await createCustomerFixture(request, {
      name: `Cliente E2E Estado ${uniqueSuffix()}`,
      email: uniqueEmail('cliente.estado'),
    });

    await loginAs(page, USERS.admin.email);
    await page.goto(ROUTES.clients);
    await expectNoServerError(page);

    await waitForLivewireAction(
      page,
      () => page.getByTestId('customer-search-input').fill(customer.name),
      { actionName: 'buscar cliente para cambio de estado' },
    );

    await page.getByTestId(`customer-row-${customer.id}`).click();
    await expect(page).toHaveURL(new RegExp(`/clients/${customer.id}/profile`));

    if (!(await page.getByTestId('customer-profile-toggle-status-button').isVisible())) {
      await page.getByTestId('customer-profile-actions-button').click();
    }
    page.once('dialog', dialog => dialog.accept());
    await clickAndWaitForEither(page, page.getByTestId('customer-profile-toggle-status-button'), {
      livewire: true,
      text: /inactivo|desactivado/i,
      actionName: 'desactivar cliente',
    });
    await expect(page.getByRole('main')).toContainText(/reactivar cliente/i);

    if (!(await page.getByTestId('customer-profile-toggle-status-button').isVisible())) {
      await page.getByTestId('customer-profile-actions-button').click();
    }
    page.once('dialog', dialog => dialog.accept());
    await clickAndWaitForEither(page, page.getByTestId('customer-profile-toggle-status-button'), {
      livewire: true,
      text: /activo|reactivado/i,
      actionName: 'reactivar cliente',
    });
    await expect(page.getByRole('main')).toContainText(/desactivar cliente/i);
  });

  test('bodega no puede acceder al modulo de clientes', async ({ page }) => {
    await loginAs(page, USERS.bodega.email);
    await page.goto(ROUTES.clients);
    await expectAccessDenied(page);
  });
});
