import type { Page } from '@playwright/test';

// Navigation/catalogue tests represent a returning visitor. Fresh consent is
// exercised separately in storefront-consent-entry.spec.ts.
export async function returningStorefrontVisitor(page: Page) {
    await page.addInitScript(() => {
        localStorage.setItem('quantum.storefront.consent.v1', JSON.stringify({
            version: 1, necessary: true, external: false, savedAt: Date.now(),
        }));
    });
}
