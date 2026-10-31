import { expect, type Page } from '@playwright/test';
import { expectNoServerError } from './assertions';

export function appUrl(path: string): string {
  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  const baseURL = process.env.E2E_BASE_URL ?? '';

  return `${baseURL.replace(/\/$/, '')}${path}`;
}

export async function gotoAndAssertHealthy(page: Page, url: string): Promise<void> {
  await page.goto(appUrl(url));
  await page.waitForLoadState('networkidle').catch(() => undefined);
  await expectNoServerError(page);

  await expect(page).toHaveURL(new RegExp(appUrl(url).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
}

export async function expectUrlContains(page: Page, value: string | RegExp): Promise<void> {
  await expect(page).toHaveURL(value instanceof RegExp ? value : new RegExp(value));
}
