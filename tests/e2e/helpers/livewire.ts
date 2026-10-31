/**
 * Livewire and interaction helpers for Playwright E2E tests.
 *
 * Rule: never use waitForTimeout(). Wait for a concrete browser signal.
 */
import { expect, type Locator, type Page, type Response } from '@playwright/test';

type UrlMatcher = string | RegExp | ((url: URL) => boolean);

export type ClickWaitResult =
  | { kind: 'livewire'; response: Response }
  | { kind: 'navigation'; url: string }
  | { kind: 'response'; response: Response }
  | { kind: 'locator'; description: string }
  | { kind: 'click' };

export interface ClickAndWaitOptions {
  timeout?: number;
  livewire?: boolean;
  url?: UrlMatcher;
  modal?: Locator;
  locator?: Locator;
  locatorDescription?: string;
  text?: string | RegExp;
  response?: (response: Response) => boolean;
  required?: boolean;
}

async function responseExcerpt(response: Response): Promise<string> {
  return response.text().then((text) => text.slice(0, 500)).catch(() => '');
}

function urlMatches(url: URL, matcher: UrlMatcher): boolean {
  if (typeof matcher === 'string') {
    return url.toString().includes(matcher) || url.pathname.includes(matcher);
  }

  if (matcher instanceof RegExp) {
    return matcher.test(url.toString()) || matcher.test(url.pathname);
  }

  return matcher(url);
}

async function assertHealthyResponse(response: Response, label: string): Promise<void> {
  if (response.ok()) {
    return;
  }

  const excerpt = await responseExcerpt(response);
  throw new Error(
    `${label} returned HTTP ${response.status()} ${response.statusText()} at ${response.url()}\n` +
    `Body excerpt: ${excerpt}`,
  );
}

/**
 * Executes an action that must trigger a Livewire update.
 * If the request returns 419/403/500/etc. the helper fails with the real status.
 *
 * @example
 * await waitForLivewireAction(page, () => page.getByTestId('customer-save-button').click());
 */
export async function waitForLivewireAction(
  page: Page,
  action: () => Promise<void>,
  options: { timeout?: number; actionName?: string } = {},
): Promise<Response> {
  const timeout = options.timeout ?? 45_000;
  const actionName = options.actionName ?? 'Livewire action';

  const responsePromise = page.waitForResponse(
    (response) => response.url().includes('/livewire/update'),
    { timeout },
  );

  await action();

  let response: Response;
  try {
    response = await responsePromise;
  } catch (error) {
    const body = await page.locator('body').textContent().catch(() => '');
    throw new Error(
      `${actionName} did not trigger /livewire/update within ${timeout}ms.\n` +
      `Current URL: ${page.url()}\n` +
      `Body excerpt: ${(body ?? '').slice(0, 500)}`,
      { cause: error },
    );
  }

  await assertHealthyResponse(response, actionName);
  return response;
}

/**
 * Backward-compatible alias for older specs. Prefer waitForLivewireAction in
 * new tests so the expectation is explicit.
 */
export async function waitForLivewire(
  page: Page,
  action: () => Promise<void>,
  options: { timeout?: number; actionName?: string } = {},
): Promise<void> {
  await waitForLivewireAction(page, action, options);
}

/**
 * Clicks an element and waits for the first expected signal:
 * Livewire update, navigation, a visible modal/locator, text, or a custom response.
 * Use it when the action might be Alpine-only, a normal link, or Livewire.
 */
