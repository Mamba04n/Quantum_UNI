import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';
test.use({video:'off', extraHTTPHeaders: {}});
test('tablero visual, portadas privadas y navegación por etapa', async ({page}, info) => {
  test.skip(process.env.E2E_KANBAN_FIXTURES !== '1', 'Requires isolated quotation-kanban.php fixtures.');
  test.setTimeout(240_000);
  const errors: string[]=[];
  page.on('pageerror', e=>errors.push(e.message));
  page.on('response', r=>{if(r.status()>=400 && /livewire|quotation-cover|build\//.test(r.url())) errors.push(r.status()+' '+r.url());});
  await loginAs(page,'ux-quotation@quantum.test');
  for(const width of [1440,1920,768,390]) {
    await page.setViewportSize({width,height:950});
    await page.goto('/sales/quotations/fase-cotizacion');
    await page.waitForFunction(() => (window as any).Livewire?.all()?.length > 0);
    await page.getByPlaceholder('Buscar orden, cliente, producto o responsable...').fill('UX-KANBAN');
    const board=page.getByTestId('commercial-kanban-board');
    await expect(board.locator('article')).toHaveCount(40, {timeout:45_000});
    await expect(board.locator('img')).toHaveCount(39);
    await expect(page.getByTestId('kanban-column-purchase_preparation').locator('article')).toHaveCount(10);
    await expect(page.getByTestId('kanban-column-purchase_order_generated').locator('article')).toHaveCount(6);
    await expect.poll(()=>board.locator('img').first().evaluate(i=>(i as HTMLImageElement).complete && (i as HTMLImageElement).naturalWidth>0)).toBe(true);
    await page.screenshot({path:info.outputPath('kanban-'+width+'.png'),fullPage:false});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    await page.getByRole('button',{name:'Lista',exact:true}).click();
    const list=page.getByTestId('commercial-grouped-list');
    await expect(list.locator('details')).toHaveCount(6);
    await expect(list.locator('tbody tr[data-testid]')).toHaveCount(40);
    const preparation=page.getByTestId('list-stage-purchase_preparation');
    await expect(preparation.locator('tbody tr')).toHaveCount(10);
    await preparation.locator('summary').focus();
    await page.keyboard.press('Enter');
    await expect(preparation.locator('table')).not.toBeVisible();
    await page.keyboard.press('Enter');
    await expect(preparation.locator('table')).toBeVisible();
    await page.evaluate(()=>window.scrollTo(0,0));
    await page.mouse.move(0,0);
    await page.screenshot({path:info.outputPath('lista-'+width+'.png'),fullPage:false});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    if(width===1440){
      await list.getByRole('link',{name:'Abrir cotización UX-KANBAN-25',exact:true}).click();
      await expect(page).toHaveURL(/\/aprobacion\//);
      await page.goto('/sales/quotations/fase-cotizacion');
    }
    await page.getByRole('button',{name:'Kanban',exact:true}).click();
    if(width===1440){
      const link=board.getByRole('link',{name:/Abrir cotización UX-KANBAN-2:/});
      await expect(link).toHaveAttribute('href',/\/licitacion\//);
      await link.focus(); await page.keyboard.press('Enter');
      await expect(page).toHaveURL(/\/licitacion\//);
      await expect(page.getByRole('navigation',{name:'Progreso del proceso comercial'})).toBeVisible();
    }
    await page.goto('/admin/users');
    await expect(page.getByText('Equipo Interno',{exact:true})).toBeVisible();
    await page.screenshot({path:info.outputPath('reference-'+width+'.png'),fullPage:true});
  }
  await page.goto('/sales/quotations/fase-cotizacion');
  await page.getByRole('button',{name:'Lista',exact:true}).click();
  await expect(page.getByRole('columnheader',{name:'Código',exact:true}).first()).toBeVisible();
  expect(errors).toEqual([]);
});
