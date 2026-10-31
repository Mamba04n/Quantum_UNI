import {test,expect} from '@playwright/test';
test.use({video:'off'});
test('filters follow document scroll and remain usable on mobile',async({page},info)=>{
 await page.goto('/productos');const sidebar=page.locator('.products-sidebar');
 const before=await sidebar.boundingBox();await page.evaluate(()=>scrollBy(0,500));await page.waitForTimeout(400);const after=await sidebar.boundingBox();expect(before!.y-after!.y).toBeGreaterThan(450);
 await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:info.outputPath('filters-desktop.png')});
 await page.setViewportSize({width:390,height:950});await page.goto('/productos');await page.getByRole('button',{name:'Filtros',exact:true}).click();await expect(sidebar).toBeVisible();
 await sidebar.getByRole('checkbox').check();await expect(sidebar.getByRole('checkbox')).toBeChecked();await sidebar.getByRole('button',{name:'Limpiar',exact:true}).click();await expect(sidebar.getByRole('checkbox')).not.toBeChecked();
 await page.screenshot({path:info.outputPath('filters-mobile.png')});await page.getByRole('button',{name:'Cerrar filtros',exact:true}).click();
});
