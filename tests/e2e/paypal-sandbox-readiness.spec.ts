import { test, expect } from '@playwright/test';
import fs from 'node:fs';
test.use({video:'off'});
test('SDK oficial Sandbox ofrece checkout elegible sin cuenta Quantum',async({page},info)=>{
  test.skip(process.env.E2E_PAYPAL_REAL_SANDBOX!=='1','Requires explicitly configured local Sandbox.');
  const fixture=JSON.parse(fs.readFileSync('storage/app/public-commerce-e2e.json','utf8'));
  const visual=fs.existsSync('storage/app/public-commerce-visual.json')?JSON.parse(fs.readFileSync('storage/app/public-commerce-visual.json','utf8')):null;
  await page.setViewportSize({width:390,height:900});
  await page.goto(`/pagar/${visual?.pending_token ?? fixture.payment_token}`);
  await expect(page.getByRole('heading',{name:'Pago de tu pedido'})).toBeVisible();
  await expect(page.locator('#paypal-button:not([hidden]), #guest-card-container:not([hidden])').first()).toBeVisible({timeout:45000});
  await page.screenshot({path:info.outputPath('sandbox-checkout-390.png'),fullPage:true});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
});
