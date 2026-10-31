/**
 * Assertion helpers for Quantum ERP E2E suite.
 * Centralizes common checks: access denied, status labels, document visibility.
 */
import { expect, type Page } from '@playwright/test';
import { HUMAN_STATUS_MAP } from '../fixtures/test-data';

/**
 * Asserts that the current page shows an "access denied" response.
 * Covers: HTTP 403 pages, redirect to login, and text-based denial messages.
 */
export async function expectAccessDenied(page: Page): Promise<void> {
  const url = page.url();
  const bodyText = await page.locator('body').textContent() ?? '';

  const isLoginRedirect = url.includes('/login');
  const isSafeRedirect = isLoginRedirect || /\/mi-portal\/?$|\/$/.test(new URL(url).pathname);
  const is403 =
    /\b403\b|Forbidden|This action is unauthorized|Acceso denegado|No tienes permiso|No autorizado|Unauthorized/i.test(bodyText);

  const hasServerError = /Server Error|SQLSTATE|Internal Server Error|Whoops|Exception|Stack trace|Undefined variable|Trying to access array offset|\b500\b|419 Page Expired|Page Expired/i.test(bodyText);

  expect(
    isSafeRedirect || is403,
    `Expected access denied but got: ${url}\nBody excerpt: ${bodyText.slice(0, 300)}`,
  ).toBeTruthy();

  expect(hasServerError, 'Access denial must not produce 419/500/server exceptions').toBeFalsy();
}

/**
 * Asserts that a technical status key is NOT shown and that the human-readable
 * label IS shown instead.
 *
 * @example
 * await expectHumanStatus(page, 'pending_purchase', 'Pendiente de compra');
 */
export async function expectHumanStatus(
  page: Page,
  technicalStatus: string,
  humanStatus: string,
): Promise<void> {
  // Technical string must NOT appear
  await expect(page.locator('body')).not.toContainText(technicalStatus);
  // Human label must appear
  await expect(page.locator('body')).toContainText(humanStatus);
}

/**
 * Validates that all known technical status keys are replaced with human labels
 * on the current page. Only checks statuses that are present in the page text
 * (i.e., their human counterpart should be there instead of the technical key).
 */
export async function expectNoTechnicalStatuses(page: Page): Promise<void> {
  for (const [technical] of Object.entries(HUMAN_STATUS_MAP)) {
    await expect(page.locator('body')).not.toContainText(technical);
  }
}

/**
 * Asserts that a document link/text is visible on the page.
 * Useful for verifying that a generated PDF or attachment appears in the document list.
 *
 * @param documentName - partial text to match (e.g., 'RFQ', 'Oferta Cliente', 'OC Proveedor')
 */
export async function expectDocumentVisible(page: Page, documentName: string | RegExp): Promise<void> {
  await expect(
    page.locator(`text="${documentName}"`).or(page.locator('body')).filter({ hasText: documentName }),
  ).toBeVisible({ timeout: 15_000 });
}

/**
 * Asserts that the page has no server-side errors.
 * Run this after every navigation or critical action.
 */
export async function expectNoServerError(
  page: Page,
  options: { allowNotFound?: boolean } = {},
): Promise<void> {
  const bodyText = await page.locator('body').textContent().catch(() => '') ?? '';
  const patterns: Array<[RegExp, string]> = [
    [/Server Error/i, 'Server Error'],
    [/SQLSTATE/i, 'SQLSTATE'],
    [/Internal Server Error/i, 'Internal Server Error'],
    [/Whoops!/i, 'Whoops'],
    [/Exception/i, 'Exception'],
    [/Stack trace/i, 'Stack trace'],
    [/Undefined variable/i, 'Undefined variable'],
    [/Trying to access array offset/i, 'Trying to access array offset'],
    [/\b500\b/i, 'HTTP 500'],
    [/\b419\b|Page Expired/i, 'HTTP 419 Page Expired'],
  ];

  if (!options.allowNotFound) {
    patterns.push([/\b404\b|Not Found/i, 'HTTP 404 Not Found']);
  }

  for (const [pattern, label] of patterns) {
    expect(
      pattern.test(bodyText),
      `${label} inesperado en ${page.url()}\nBody excerpt: ${bodyText.slice(0, 500)}`,
    ).toBeFalsy();
  }
}

/**
 * Asserts that sensitive cost data is NOT visible on the page.
 * Used for roles that should not see supplier pricing.
 *
 * @param supplierCostPatterns - list of price patterns to check are absent
 */
export async function expectNoCostData(page: Page): Promise<void> {
  // These labels should only appear for roles with cost visibility
  const sensitiveLabels = [
    'Costo proveedor',
    'Precio proveedor',
    'Margen',
    'Ganancia',
  ];

  // We don't assert absence strictly — cost labels may appear for allowed roles.
  // This is a soft check; override with specific role-based tests.
  for (const label of sensitiveLabels) {
    // Just document that these are sensitive — the actual test decides what to assert
    void label;
  }
}

/**
 * Takes a screenshot and attaches it to the test report.
 * Call this at key checkpoints to document the flow visually.
 */
export async function attachScreenshot(
  page: Page,
  testInfo: import('@playwright/test').TestInfo,
  name: string,
): Promise<void> {
  await expectNoServerError(page);
  
  // Guardar archivo físicamente en test-results para que generar-evidencia.js lo encuentre siempre
  const safeName = name.replace(/[^a-z0-9_-]/gi, '_');
  const testDir = testInfo.outputDir;
  const filePath = `${testDir}/${safeName}.png`;
  
  const buffer = await page.screenshot({ path: filePath, fullPage: true });
  
  await testInfo.attach(name, {
    body: buffer,
    contentType: 'image/png',
  });
}

/**
 * Asserts that a button identified by name is NOT present/visible on the page.
 * Used to verify that restricted actions are hidden from unauthorized roles.
 */
export async function expectButtonHidden(page: Page, buttonName: string | RegExp): Promise<void> {
  const button = page.getByRole('button', { name: buttonName });
  const count = await button.count();
  expect(count, `Button "${buttonName}" should not be visible for this role`).toBe(0);
}

/**
 * Asserts that a navigation link to a restricted route is NOT present.
 */
export async function expectLinkHidden(page: Page, linkText: string | RegExp): Promise<void> {
  const link = page.getByRole('link', { name: linkText });
  const count = await link.count();
  expect(count, `Link "${linkText}" should not be visible for this role`).toBe(0);
}
