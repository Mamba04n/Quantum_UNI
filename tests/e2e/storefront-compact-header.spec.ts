import { returningStorefrontVisitor } from './helpers/storefront-returning-visitor';
﻿import {test,expect} from '@playwright/test';
test.beforeEach(async ({ page }) => { await returningStorefrontVisitor(page); });
test.use({video:'off'});
test('commerce header menu and search work across widths',async({page},info)=>{
 for(const width of [1440,390]){
 await page.setViewportSize({width,height:950});await page.goto('/productos');
 await page.getByRole('button',{name:'Abrir men\u00fa',exact:true}).click();await expect(page.getByRole('complementary',{name:'Men\u00fa de navegaci\u00f3n'})).toBeVisible();await page.getByRole('button',{name:'Cerrar men\u00fa',exact:true}).click();
 await page.locator('.header-search-form input').fill('1R-1808');await page.locator('.header-search-form button').click();await expect(page.locator('.products-item-card').first()).toContainText('1R-1808');
 await page.screenshot({path:info.outputPath(`compact-header-${width}.png`)});
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
 }
});
