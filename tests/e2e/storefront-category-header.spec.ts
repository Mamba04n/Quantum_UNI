import {test,expect} from '@playwright/test';
test.use({video:'off'});
test('category filters update breadcrumbs without duplicate navigation',async({page},info)=>{
 for(const width of [1920,390]){
 await page.setViewportSize({width,height:950});await page.goto('/productos');
 await expect(page.locator('.catalog-category-nav')).toHaveCount(0);
 if(width===390)await page.getByRole('button',{name:'Filtros',exact:true}).click();
 const sidebar=page.locator('.products-sidebar');await sidebar.locator('input[value="filtracion"]').check();
 if(width===390)await page.getByRole('button',{name:'Cerrar filtros',exact:true}).click();
 await expect(page.locator('.catalog-breadcrumb [aria-current="page"]')).toHaveText('Filtraci\u00f3n');
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
 await page.screenshot({path:info.outputPath(`header-${width}.png`)});
 }
});
