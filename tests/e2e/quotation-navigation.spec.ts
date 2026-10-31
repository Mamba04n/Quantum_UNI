import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { USERS } from './fixtures/test-data';

test.use({ video: 'off' });

test('recorrido Inicio, Áreas, Ventas y Cotizaciones', async ({ page }, testInfo) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', response => {
    if ((response.url().includes('/livewire/') || response.url().includes('/build/')) && response.status() >= 400) errors.push(`${response.status()} ${response.url()}`);
  });
  await loginAs(page, process.env.E2E_ADMIN_EMAIL ?? USERS.admin.email);
  for (const viewport of [{width:1920,height:1080}, {width:1366,height:768}, {width:768,height:1024}, {width:390,height:844}]) {
    await page.setViewportSize(viewport);
    await page.goto('/inicio');
    await expect(page.getByRole('heading', {name:'Continuar trabajando'})).toBeVisible();
    await expect(page.locator('aside')).toHaveCount(0);
    await page.screenshot({path:testInfo.outputPath(`inicio-${viewport.width}.png`), fullPage:true});
    await page.getByRole('button', {name:'Áreas', exact:true}).click();
    const menu=page.getByRole('navigation', {name:'Todas las áreas'});
    await expect(menu).toBeVisible();
    await page.screenshot({path:testInfo.outputPath(`areas-${viewport.width}.png`), fullPage:true});
    await menu.getByRole('link', {name:'Ventas',exact:true}).click();
    await expect(page.getByRole('heading', {name:'Ventas',exact:true})).toBeVisible();
    await page.screenshot({path:testInfo.outputPath(`ventas-${viewport.width}.png`), fullPage:true});
    const quotesLink=page.locator('main').getByRole('link', {name:/Cotizaciones/}).first();
    if(viewport.width===1366) {
      await quotesLink.hover();
      await expect(quotesLink).toHaveCSS('background-color','rgb(111, 18, 32)');
      await expect(quotesLink).toHaveCSS('color','rgb(255, 255, 255)');
    }
    await quotesLink.click();
    await expect(page.getByRole('heading',{name:'Cotizaciones',exact:true})).toBeVisible();
    await expect(page.getByTestId('commercial-kanban-board')).toBeVisible();
    await page.getByRole('button', { name: 'Lista', exact: true }).click();
    await expect(page.getByRole('columnheader',{name:'Código',exact:true}).first()).toBeVisible();
    await page.screenshot({path:testInfo.outputPath(`cotizaciones-${viewport.width}.png`), fullPage:true});
    const responseLink=page.locator('main a[href*="/quotations/licitacion/"]').first();
    if(await responseLink.count()) {
      await responseLink.click();
      await expect(page.getByRole('navigation',{name:'Progreso del proceso comercial'})).toBeVisible();
      await page.screenshot({path:testInfo.outputPath(`expediente-${viewport.width}.png`),fullPage:true});
      if(await page.getByRole('region',{name:'Respuesta Proveedor',exact:true}).count()) {
        await page.getByRole('button',{name:'Documentos y observaciones',exact:true}).click();
        await expect(page.getByText('Observaciones internas',{exact:true})).toBeVisible();
        await page.getByRole('button',{name:'Revisión final',exact:true}).click();
        await expect(page.getByRole('button',{name:'Continuar a Oferta Cliente',exact:true})).toBeVisible();
        await page.getByRole('button',{name:'Partidas',exact:true}).click();
      }
    }
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    await page.goto('/admin/users');
    await page.screenshot({path:testInfo.outputPath(`referencia-${viewport.width}.png`),fullPage:false});
  }
  expect(errors).toEqual([]);
});
