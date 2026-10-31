import { returningStorefrontVisitor } from './helpers/storefront-returning-visitor';
﻿import {test,expect} from '@playwright/test';
test.beforeEach(async ({ page }) => { await returningStorefrontVisitor(page); });
test.use({video:'off'});
test('navigation drawer exposes groups and keyboard close',async({page},info)=>{
 for(const width of [1440,390]){
 await page.setViewportSize({width,height:850});await page.goto('/productos');const opener=page.getByRole('button',{name:'Abrir men\u00fa',exact:true});await opener.click();const panel=page.locator('#public-navigation-panel');await expect(panel).toBeVisible();await expect(opener).toHaveAttribute('aria-expanded','true');
 await panel.locator('summary').first().click();await expect(panel.getByRole('link',{name:/Filtraci/})).toBeVisible();await expect(panel.getByRole('link',{name:'Seguimiento de pedido'})).toBeVisible();
 await page.screenshot({path:info.outputPath(`menu-${width}.png`)});expect(await panel.evaluate(el=>el.scrollWidth<=el.clientWidth)).toBe(true);
 await page.keyboard.press('Escape');await expect(opener).toHaveAttribute('aria-expanded','false');await expect(opener).toBeFocused();
 }
});
