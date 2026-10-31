import { expect, type Locator, type Page } from '@playwright/test';
import { waitForLivewireAction } from './livewire';

export async function fillByTestId(page: Page, testId: string, value: string): Promise<void> {
  const field = page.getByTestId(testId);
  await expect(field).toBeVisible();
  await field.fill(value);
}

export async function fillAndWaitForLivewire(page: Page, field: Locator, value: string): Promise<void> {
  await expect(field).toBeVisible();
  await waitForLivewireAction(page, () => field.fill(value), {
    actionName: `Fill ${await field.getAttribute('data-testid').catch(() => 'field')}`,
  });
}

export async function clearAndFill(field: Locator, value: string): Promise<void> {
  await expect(field).toBeVisible();
  await field.fill('');
  await field.fill(value);
}
