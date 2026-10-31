import { returningStorefrontVisitor } from './helpers/storefront-returning-visitor';
﻿import {test,expect} from '@playwright/test';
test.beforeEach(async ({ page }) => { await returningStorefrontVisitor(page); });
test.use({video:'off'});
test('public pages share compact responsive shell',async({page},info)=>{
 test.setTimeout(180000);
 const errors:string[]=[];page.on('pageerror',e=>errors.push(e.message));
 for(const width of [1920,768,390])for(const route of ['/','/productos','/servicios','/catalogos','/nosotros','/contacto','/blog','/carrito','/politica-de-privacidad']){
 await page.setViewportSize({width,height:950});const response=await page.goto(route,{waitUntil:'domcontentloaded'});expect(response!.status()).toBe(200);
 await expect(page.locator('.header-search-form')).toBeVisible();await expect(page.locator('main').first()).toBeVisible();expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
 const header=await page.locator('.header-wrapper').boundingBox();expect(header!.height).toBeLessThanOrEqual(width<=700?118:82);
 await page.screenshot({path:info.outputPath(`${route.replaceAll('/','')||'home'}-${width}.png`)});
 }
 expect(errors).toEqual([]);
});
