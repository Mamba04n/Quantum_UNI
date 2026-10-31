import { type Locator, type Page } from '@playwright/test';

export function byTestId(page: Page, testId: string): Locator {
  return page.getByTestId(testId);
}

export function rowById(page: Page, prefix: string, id: number | string): Locator {
  return page.getByTestId(`${prefix}-${id}`);
}
