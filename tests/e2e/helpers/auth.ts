/**
 * Auth helpers for Quantum ERP E2E tests.
 */
import { expect, type Page } from '@playwright/test';

export const E2E_PASSWORD = process.env.E2E_PASSWORD ?? 'Password123!';
const baseURL = process.env.E2E_BASE_URL;

export async function loginAs(page: Page, email: string): Promise<void> {
  await page.goto(baseURL ? `${baseURL.replace(/\/$/, '')}/login` : '/login');

  const emailInput = page.locator('#email, input[type="email"]').first();
  await expect(emailInput).toBeVisible({ timeout: 15_000 });

  await emailInput.fill(email);
  await page.locator('#password, input[type="password"]').first().fill(E2E_PASSWORD);

  const submitButton = page.locator('button[type="submit"]').first();
  await expect(submitButton).toBeVisible();

  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30_000 }),
    submitButton.click({ noWaitAfter: true }),
  ]);

  await page.waitForLoadState('domcontentloaded');
  await expect(page).not.toHaveURL(/\/login$/);
}

/**
 * Logs out through the real Laravel POST form.
 *
 * GET /logout returns 405 in this app and keeps the session alive, so this
 * helper opens the user dropdown and submits the CSRF-protected form.
 */
export async function logout(page: Page): Promise<void> {
  const logoutButton = page.getByTestId('logout-submit');
  await expect(logoutButton, 'Debe existir el formulario POST de logout').toBeAttached({ timeout: 5_000 });

  await Promise.all([
    page
      .waitForResponse(
        (response) => response.url().includes('/logout') && response.request().method() === 'POST',
        { timeout: 20_000 },
      )
      .catch(() => undefined),
    page.waitForURL((url) => !url.pathname.includes('/dashboard'), { timeout: 20_000 }).catch(() => undefined),
    logoutButton.evaluate((button) => {
      const form = button.closest('form');
      if (!(form instanceof HTMLFormElement)) {
        throw new Error('El botón de logout no pertenece a un formulario.');
      }
      form.requestSubmit();
    }),
  ]);

  await page.waitForLoadState('domcontentloaded').catch(() => undefined);
}
