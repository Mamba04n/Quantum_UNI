import { returningStorefrontVisitor } from './helpers/storefront-returning-visitor';
import { test, expect } from '@playwright/test';
test.beforeEach(async ({ page }) => { await returningStorefrontVisitor(page); });

test.use({ video: 'off' });
test('canonical product and category links work on desktop and mobile', async ({ page }, info) => {
    test.setTimeout(180000);
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 950 });
        await page.goto('/productos/filtracion?marca=Donaldson&stock=1', { waitUntil: 'networkidle' });
        await expect(page.locator('.products-item-card')).toHaveCount(1);
        await page.locator('.products-item-link').first().click();
        await page.waitForLoadState('networkidle');
        expect(new URL(page.url()).pathname).toMatch(/^\/productos\/\d+\/[^/]+$/);
        await expect(page.locator('link[rel=canonical]')).toHaveAttribute('href', page.url());
        await expect(page.locator('meta[property="og:url"]')).toHaveAttribute('content', page.url());
        await page.screenshot({ path: info.outputPath(`product-url-${width}.png`), fullPage: true });
        await page.locator('.detail-breadcrumb a').last().click();
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveURL(/\/productos\/filtracion$/);
        if (width < 700) await page.locator('[data-products-filter-open]').click();
        await page.locator('input[type=radio][value="electrico"]').check();
        await expect(page).toHaveURL(/\/productos\/electrico$/, { timeout: 20000 });
        await page.waitForLoadState('networkidle');
        await expect(page.locator('h1')).toContainText('Eléctrico');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: info.outputPath(`category-url-${width}.png`), fullPage: true });
    }
    expect(errors).toEqual([]);
});