export async function clickAndWaitForEither(
  page: Page,
  locator: Locator,
  options: ClickAndWaitOptions = {},
): Promise<ClickWaitResult> {
  const timeout = options.timeout ?? 15_000;
  const waiters: Array<Promise<ClickWaitResult>> = [];
  let fatalError: Error | null = null;

  if (options.livewire !== false) {
    waiters.push(
      page.waitForResponse((response) => response.url().includes('/livewire/update'), { timeout })
        .then(async (response) => {
          try {
            await assertHealthyResponse(response, 'Livewire update');
          } catch (error) {
            fatalError = error as Error;
            throw error;
          }

          return { kind: 'livewire', response } as const;
        }),
    );
  }

  if (options.url) {
    waiters.push(
      page.waitForURL((url) => urlMatches(url, options.url as UrlMatcher), { timeout })
        .then(() => ({ kind: 'navigation', url: page.url() }) as const),
    );
  }

  if (options.response) {
    waiters.push(
      page.waitForResponse(options.response, { timeout })
        .then(async (response) => {
          try {
            await assertHealthyResponse(response, 'Expected response');
          } catch (error) {
            fatalError = error as Error;
            throw error;
          }

          return { kind: 'response', response } as const;
        }),
    );
  }

  const visibleLocator = options.modal ?? options.locator;
  if (visibleLocator) {
    waiters.push(
      visibleLocator.waitFor({ state: 'visible', timeout })
        .then(() => ({
          kind: 'locator',
          description: options.locatorDescription ?? (options.modal ? 'modal' : 'locator'),
        }) as const),
    );
  }

  if (options.text) {
    waiters.push(
      expect(page.locator('body')).toContainText(options.text, { timeout })
        .then(() => ({ kind: 'locator', description: `text:${String(options.text)}` }) as const),
    );
  }

  await locator.click();

  if (waiters.length === 0) {
    return { kind: 'click' };
  }

  try {
    return await Promise.any(waiters);
  } catch (error) {
    if (fatalError) {
      throw fatalError;
    }

    if (options.required === false) {
      return { kind: 'click' };
    }

    const body = await page.locator('body').textContent().catch(() => '');
    throw new Error(
      `Click did not produce any expected signal within ${timeout}ms.\n` +
      `Current URL: ${page.url()}\n` +
      `Body excerpt: ${(body ?? '').slice(0, 500)}`,
      { cause: error },
    );
  }
}

/**
 * Waits for a Livewire file upload to complete.
 * Use this immediately before calling setInputFiles().
 *
 * @example
 * const done = waitForFileUpload(page);
 * await page.locator('input[type="file"]').setInputFiles(...);
 * await done;
 */
export function waitForFileUpload(page: Page, options: { timeout?: number } = {}): Promise<void> {
  return page
    .waitForResponse(
      (response) =>
        response.url().includes('/livewire/upload-file') && response.status() === 200,
      { timeout: options.timeout ?? 30_000 },
    )
    .then(() => undefined);
}

/**
 * Asserts that a toast/flash notification with the given message becomes visible.
 * Works with Alpine.js/Livewire toast patterns common in this ERP.
 *
 * @example
 * await expectToast(page, 'Guardado exitosamente');
 */
export async function expectToast(page: Page, message: string | RegExp, options: { timeout?: number } = {}): Promise<void> {
  const timeout = options.timeout ?? 10_000;

  // Try multiple common toast selectors used in this app
  const selectors = [
    page.locator('[x-data*="toast"]'),
    page.locator('.toast, .alert-success, .alert-danger, .alert-info'),
    page.locator('[role="alert"]'),
    page.locator('[wire\\:key*="toast"]'),
    page.locator('body'), // fallback: text anywhere in body
  ];

  for (const locator of selectors) {
    try {
      await expect(locator).toContainText(message, { timeout: 1_000 });
      return;
    } catch {
      // try next selector
    }
  }

  // Final assertion with full timeout
  await expect(page.locator('body')).toContainText(message, { timeout });
}

/**
 * Waits for a Livewire component to finish re-rendering after a navigation or
 * wire:navigate event. Useful after clicking livewire links.
 */
export async function waitForLivewireNavigation(page: Page, options: { timeout?: number } = {}): Promise<void> {
  await page.waitForLoadState('networkidle', { timeout: options.timeout ?? 30_000 });
}

/**
 * Clicks a button once and waits for livewire update. Safe against double-click
 * by disabling the button after the first click (relies on Livewire's loading state).
 */
export async function clickOnceLivewire(
  page: Page,
  buttonLocator: ReturnType<Page['getByRole']>,
  options: { timeout?: number } = {},
): Promise<void> {
  await waitForLivewireAction(page, () => buttonLocator.click(), options);
}
