import {test,expect} from '@playwright/test';
import fs from 'node:fs';
test.use({video:'off'});
test('pago y seguimiento mantienen jerarquía, privacidad y botones elegibles',async({page},info)=>{
  const fixture=JSON.parse(fs.readFileSync('storage/app/public-commerce-e2e.json','utf8'));
  const visual=JSON.parse(fs.readFileSync('storage/app/public-commerce-visual.json','utf8'));
  const errors:string[]=[]; page.on('pageerror',e=>errors.push(e.message));
  for(const width of [1440,768,390]) {
    await page.setViewportSize({width,height:950});
    await page.goto(`/pagar/${visual.pending_token}`);
    await expect(page.getByRole('heading',{name:'Elige cómo pagar'})).toBeVisible();
    await expect(page.locator('#paypal-method:not([hidden]),#card-method:not([hidden])').first()).toBeVisible({timeout:45000});
    await expect(page.getByRole('complementary',{name:'Resumen del pedido'})).toBeVisible();
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    await page.screenshot({path:info.outputPath(`payment-pending-${width}.png`),fullPage:true});
    await page.goto(`/pagar/${fixture.payment_token}`);
    await expect(page.getByRole('heading',{name:'Pago recibido correctamente'})).toBeVisible();
    await expect(page.getByRole('link',{name:'Ver seguimiento'})).toBeVisible();
    await page.screenshot({path:info.outputPath(`payment-paid-${width}.png`),fullPage:true});
    await page.goto(`/seguimiento/${fixture.tracking_code}`);
    await expect(page.getByRole('heading',{name:'Preparando tu pedido'})).toBeVisible();
    await expect(page.locator('[aria-current=step]')).toHaveCount(1);
    await expect(page.getByRole('heading',{name:'Actualizaciones'})).toBeVisible();
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    await page.screenshot({path:info.outputPath(`tracking-${width}.png`),fullPage:true});
  }
  await page.getByLabel('Código de seguimiento').fill('QS-AAAAAAAAAAAA');
  await page.getByRole('button',{name:'Consultar',exact:true}).click();
  await expect(page.getByText(/No encontramos un pedido/)).toBeVisible();
  expect(errors).toEqual([]);
});
