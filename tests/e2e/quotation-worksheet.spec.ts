import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';

test.use({video:'off'});
test('captura masiva, filtros, teclado y escala de partidas', async ({page}, testInfo) => {
  test.skip(process.env.E2E_QUOTATION_FIXTURES !== '1', 'Requires isolated quotation-workspace.php fixtures.');
  const errors: string[]=[];
  page.on('pageerror', e=>errors.push(e.message));
  page.on('response', r=>{if(r.url().includes('/livewire/') && r.status()>=400) errors.push(`${r.status()} ${r.url()}`)});
  await loginAs(page, 'ux-quotation@quantum.test');
  for(const count of [5,20,60]) {
    await page.goto('/sales/quotations/fase-cotizacion');
    await page.getByRole('button', { name: 'Lista', exact: true }).click();
    await page.getByRole('link',{name:`Abrir cotización UX-COT-${count}`,exact:true}).click();
    await expect(page.locator('[data-supplier-row]')).toHaveCount(count);
    await page.waitForFunction(() => (window as any).Alpine?.$data(document.getElementById('supplier-response-workspace')).saving === false);
    if(count===5) {
      const first=page.locator('[data-supplier-row]').nth(0);
      const second=page.locator('[data-supplier-row]').nth(1);
      const price=first.locator('[data-supplier-price]');
      await price.fill('45');
      await price.press('Tab');
      await expect(first.getByLabel('Garantía de UX-001')).toBeFocused();
      await page.keyboard.type('6 meses');
      await page.keyboard.press('Tab');
      await expect(first.locator('[data-supplier-delivery]')).toBeFocused();
      await page.keyboard.type('5 días');
      await page.keyboard.press('Tab');
      await expect(second.locator('[data-supplier-price]')).toBeFocused();
      await page.keyboard.type('20');
      await second.locator('[data-supplier-price]').press('Shift+Tab');
      await expect(first.locator('[data-supplier-delivery]')).toBeFocused();
      await expect(first.getByText('Sin guardar',{exact:true})).toBeVisible();
      await page.getByRole('button',{name:'Sin respuesta',exact:true}).click();
      await page.getByRole('button',{name:'Todas',exact:true}).click();
      await expect(price).toHaveValue('45');
      await page.getByRole('button',{name:'Guardar todas las partidas',exact:true}).click();
      await expect(first.getByText('Completa',{exact:true})).toBeVisible();
      await expect(second.getByText('Pendiente',{exact:true})).toBeVisible();
      await price.fill('46');
      await expect(first.getByText('Sin guardar',{exact:true})).toBeVisible();
      await page.getByRole('button',{name:'Guardar todas las partidas',exact:true}).click();
      await expect(first.getByText('Completa',{exact:true})).toBeVisible();
      await page.locator('[data-supplier-row]').nth(2).getByRole('combobox').first().selectOption('not_quoted');
      await page.getByRole('button',{name:'Guardar todas las partidas',exact:true}).click();
      await expect(page.locator('[data-supplier-row]').nth(2).locator('span').filter({hasText:/^Sin respuesta$/})).toBeVisible();
      await page.getByRole('button',{name:'Documentos y observaciones',exact:true}).click();
      await expect(page.getByText('Observaciones internas',{exact:true})).toBeVisible();
      await page.getByRole('button',{name:'Revisión final',exact:true}).click();
      await expect(page.getByRole('button',{name:'Continuar a Oferta Cliente',exact:true})).toBeVisible();
      await page.getByRole('button',{name:'Partidas',exact:true}).click();
    }
    while(await page.getByRole('button',{name:'Cerrar',exact:true}).count()) await page.getByRole('button',{name:'Cerrar',exact:true}).first().click();
    for(const viewport of [{width:1920,height:1080},{width:1366,height:768},{width:768,height:1024},{width:390,height:844}]) {
      await page.setViewportSize(viewport);
      expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
      await page.mouse.move(0, 0);
      await page.locator('[aria-label="Hoja de trabajo de partidas cotizadas"]').scrollIntoViewIfNeeded();
      await page.screenshot({path:testInfo.outputPath(`partidas-${count}-${viewport.width}.png`),fullPage:true});
    }
    await page.setViewportSize({width:1366,height:768});
  }
  expect(errors).toEqual([]);
});
