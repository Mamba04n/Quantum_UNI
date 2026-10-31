import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import { loginAs } from './helpers/auth';

test.use({video:'off'});
test('cobros y seguimiento conservan privacidad y composición responsive', async ({page,browser},info)=>{
  const fixture=JSON.parse(fs.readFileSync('storage/app/public-commerce-e2e.json','utf8'));
  const errors:string[]=[]; page.on('pageerror',e=>errors.push(e.message));
  page.on('response',r=>{if(r.status()>=400 && /livewire|public-commerce\.css/.test(r.url())) errors.push(`${r.status()} ${r.url()}`);});
  for(const width of [1440,768,390]) {
    await page.setViewportSize({width,height:950});
    await page.goto(`/pagar/${fixture.payment_token}`);
    await expect(page.getByRole('heading',{name:'Pago de tu pedido'})).toBeVisible();
    await expect(page.locator('.order-summary .total strong')).toHaveText('USD 25.00');
    await expect(page.getByText('UX-PAYPAL Cliente de prueba')).toHaveCount(0);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    await page.screenshot({path:info.outputPath(`payment-${width}.png`),fullPage:true});
    await page.goto(`/seguimiento/${fixture.tracking_code}`);
    await expect(page.getByRole('heading',{name:'Preparando tu pedido'})).toBeVisible();
    await expect(page.locator('[aria-current=step]')).toContainText('Preparando');
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    await page.screenshot({path:info.outputPath(`tracking-${width}.png`),fullPage:true});
  }
  await loginAs(page,'ux-paypal@quantum.test');
  for(const width of [1440,390]) {
    await page.setViewportSize({width,height:950});
    await page.goto('/finance/payment-links');
    await expect(page.getByRole('heading',{name:'Enlaces de pago'})).toBeVisible();
    await page.getByRole('button',{name:'Nuevo cobro'}).click();
    await expect(page.getByLabel('Cliente',{exact:true})).toBeVisible();
    await page.getByLabel('Cliente',{exact:true}).fill('UX-PAYPAL');
    await page.getByRole('button',{name:'UX-PAYPAL Cliente de prueba',exact:true}).click();
    await page.getByLabel('Agregar pieza',{exact:true}).fill('UX-PAYPAL-FILTRO');
    await page.getByRole('button',{name:/UX-PAYPAL-FILTRO.*Filtro de aceite/}).click();
    await expect(page.getByLabel('Cantidad 1',{exact:true})).toBeVisible();
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    await page.screenshot({path:info.outputPath(`internal-${width}.png`),fullPage:true});
    if(width===390) {
      await page.getByLabel('Descripción / referencia',{exact:true}).fill('UX-PAYPAL Cobro desde interfaz');
      await page.getByRole('button',{name:'Generar enlace',exact:true}).click();
      await expect(page.getByText('Enlace de pago generado.',{exact:true})).toBeVisible();
      const paymentUrl=await page.getByRole('link',{name:'Ver página pública',exact:true}).getAttribute('href');
      const guestContext=await browser.newContext({viewport:{width:390,height:850}});
      const guest=await guestContext.newPage(); await guest.goto(paymentUrl!);
      await expect(guest.locator('.order-summary .total strong')).toHaveText('USD 10.00');
      await expect(guest.getByRole('heading',{name:'Pago de tu pedido'})).toBeVisible();
      await expect(guest.getByText('UX-PAYPAL Cliente de prueba')).toHaveCount(0);
      await guestContext.close();
    }
    await page.goto('/admin/users');
    await page.screenshot({path:info.outputPath(`reference-${width}.png`),fullPage:true});
  }
  await page.goto(`/orders/status/${fixture.order_id}`);
  await expect(page.getByRole('heading',{name:'Cobro y seguimiento público'})).toBeVisible();
  await expect(page.getByText(fixture.tracking_code,{exact:true})).toBeVisible();
  expect(errors).toEqual([]);
});
