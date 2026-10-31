import { returningStorefrontVisitor } from './helpers/storefront-returning-visitor';
﻿import {test,expect} from '@playwright/test';
test.beforeEach(async ({ page }) => { await returningStorefrontVisitor(page); });
test.use({video:'off'});
test('quote drawer updates quantities, keeps summary visible and supports empty state',async({page},info)=>{
 const errors:string[]=[];page.on('pageerror',e=>errors.push(e.message));
 for(const width of [1440,768,390]){
  const height=width===1440?650:950; await page.setViewportSize({width,height});await page.goto('/productos');await page.locator('.products-card-actions .products-add-btn').first().click();
  const drawer=page.locator('dialog[open]');await expect(drawer.locator('.cart-drawer-item')).toHaveCount(1);
  await expect(page.locator('.storefront-cart-trigger')).toHaveClass(/has-items/);
  const amount=drawer.locator('.commerce-quantity output');const before=Number(await amount.innerText());
  await drawer.getByRole('button',{name:/Aumentar cantidad/}).click();await expect(amount).toHaveText(String(before+1));
  await drawer.getByRole('button',{name:/Reducir cantidad/}).click();await expect(amount).toHaveText(String(before));
  await expect(drawer.getByText('Subtotal referencial',{exact:true})).toBeVisible();await expect(drawer.locator('progress')).toBeVisible();
  const footer=await drawer.locator('.cart-drawer-footer').boundingBox();expect(footer!.y+footer!.height).toBeLessThanOrEqual(height+1);
  expect(await page.evaluate(()=>document.body.scrollHeight>0 && getComputedStyle(document.body).overflow==='hidden')).toBe(true);
  await page.screenshot({path:info.outputPath(`quote-drawer-${width}.png`)});
  await drawer.getByRole('button',{name:'Eliminar',exact:true}).click();await expect(drawer.getByText('Tu carrito de cotizaci\u00f3n est\u00e1 vac\u00edo.',{exact:true})).toBeVisible();await expect(drawer.locator('progress')).toHaveCount(0);await expect(page.locator('.storefront-cart-trigger')).not.toHaveClass(/has-items/);
  await page.getByRole('button',{name:'Cerrar carrito',exact:true}).click();await expect(page.locator('dialog[open]')).toHaveCount(0);
 }
 expect(errors).toEqual([]);
});
