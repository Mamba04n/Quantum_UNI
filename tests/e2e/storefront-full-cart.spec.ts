import { returningStorefrontVisitor } from './helpers/storefront-returning-visitor';
import { test, expect } from '@playwright/test';
test.beforeEach(async ({ page }) => { await returningStorefrontVisitor(page); });

test.use({ video: 'off' });
test('full quotation cart updates quantities and adapts without losing contact fields', async ({ page }, info) => {
    const errors: string[] = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto('/productos');
    await page.locator('.products-add-btn').first().click();
    await expect(page.locator('dialog[open]')).toBeVisible();
    await page.goto('/carrito');
    const quantity = page.locator('.quote-cart-controls input').first();
    await expect(quantity).toHaveValue('1');
    await page.getByRole('button', { name: /Aumentar cantidad/ }).first().click();
    await expect(quantity).toHaveValue('2');
    await expect(page.locator('.cart-count')).toHaveText('2');
    await quantity.fill('3');
    await quantity.press('Tab');
    await expect(page.locator('.cart-count')).toHaveText('3');
    await page.getByRole('button', { name: /Reducir cantidad/ }).first().click();
    await expect(quantity).toHaveValue('2');
    await page.locator('input[autocomplete="name"]').fill('Revisión visual');
    for (const width of [1920, 1366, 768, 390]) {
        await page.setViewportSize({ width, height: 950 });
        await expect(page.locator('#quote-summary-title')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await expect(page.locator('input[autocomplete="name"]')).toHaveValue('Revisión visual');
        await page.screenshot({ path: info.outputPath(`full-cart-${width}.png`), fullPage: true });
    }
    await page.getByRole('button', { name: /Eliminar / }).first().click();
    await expect(page.getByRole('heading', { name: 'Tu carrito está vacío' })).toBeVisible();
    await expect(page.locator('.cart-count')).toBeHidden();
    expect(errors).toEqual([]);
});
