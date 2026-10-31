import { returningStorefrontVisitor } from './helpers/storefront-returning-visitor';
﻿import {test,expect} from '@playwright/test';
test.beforeEach(async ({ page }) => { await returningStorefrontVisitor(page); });
test.use({video:'off'});
test('catalogue, product and cart adapt to monitor widths',async({page},info)=>{
 const errors:string[]=[];page.on('pageerror',e=>errors.push(e.message));
 for(const width of [2560,1920,1366,768,390]){
  await page.setViewportSize({width,height:1000});
  await page.goto('/productos');
  await expect(page.locator('.products-item-card').first()).toBeVisible();
  const content=await page.locator('.products-page > .container').boundingBox();expect(content!.width).toBeGreaterThan(width*.88);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  await page.screenshot({path:info.outputPath(`catalogue-${width}.png`),fullPage:true});
  await page.locator('.products-add-btn').first().click();
  await expect(page.locator('dialog[open]')).toBeVisible();
  await expect(page.locator('.cart-drawer-item').first()).toBeVisible();
  await page.screenshot({path:info.outputPath(`drawer-${width}.png`)});
  await page.getByRole('button',{name:'Cerrar carrito',exact:true}).click();
  await expect(page.locator('dialog[open]')).toHaveCount(0);
  await page.locator('.products-item-link').first().click();
  await expect(page.locator('.detail-info-card h1')).toBeVisible();
  await expect(page.getByText('Identificación y especificaciones',{exact:true})).toBeVisible();
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  await page.screenshot({path:info.outputPath(`product-${width}.png`),fullPage:true});
  await page.goto('/carrito');
  await expect(page.locator('.quote-cart-item').first()).toBeVisible();
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  await page.screenshot({path:info.outputPath(`cart-${width}.png`),fullPage:true});
 }
 expect(errors).toEqual([]);
});
